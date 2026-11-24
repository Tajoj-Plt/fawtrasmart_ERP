<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * تنظيف «إعدادات وحدات العملاء» بطلب المستخدم: كلُّ ما لا يقرؤه الكود،
 * وكلُّ ما يعمل «تلقائياً» بلا يدِ الموظف، خرج من الشاشة ومن النظام.
 *
 * **ما حُذف من الشيفرة معه**
 *
 *   • «الزيارات التلقائية» (`auto_visit_settings`) — كان مفتاحُها الوحيد
 *     الحيّ يحرس مسلكين: إقحامُ العميل الجديد في خطّ سير اليوم لكلّ موظّفٍ
 *     مسؤول (`ClientController::store()`)، وجدولةُ الزيارة القادمة من تصنيف
 *     الملاحظة (`ItineraryService` عبر `ClientRelationObserver`). وأربعةٌ
 *     من حقوله (`days_ahead`، `default_duration_minutes`، `default_visit_type`،
 *     `respect_client_frequency`) لم يقرأها شيءٌ قط.
 *   • «وضع تسجيل الحضور/الانصراف» (`visit_checkin_mode_settings`) — الوضع
 *     التلقائي كان متتبّعَ GPS صامتاً في القوالب الثلاثة يسجّل الوصول
 *     والانصراف (`VisitController::storeLocationEnhanced()`). القيمة المحفوظة
 *     كانت `manual` أصلاً، فالسلوك الفعلي لا يتغيّر.
 *   • «القبول التلقائي للتحويلات» (`invoice_transfer_settings.auto_accept_hours`)
 *     ومهمّتُه `clients:auto-accept-transfers` — مهمّةٌ معطَّلة وموسومة
 *     «غير مطبَّقة بعد».
 *   • مفتاحا «موظف العميل»: `require_primary` و`reassign_open_documents` —
 *     لا قارئ لهما.
 *   • صلاحية `automatic_itinerary_generation` — كان يقرؤها `ItineraryService`
 *     وحده.
 *
 * **صفوف «الحقول الإضافية» الميّتة**: `opening_balance` لا يقرؤه نموذج العميل،
 * و`note_settings`/`crm_settings` صفّا مجموعتين أُزيلتا (2026-09-13) فصارا
 * يظهران «حقلَين» عليهما تنبيه «غير مربوط».
 *
 * `down()` لا يعيد شيئاً: الشيفرة التي كانت تقرأ هذه الصفوف لم تعد موجودة،
 * فإعادتُها لا تعيد سلوكاً.
 */
return new class extends Migration
{
    private const DEAD_ROWS = [
        'auto_visit_settings',
        'visit_checkin_mode_settings',
        'opening_balance',
        'note_settings',
        'crm_settings',
    ];

    /** مفاتيحُ خيارات تُنزع من حقائب صفوفٍ باقية. */
    private const DEAD_OPTIONS = [
        'client_employee_settings' => ['require_primary', 'reassign_open_documents'],
        'invoice_transfer_settings' => ['auto_accept_hours'],
    ];

    public function up(): void
    {
        if (Schema::hasTable('general_client_settings')) {
            DB::table('general_client_settings')
                ->whereIn('key', self::DEAD_ROWS)
                ->delete();

            if (Schema::hasColumn('general_client_settings', 'options')) {
                foreach (self::DEAD_OPTIONS as $group => $keys) {
                    $row = DB::table('general_client_settings')->where('key', $group)->first(['id', 'options']);
                    $options = $row ? json_decode((string) $row->options, true) : null;

                    if (! is_array($options)) {
                        continue;
                    }

                    $stripped = array_diff_key($options, array_flip($keys));

                    if (count($stripped) !== count($options)) {
                        DB::table('general_client_settings')
                            ->where('id', $row->id)
                            ->update(['options' => json_encode($stripped, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            }
        }

        if (Schema::hasTable('cron_jobs')) {
            DB::table('cron_jobs')
                ->where('handler', 'clients:auto-accept-transfers')
                ->orWhere('key', 'client_auto_accept_transfers')
                ->delete();
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', 'automatic_itinerary_generation')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // لا شيء — انظر التعليق أعلى الملف
    }
};
