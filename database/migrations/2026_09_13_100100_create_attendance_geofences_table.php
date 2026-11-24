<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نطاقُ المرفق: دائرةٌ حول إحداثيات الموقع، وشبكةُ المقر المسموح منها.
 *
 * لِمَ جدولٌ جديد ولدينا `attendance_determinants`؟ لأن المحدِّدات تُسنَد
 * إلى **الموظف** عبر `holy_day_list_customizes` — قاعدةٌ على القسم أو
 * المسمّى الوظيفي — فلا تصلح وصفاً لـ«مرفق» له عنوانٌ وشبكةٌ وفرعٌ يتبعه.
 * والفرقُ عمليّ: الموظفُ الميداني قد يزور ثلاثةَ مرافق في يومٍ واحد،
 * فالنطاقُ يخصّ المكان لا الشخص. ويبقى المحدِّدُ عاملاً كطبقةٍ ثانية فوق
 * هذه (انظر `GeofenceGuard::verify`) حفاظاً على ما هو مُعَدٌّ اليوم.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_geofences')) {
            return;
        }

        Schema::create('attendance_geofences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            // مركزُ الدائرة ونصفُ قطرها — يبدأ بـ100 متر كما تنصّ السياسة
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->default(100)
                ->comment('نصف قطر النطاق بالأمتار — الافتراضي 100');

            /*
                دقّةُ قراءة الـGPS نفسها شرطٌ مستقل: جوالٌ يبلّغ «أنا داخل
                النطاق ± 2000 متر» لم يُثبت شيئاً. ما تجاوز هذا الحدّ يُردّ
                بطلب قراءةٍ أدقّ لا بتسجيلٍ مشكوك فيه.
            */
            $table->unsignedInteger('max_accuracy_meters')->default(100)
                ->comment('أقصى هامش خطأ مقبول في قراءة الجوال');

            // شبكةُ المقر: مدياتٌ بصيغة CIDR لا عناوينَ مفردة
            $table->json('allowed_ip_ranges')->nullable()
                ->comment('مديات IP مسموحة بصيغة CIDR — 192.168.1.0/24');
            $table->json('allowed_wifi_ssids')->nullable()
                ->comment('أسماء شبكات Wi-Fi المسموح التسجيل منها');

            /*
                شرطان مستقلّان لا شرطٌ واحد: جهازُ الفرع الثابت يُتحقَّق منه
                بالشبكة (لا GPS لحاسوبٍ مكتبي)، والميدانيُّ بالإحداثيات.
                ونطاقٌ واحد قد يخدم الاثنين.
            */
            $table->boolean('require_geo')->default(true)
                ->comment('يُلزم الإحداثيات — للتسجيل الميداني');
            $table->boolean('require_network')->default(false)
                ->comment('يُلزم الشبكة (IP/SSID) — لأجهزة المقر');

            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_geofences');
    }
};
