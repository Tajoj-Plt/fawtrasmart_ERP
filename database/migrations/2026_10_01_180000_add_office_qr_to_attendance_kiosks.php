<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «رمز المكتب» — الموظفُ يمسح رمزاً في المكتب بجواله فيُسجَّل حضورُه.
 *
 * عكسُ جهاز الفرع: هناك الجهازُ يمسح بطاقةَ الموظف، وهنا جوالُ الموظف يمسح
 * رمزَ المكان. والرمزُ نوعان:
 *   screen  — يتغيّر كل 30 ثانية على شاشة الجهاز المقترنة (صورتُه تنتهي خلال دقيقة)
 *   printed — مطبوعٌ ثابت؛ لا يُقبل إلا وموقعُ الجوال داخل مكان الجهاز
 * و`office_qr_version` يُبطل المطبوعَ القديم عند إصدار رمزٍ جديد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_kiosks', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_kiosks', 'office_qr')) {
                $table->string('office_qr', 10)->default('off')->after('paired_user_agent')
                    ->comment('off | screen | printed | both');
            }
            if (! Schema::hasColumn('attendance_kiosks', 'office_qr_version')) {
                $table->unsignedInteger('office_qr_version')->default(1)->after('office_qr');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_kiosks', function (Blueprint $table) {
            foreach (['office_qr', 'office_qr_version'] as $column) {
                if (Schema::hasColumn('attendance_kiosks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
