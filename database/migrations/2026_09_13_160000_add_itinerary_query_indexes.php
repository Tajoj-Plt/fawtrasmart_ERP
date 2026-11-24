<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهارسُ استعلامات خط السير.
 *
 * `employee_client_visits` (نحو 52 ألف صفٍّ في هذه القاعدة) لم يكن عليه فهرسٌ
 * على المندوب ولا التاريخ ولا الأسبوع ولا العميل — فكلُّ استعلامٍ في شاشات خط
 * السير (العرض، واللوحة، وملاحظات اليوم، والتخطيط) مسحٌ كامل للجدول: نحو نصف
 * ثانيةٍ لاستعلام يومِ مندوبٍ واحد.
 *
 * كلُّ فهرسٍ محروسٌ بـ«إن لم يكن موجوداً» — قواعدُ الإنتاج تسبق مجموعةَ الترحيلات.
 */
return new class extends Migration
{
    private const TABLE = 'employee_client_visits';

    private const INDEXES = [
        'ecv_employee_date_index' => ['employee_id', 'visit_date'],
        'ecv_visit_date_index' => ['visit_date'],
        'ecv_year_week_employee_index' => ['year', 'week_number', 'employee_id'],
        'ecv_client_index' => ['client_id'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (self::INDEXES as $name => $columns) {
            if (SchemaGuard::hasIndex(self::TABLE, $name)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn(self::TABLE, $column)) {
                    continue 2;
                }
            }

            Schema::table(self::TABLE, fn (Blueprint $table) => $table->index($columns, $name));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if (SchemaGuard::hasIndex(self::TABLE, $name)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropIndex($name));
            }
        }
    }
};
