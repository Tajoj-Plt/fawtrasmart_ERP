<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الملّاك — كيانٌ مستقلّ عن ملفّ العملاء المركزي.
 *
 *  • `re_owners`: بيانات المالك واتفاقية إدارة أملاكه (نسبة أو مبلغ ثابت، وأساس
 *    احتسابها) وحسابه الجاري في دليل الحسابات.
 *  • `re_owner_entries`: دفتر المالك — له (إيجار محصَّل) وعليه (عمولة، صيانة،
 *    تحويل) — ومنه كشف حسابه ورصيده.
 *  • أعمدة `owner_id` في المباني والمجمعات والعقود، وتُملأ من `owner_client_id`
 *    القديم (يُنشأ مالكٌ لكل عميلٍ كان مالكاً)، ويبقى القديم بلا استعمال.
 *  • عمولة الإدارة على الدفعات والتوزيعات.
 *  • الحالة الاجتماعية: أعزب/متزوج فقط — القيمة «أخرى» القديمة تُفرَّغ.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('re_owners')) {
            Schema::create('re_owners', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('owner_type', 20)->default('individual');
                $table->string('id_type', 30)->nullable();
                $table->string('id_number', 50)->nullable();
                $table->string('nationality', 100)->nullable();
                $table->string('marital_status', 20)->nullable();
                $table->string('mobile', 30)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('iban', 50)->nullable();

                // اتفاقية إدارة الأملاك: نسبة من الإيجار أو مبلغٌ ثابت لكل قسط
                $table->string('agreement_type', 20)->default('percentage');
                $table->decimal('agreement_value', 15, 2)->default(0);
                // أساس الاحتساب: من الإيجار المحصَّل فعلاً أو من المستحق
                $table->string('commission_basis', 20)->default('collected');

                $table->string('status', 20)->default('active')->index();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('re_owner_entries')) {
            Schema::create('re_owner_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_id')->index();
                $table->date('entry_date')->index();
                // rent | commission | maintenance | transfer | adjustment | reversal
                $table->string('type', 20)->index();
                // credit = له (يزيد رصيده) · debit = عليه
                $table->string('direction', 10);
                $table->decimal('amount', 15, 2);
                $table->unsignedBigInteger('building_id')->nullable()->index();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->unsignedBigInteger('contract_id')->nullable()->index();
                $table->unsignedBigInteger('payment_id')->nullable()->index();
                $table->unsignedBigInteger('maintenance_request_id')->nullable();
                $table->unsignedBigInteger('maintenance_cost_id')->nullable()->index();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->string('description');
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        foreach (['re_buildings', 're_contracts', 're_complexes'] as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'owner_id')) {
                Schema::table($name, fn (Blueprint $table) => $table->unsignedBigInteger('owner_id')->nullable()->index());
            }
        }

        if (! Schema::hasColumn('re_payments', 'commission_amount')) {
            Schema::table('re_payments', fn (Blueprint $table) => $table->decimal('commission_amount', 15, 2)->default(0));
        }

        if (! Schema::hasColumn('re_payment_allocations', 'commission_amount')) {
            Schema::table('re_payment_allocations', fn (Blueprint $table) => $table->decimal('commission_amount', 15, 2)->default(0));
        }

        $this->backfillOwners();

        if (Schema::hasTable('re_tenant_profiles')) {
            DB::table('re_tenant_profiles')->whereNotIn('marital_status', ['single', 'married'])->whereNotNull('marital_status')->update(['marital_status' => null]);
        }
    }

    /** لكل عميلٍ كان «مالكاً» في مبنى أو مجمع أو عقد: مالكٌ مقابل، وتُربط السجلات به. */
    private function backfillOwners(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        $clientIds = collect(['re_buildings', 're_contracts', 're_complexes'])
            ->filter(fn ($t) => Schema::hasTable($t) && Schema::hasColumn($t, 'owner_client_id'))
            ->flatMap(fn ($t) => DB::table($t)->whereNotNull('owner_client_id')->whereNull('owner_id')->distinct()->pluck('owner_client_id'))
            ->unique()->values();

        foreach ($clientIds as $clientId) {
            $client = DB::table('clients')->where('id', $clientId)->first();

            if (! $client) {
                continue;
            }

            $name = filled($client->trade_name ?? null) ? $client->trade_name
                : (trim(($client->first_name ?? '').' '.($client->last_name ?? '')) ?: '#'.$client->id);

            $ownerId = DB::table('re_owners')->insertGetId([
                'code' => 'OWN-'.str_pad((string) ((int) DB::table('re_owners')->max('id') + 1), 6, '0', STR_PAD_LEFT),
                'name' => $name,
                'mobile' => $client->mobile ?? null,
                'phone' => $client->phone ?? null,
                'email' => $client->email ?? null,
                'notes' => 'نُقل من ملفّ العملاء (#'.$client->id.')',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach (['re_buildings', 're_contracts', 're_complexes'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'owner_client_id')) {
                    DB::table($table)->where('owner_client_id', $clientId)->whereNull('owner_id')->update(['owner_id' => $ownerId]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['re_buildings', 're_contracts', 're_complexes'] as $name) {
            if (Schema::hasColumn($name, 'owner_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropIndex(['owner_id']);
                    $table->dropColumn('owner_id');
                });
            }
        }

        Schema::table('re_payments', fn (Blueprint $table) => $table->dropColumn('commission_amount'));
        Schema::table('re_payment_allocations', fn (Blueprint $table) => $table->dropColumn('commission_amount'));
        Schema::dropIfExists('re_owner_entries');
        Schema::dropIfExists('re_owners');
    }
};
