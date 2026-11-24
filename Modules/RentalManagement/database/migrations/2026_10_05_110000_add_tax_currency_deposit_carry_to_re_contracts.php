<?php

use App\Support\CurrencySettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * العقود: ضريبة العقد من إعدادات النظام (`tax_id`)، التأمين المرحَّل من العقد
 * السابق (`deposit_carried`)، علامة «عقد سابق — تسجيل تاريخي»، تاريخ سريان
 * التعديل، وتعبئة عملة العقود القائمة بعملة النظام.
 *
 * قابلٌ للتكرار: قاعدة التطوير تحمل `tax_id` أصلاً من عملٍ سابق.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('re_contracts')) {
            Schema::table('re_contracts', function (Blueprint $table) {
                if (! Schema::hasColumn('re_contracts', 'tax_id')) {
                    $table->unsignedBigInteger('tax_id')->nullable()->after('tax_rate');
                }

                if (! Schema::hasColumn('re_contracts', 'currency')) {
                    $table->string('currency', 10)->nullable()->after('deposit_deducted');
                }

                if (! Schema::hasColumn('re_contracts', 'deposit_carried')) {
                    $table->decimal('deposit_carried', 15, 2)->default(0)->after('security_deposit');
                }

                if (! Schema::hasColumn('re_contracts', 'is_historical')) {
                    $table->boolean('is_historical')->default(false)->after('status');
                }
            });

            // لقطة العملة: العقود التي سبقت العمود تأخذ عملة النظام الحالية مرّةً واحدة
            $code = null;

            try {
                $code = CurrencySettings::code();
            } catch (Throwable $e) {
                $code = null;
            }

            if ($code) {
                DB::table('re_contracts')->whereNull('currency')->orWhere('currency', '')->update(['currency' => $code]);
            }
        }

        if (Schema::hasTable('re_contract_amendments') && ! Schema::hasColumn('re_contract_amendments', 'effective_date')) {
            Schema::table('re_contract_amendments', function (Blueprint $table) {
                $table->date('effective_date')->nullable()->after('reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('re_contracts')) {
            Schema::table('re_contracts', function (Blueprint $table) {
                foreach (['deposit_carried', 'is_historical'] as $column) {
                    if (Schema::hasColumn('re_contracts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('re_contract_amendments') && Schema::hasColumn('re_contract_amendments', 'effective_date')) {
            Schema::table('re_contract_amendments', function (Blueprint $table) {
                $table->dropColumn('effective_date');
            });
        }
    }
};
