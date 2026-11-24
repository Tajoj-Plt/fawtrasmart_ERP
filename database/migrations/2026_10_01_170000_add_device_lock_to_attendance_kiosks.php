<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قفلُ جهاز الفرع على شاشةٍ واحدة.
 *
 * كان الاقترانُ رمزاً مشتركاً: أيُّ متصفّحٍ يُدخله يصير «جهاز الفرع» — فموظفٌ
 * عرف الرمز أقرن جوالَه ومسح بطاقتَه من بيته فسُجّل حضورُه «من جهاز الفرع».
 * الآن يُصدَر عند الاقتران مفتاحٌ سرّيٌّ يُحفظ في متصفّح الشاشة وحدها (وبصمتُه
 * هنا)، فلا يُقبل مسحٌ إلا من الشاشة التي تحمله، ولا تُقرَن شاشةٌ ثانية حتى
 * يفكّ المديرُ الأولى أو يصدر رمزاً جديداً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_kiosks', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_kiosks', 'device_token_hash')) {
                $table->string('device_token_hash', 64)->nullable()->after('pairing_code')
                    ->comment('sha256 لمفتاح الشاشة المقترنة — لا يُقبل مسحٌ إلا ممن يحمله');
            }
            if (! Schema::hasColumn('attendance_kiosks', 'paired_at')) {
                $table->timestamp('paired_at')->nullable()->after('device_token_hash');
            }
            if (! Schema::hasColumn('attendance_kiosks', 'paired_by')) {
                $table->unsignedBigInteger('paired_by')->nullable()->after('paired_at');
            }
            if (! Schema::hasColumn('attendance_kiosks', 'paired_user_agent')) {
                $table->string('paired_user_agent', 255)->nullable()->after('paired_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_kiosks', function (Blueprint $table) {
            foreach (['device_token_hash', 'paired_at', 'paired_by', 'paired_user_agent'] as $column) {
                if (Schema::hasColumn('attendance_kiosks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
