<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أرقام مستندات الرواتب — قسائم وصرف وسلف وخصميات.
 *
 * كانت هذه السجلات تُعرَف بـ`id` الخام في الشاشات والتقارير. صار لكلٍّ
 * عمود `code` من «الترقيم المتسلسل» (أقسام `salary-slip` و`payroll-run`
 * و`advance` و`deduction`).
 *
 *   • `nullable`: السجلات القائمة بلا رقم، ولا يُخترع لها واحد هنا.
 *   • `unique`: الرقم يميّز، وفهرسه يجعل `DocumentNumberService`
 *     يتخطّى المحجوز فعلاً. و`null` لا يتصادم مع `null` في MySQL.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'salary_slips',
        'payroll_runs',
        'salary_advances',
        'deductions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'code')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('code', 64)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'code')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(['code']);
                $blueprint->dropColumn('code');
            });
        }
    }
};
