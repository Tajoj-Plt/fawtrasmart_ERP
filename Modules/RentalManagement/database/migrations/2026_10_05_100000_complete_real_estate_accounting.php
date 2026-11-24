<?php

use App\Support\CurrencySettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\RentalManagement\Support\RealEstateAccounts;
use Modules\RentalManagement\Support\RealEstateSettings;

/**
 * إكمال محاسبة العقارات: ملكية المبنى، تسجيل المالك في الضريبة، ضريبة
 * العمولة، تسوية التأمين، رقم تحويل المالك، قيد تكلفة الصيانة، والعملة
 * المسجَّلة على كل حركة. قابلٌ للتكرار: كل خطوةٍ تفحص قبل أن تكتب.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->column('re_buildings', 'ownership', fn (Blueprint $t) => $t->string('ownership', 10)->default('managed')->index());

        $this->column('re_owners', 'is_vat_registered', fn (Blueprint $t) => $t->boolean('is_vat_registered')->default(false));
        $this->column('re_owners', 'tax_number', fn (Blueprint $t) => $t->string('tax_number', 50)->nullable());

        $this->column('re_payments', 'payment_method_id', fn (Blueprint $t) => $t->unsignedBigInteger('payment_method_id')->nullable()->index());
        $this->column('re_payments', 'tax_amount', fn (Blueprint $t) => $t->decimal('tax_amount', 15, 2)->default(0));
        $this->column('re_payments', 'owner_tax_amount', fn (Blueprint $t) => $t->decimal('owner_tax_amount', 15, 2)->default(0));
        $this->column('re_payments', 'commission_tax_amount', fn (Blueprint $t) => $t->decimal('commission_tax_amount', 15, 2)->default(0));

        $this->column('re_owner_entries', 'currency', fn (Blueprint $t) => $t->string('currency', 10)->nullable());
        $this->column('re_owner_entries', 'reference_no', fn (Blueprint $t) => $t->string('reference_no', 60)->nullable()->index());
        $this->column('re_owner_entries', 'reverses_entry_id', fn (Blueprint $t) => $t->unsignedBigInteger('reverses_entry_id')->nullable()->index());
        $this->column('re_owner_entries', 'deposit_settlement_id', fn (Blueprint $t) => $t->unsignedBigInteger('deposit_settlement_id')->nullable()->index());

        $this->column('re_maintenance_costs', 'currency', fn (Blueprint $t) => $t->string('currency', 10)->nullable());
        $this->column('re_maintenance_costs', 'charged_to', fn (Blueprint $t) => $t->string('charged_to', 20)->nullable());
        $this->column('re_maintenance_costs', 'treasury_account_id', fn (Blueprint $t) => $t->unsignedBigInteger('treasury_account_id')->nullable());
        $this->column('re_maintenance_costs', 'supplier_id', fn (Blueprint $t) => $t->unsignedBigInteger('supplier_id')->nullable());
        $this->column('re_maintenance_costs', 'journal_entry_id', fn (Blueprint $t) => $t->unsignedBigInteger('journal_entry_id')->nullable()->index());
        $this->column('re_maintenance_costs', 'schedule_id', fn (Blueprint $t) => $t->unsignedBigInteger('schedule_id')->nullable()->index());

        // قسط «صيانة على المستأجر» يحمل بيانه ومصدره
        $this->column('re_payment_schedules', 'description', fn (Blueprint $t) => $t->string('description')->nullable());
        $this->column('re_payment_schedules', 'maintenance_cost_id', fn (Blueprint $t) => $t->unsignedBigInteger('maintenance_cost_id')->nullable()->index());

        if (! Schema::hasTable('re_deposit_settlements')) {
            Schema::create('re_deposit_settlements', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->index();
                $table->unsignedBigInteger('contract_id')->index();
                $table->date('settled_at');
                $table->decimal('held_amount', 15, 2);
                $table->decimal('deducted_amount', 15, 2)->default(0);
                $table->decimal('refunded_amount', 15, 2)->default(0);
                $table->string('deduct_to', 10)->default('owner'); // owner | office
                $table->unsignedBigInteger('treasury_account_id')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->text('reason')->nullable();
                $table->string('currency', 10)->nullable();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // مبنى بلا مالك = مملوك للمنشأة
        DB::table('re_buildings')->whereNull('owner_id')->where('ownership', '!=', 'own')->update(['ownership' => 'own']);

        // العملة لقطةٌ وقت الإنشاء؛ ما سبق هذا الترحيل كان بعملة النظام يومها
        $currency = $this->systemCurrency();

        foreach (['re_payments', 're_owner_entries', 're_maintenance_costs', 're_deposit_settlements'] as $table) {
            DB::table($table)->whereNull('currency')->update(['currency' => $currency]);
        }

        // تكاليف سابقة: «على من» من طلبها، وقيدها من حركة دفتر المالك إن وُجدت
        // `UPDATE … JOIN` صيغة MySQL — وقاعدة SQLite للاختبار تبدأ فارغة فلا تكاليف سابقة فيها
        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE re_maintenance_costs c JOIN re_maintenance_requests r ON r.id = c.request_id SET c.charged_to = r.charge_to WHERE c.charged_to IS NULL');
            DB::statement("UPDATE re_maintenance_costs c JOIN re_owner_entries e ON e.maintenance_cost_id = c.id AND e.type = 'maintenance' AND e.journal_entry_id IS NOT NULL SET c.journal_entry_id = e.journal_entry_id WHERE c.journal_entry_id IS NULL");
        }

        $this->settings();

        Cache::forget(RealEstateSettings::CACHE_KEY);

        try {
            RealEstateAccounts::ensureAll();
        } catch (Throwable $e) {
            // دليل حساباتٍ غير مهيّأ (تثبيتٌ جديد قبل الزرع): تُنشأ الحسابات عند أول حاجة
            Log::warning('تعذّر تجهيز حسابات العقارات أثناء الترحيل', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        // لا تراجع: الأعمدة تحمل بياناتٍ مالية بعد أول استعمال.
    }

    private function column(string $table, string $column, Closure $definition): void
    {
        if (Schema::hasTable($table) && ! Schema::hasColumn($table, $column)) {
            Schema::table($table, fn (Blueprint $t) => $definition($t));
        }
    }

    private function systemCurrency(): string
    {
        try {
            return CurrencySettings::code();
        } catch (Throwable $e) {
            return 'SAR';
        }
    }

    /** `default_tax_id` يخلف `default_tax_rate`، وحساب ضريبة الوحدة الخاص يُلغى لصالح حساب النظام. */
    private function settings(): void
    {
        if (! Schema::hasTable('re_settings')) {
            return;
        }

        $hasTax = DB::table('re_settings')->where('key', 'default_tax_id')->whereNotNull('value')->exists();

        if (! $hasTax && Schema::hasTable('tax_sittings')) {
            $rate = DB::table('re_settings')->where('key', 'default_tax_rate')->value('value');
            $rate = is_numeric($rate) ? (float) $rate : 15.0;
            $taxId = $rate > 0 ? DB::table('tax_sittings')->where('tax', $rate)->orderBy('id')->value('id') : null;

            if ($taxId) {
                DB::table('re_settings')->updateOrInsert(['key' => 'default_tax_id'], ['value' => (string) $taxId, 'updated_at' => now(), 'created_at' => now()]);
            }
        }

        if (! DB::table('re_settings')->where('key', 'commission_taxable')->exists()) {
            DB::table('re_settings')->insert(['key' => 'commission_taxable', 'value' => '0', 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::table('re_settings')->whereIn('key', ['vat_account_id', 'default_tax_rate'])->delete();
    }
};
