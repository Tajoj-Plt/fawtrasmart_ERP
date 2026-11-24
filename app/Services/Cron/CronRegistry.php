<?php

namespace App\Services\Cron;

use App\Jobs\EvaluateCustomerTargetsJob;
use App\Jobs\ProcessAutoDepartures;

/**
 * سجلّ ما يُسمح بجدولته وتشغيله من لوحة التحكّم.
 *
 * هذه هي حدود الأمان في نظام المهام كلّه: لا يُنفَّذ إلا ما ورد اسمه هنا
 * حرفياً. المستخدم لا يكتب أمراً — يختار من هذه القائمة، وأي قيمة تصل من
 * الواجهة تُطابَق على المفاتيح أدناه قبل أن تقترب من `Artisan::call`.
 *
 * ما هو مستبعَد عمداً:
 * - أوامر التهجير والمسح (`migrate:fresh`, `db:wipe`) — تمحو البيانات.
 * - أوامر التطوير والفحص (`test:*`, `verify:*`, `tinker`) — لا معنى لجدولتها.
 * - أوامر الترحيل لمرّة واحدة (`region-groubs:migrate-to-branches` وأخواتها)
 *   — تشغيلها المتكرّر يفسد البيانات التي رحّلتها.
 * - أي أمر خارج هذه القائمة، بما فيه أوامر الإطار نفسها.
 *
 * `options` هي أسماء الخيارات المسموح تمريرها لكل أمر. خيار غير مذكور
 * يُرفَض — وإلا لأمكن تمرير `--force` إلى أمر يحترمه فيتخطّى حماياته.
 *
 * `available => false` تعني مهمّة ERP مخطَّطة لم يُكتَب أمرها بعد. وجودها هنا
 * يجعلها تظهر في شاشة المهام كخطّة معلنة، والمُشغّل يرفض تشغيلها برسالة
 * صريحة بدل أن يفشل بـ«أمر غير موجود». عند كتابة الأمر فعلياً:
 * احذف `available => false` من صفّه هنا، ثم فعّل المهمة من الشاشة.
 */
class CronRegistry
{
    /**
     * أوامر Artisan المسموح جدولتها.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function commands(): array
    {
        return [
            // ==================== إدارة العقارات ====================
            'realestate:sweep' => [
                'label' => 'العقارات: كنس العقود والاستحقاقات',
                'description' => 'يُنهي العقود التي انقضت مدّتها ويحرّر وحداتها، ويحدّث حالات الاستحقاقات (مستحقة/متأخرة)، ويُنهي العروض التي فات تاريخها.',
                'type' => 'maintenance',
                'options' => [],
                'suggested_schedule' => 'daily',
            ],
            'realestate:alerts' => [
                'label' => 'العقارات: تنبيهات العقود والدفعات',
                'description' => 'ينبّه بالعقود التي تقترب نهايتها وبالدفعات التي قارب استحقاقها أو تأخّرت — مرّةً للسجلّ الواحد كلّ بضعة أيام.',
                'type' => 'notification',
                'options' => ['dry-run'],
                'suggested_schedule' => 'daily',
            ],

            // ---------- المبيعات ----------
            'sales:generate-periodic-invoices' => [
                'label' => 'إصدار فواتير الاشتراكات الدورية',
                'description' => 'يُصدر فواتير المبيعات المستحقّة من الاشتراكات الدورية المفعَّلة، ويتخطّى ما صدر من قبلُ وما وقع في فترةٍ محاسبية مقفلة.',
                'type' => 'sales',
                'options' => ['dry-run', 'date'],
                'suggested_schedule' => 'daily',
            ],

            'zatca:submit-pending' => [
                'label' => 'إرسال الفواتير الإلكترونية المعلَّقة',
                'description' => 'يعيد إرسال ما تعذّر بلوغه هيئة الزكاة والضريبة والجمارك بملفّه الموقَّع نفسه، ويرسل ما لم يُرسَل من الفواتير حين يكون الإرسال التلقائي مفعَّلاً. لا يفعل شيئاً والخدمة معطَّلة أو غير مسجَّلة.',
                'type' => 'sales',
                'options' => ['limit', 'dry-run'],
                'suggested_schedule' => 'every_15_minutes',
            ],

            // ---------- المالية ----------
            /*
                ليس `accounting:post-recurring-entries` المخطَّطة في `planned()`:
                تلك قيودُ يوميةٍ دورية، وهذه سنداتُ قبضٍ وصرفٍ علَّمها المستخدم
                «سند مكرَّر» — تُصدر النسخةُ بمسار حفظ السند نفسه.
            */
            'finance:generate-recurring-vouchers' => [
                'label' => 'إصدار سندات القبض والصرف المكرَّرة',
                'description' => 'يُصدر النسخة المستحقّة من كل سند قبض أو صرف مكرَّر بدورته (يومي، أسبوعي، كل أسبوعين، شهري، سنوي) حتى تاريخ نهايته — بقيدها وخزينتها وترقيمها كسندٍ يدوي — ويتخطّى ما صدر من قبلُ وما وقع في فترةٍ محاسبية مقفلة. لا يوزّع سند القبض على فواتير العميل.',
                'type' => 'maintenance',
                'options' => ['dry-run', 'date'],
                'suggested_schedule' => 'daily',
            ],

            'quotations:expire' => [
                'label' => 'إنهاء عروض الأسعار المنتهية',
                'description' => 'ينقل عروض الأسعار التي تجاوزت تاريخ صلاحيتها إلى حالة «منتهي الصلاحية»، فلا تُحوَّل إلى فواتير بأسعارٍ انقضت. و`--grace` مهلةٌ بالأيام بعد التاريخ قبل أن تنقلب الحالة.',
                'type' => 'maintenance',
                'options' => ['grace', 'dry-run'],
                'suggested_schedule' => 'daily',
            ],

            'itinerary:mark-missed-visits' => [
                'label' => 'تصنيف زيارات خط السير الفائتة',
                'description' => 'بعد منتصف الليل: كل زيارة خط سير انقضى يومُها ولم تكتمل (لم تبدأ، أو بدأت ولم تُنهَ) تُصنَّف «فائتة».',
                'type' => 'maintenance',
                'options' => ['dry-run'],
                'suggested_schedule' => 'daily',
            ],
            // ---------- الإشعارات ----------
            'sms:flush' => [
                'label' => 'إرسال الرسائل النصية المؤجَّلة',
                'description' => 'يرسل الرسائل المؤجَّلة إلى ما بعد ساعات الهدوء ويعيد محاولة الفاشلة وينظّف السجل.',
                'type' => 'notification',
                'options' => ['limit', 'dry-run'],
                'suggested_schedule' => 'every_5_minutes',
            ],
            'notifications:process-scheduled' => [
                'label' => 'إرسال الإشعارات المجدولة',
                'description' => 'يرسل الإشعارات التي حان موعدها ولم تُرسَل بعد.',
                'type' => 'notification',
                'options' => [],
                'suggested_schedule' => 'every_minute',
            ],
            'contracts:check-expirations' => [
                'label' => 'فحص العقود المنتهية قريباً',
                'description' => 'ينبّه على العقود التي أوشكت على الانتهاء.',
                'type' => 'notification',
                // ما لا يُذكر هنا يُرفض قبل أن يبلغ `Artisan::call`
                'options' => ['days', 'dry-run'],
                'suggested_schedule' => 'daily',
            ],
            /*
                نُقلت من `planned()` إلى هنا حين كُتب أمرُها.

                وهذا هو المسارُ المصمَّم لا التفافٌ عليه: ما في `planned()`
                يُستكمَل بـ`available => false`، فيبذره `CronJobsSeeder`
                معطَّلاً موسوماً «(غير مطبَّقة بعد)» ويرفض `CronJobRunner`
                تشغيلَه. وبانتقالها هنا تصير `isAvailable()` صادقةً، فينزع
                السيدرُ الوسمَ **ويفعّل الصفَّ تلقائياً** عند تشغيله.

                ولم تُكتب قبل اليوم لسببٍ وجيه: لا بيانات لها — كان
                `employee_attachments` بلا نوعٍ ولا تاريخِ انتهاء. فلمّا
                وُجد `employee_documents` وُجد ما تقرؤه.
            */
            'hr:document-expiry-alerts' => [
                'label' => 'تنبيه انتهاء وثائق الموظفين',
                'description' => 'ينبّه على الإقامات والرخص والوثائق الصحّية قبل انتهائها.',
                'type' => 'notification',
                // ما لا يُذكر هنا يُرفض قبل أن يبلغ `Artisan::call`
                'options' => ['days', 'dry-run'],
                'suggested_schedule' => 'daily',
            ],
            /*
                نُقلت من `planned()` إلى هنا حين كُتب أمرُها — كما نُقلت
                `hr:document-expiry-alerts` أعلاه، وللسبب نفسِه: ما بقي في
                `planned()` يُستكمَل بـ`available => false` فيرفض
                `CronJobRunner` تشغيلَه ويبذره السيدر معطَّلاً موسوماً «(غير
                مطبَّقة بعد)». وبانتقالها هنا يصير الصفُّ المبذور حيّاً.

                ولم تُكتب قبل اليوم لسببٍ وجيه: لا أجلَ لها تقرؤه. حقلُ
                «شروط الدفع» في شاشة الفاتورة كان يُرسَل باسم `terms` ويُكتب
                باسم `terms`، ولا عمودَ بهذا الاسم في `invoices` —
                فيُسقطه `$fillable` بصمت ويبقى `payment_terms` فارغاً أبداً.

                وأجلُ كلّ فاتورةٍ من عمودها لا مسطرةٌ واحدة: نظيرُها في
                المشتريات يقيس تقادماً واحداً من تاريخ الفاتورة
                (`--days=30`) لأنّ `purchase_invoices` بلا عمود أجل. وهنا
                عميلٌ على «صافي ٧» وآخرُ على «صافي ٦٠» لا يُقاسان معاً.
            */
            'invoices:payment-reminders' => [
                'label' => 'تذكير العملاء بالفواتير المستحقّة',
                'description' => 'ينبّه على فواتير المبيعات الآجلة التي حلّ أجلُها أو قارب — بحسب «شروط الدفع» المحفوظة في كلّ فاتورة. و`--before` أيامُ التذكير الاستباقيّ قبل الاستحقاق، وصفرٌ يقصره على المتأخّر وحده.',
                'type' => 'notification',
                'options' => ['before', 'dry-run'],
                'suggested_schedule' => 'daily',
            ],

            'suppliers:due-payments-alerts' => [
                'label' => 'تنبيه مستحقّات الموردين',
                'description' => 'ينبّه على فواتير الموردين التي لم تُسدَّد ومضى على تاريخها المدّة المحدَّدة.',
                'type' => 'notification',
                'options' => ['days', 'dry-run'],
                'suggested_schedule' => 'daily',
            ],

            /*
                تنبيهات الأقساط وغراماتها — أمران لا واحد.

                التنبيهُ يُخبر، والغرامةُ تُقيَّد. ودمجُهما في أمرٍ واحد
                يجعل من أراد التذكير وحده — بلا سياسةِ غرامة — مضطرّاً إلى
                تشغيل ما يكتب في دفتر الأستاذ.
            */
            'installments:due-alerts' => [
                'label' => 'تنبيه استحقاق الأقساط',
                'description' => 'ينبّه بالأقساط التي حان استحقاقها أو قاربته أو تأخّرت. و`--before` أيامُ التذكير الاستباقي قبل الاستحقاق، وصفرٌ يُلغيه.',
                'type' => 'notification',
                'options' => ['before', 'dry-run'],
                'suggested_schedule' => 'daily',
            ],

            'installments:late-fees' => [
                'label' => 'احتساب غرامات تأخير الأقساط',
                'description' => 'يحمّل غرامة التأخير على الأقساط المتأخّرة بحسب سياسة كلّ اتفاقية — النوع والقيمة وأيام السماح والأساس والسقف — ويرحّلها قيداً: من ح/ العميل إلى ح/ غرامات تأخير الأقساط. لا يفعل شيئاً لاتفاقيةٍ بلا سياسة غرامة.',
                'type' => 'maintenance',
                // `--agreement` لحصر التشغيل باتفاقية، و`--dry-run` للمعاينة.
                // `--no-notify` يوقف التنبيه ويُبقي القيد
                'options' => ['agreement', 'dry-run', 'no-notify'],
                'suggested_schedule' => 'daily',
            ],

            // ---------- التقارير ----------
            'reports:daily_employee' => [
                'label' => 'تقرير الموظفين اليومي',
                'description' => 'يرسل ملخّص أداء الموظفين اليومي.',
                'type' => 'report',
                'options' => [],
                'suggested_schedule' => 'daily',
            ],

            // ---------- العمليات المحاسبية والإدارية ----------
            'commissions:calculate-weekly' => [
                'label' => 'حساب العمولات الأسبوعية',
                'description' => 'يحسب عمولات المندوبين عن الأسبوع المنتهي.',
                'type' => 'maintenance',
                'options' => [],
                'suggested_schedule' => 'weekly',
            ],
            'payroll:process' => [
                'label' => 'معالجة قسائم الرواتب',
                'description' => 'يولّد قسائم الرواتب الجديدة ويصرف المستحقّة منها.',
                'type' => 'maintenance',
                'options' => [],
                'suggested_schedule' => 'daily',
            ],
            'attendance:process' => [
                'label' => 'معالجة الحضور اليومي',
                'description' => 'يحسب التأخير والغياب لكل موظف نشط ويطبّق الخصميات.',
                'type' => 'maintenance',
                'options' => [],
                'suggested_schedule' => 'daily',
            ],
            'points:monthly-top-performer' => [
                'label' => 'نقاط أفضل موظف شهرياً',
                'description' => 'يمنح نقاط أفضل أداء في نهاية كل شهر.',
                'type' => 'maintenance',
                'options' => [],
                'suggested_schedule' => 'monthly',
            ],
            'clients:evaluate-targets' => [
                'label' => 'التقييم الديناميكي للعملاء',
                'description' => 'يعيد تقييم تصنيف العملاء حسب نشاطهم.',
                'type' => 'maintenance',
                'options' => [],
                'suggested_schedule' => 'daily',
            ],
            'targets:process-settlements' => [
                'label' => 'تسوية أهداف المبيعات والتحصيلات',
                'description' => 'ينفّذ التسويات المستحقّة ويحسب العمولات — بعد فحص يوم التسوية ووقتها في «إعدادات الأهداف».',
                'type' => 'maintenance',
                // `--type` يحصر النوع، و`--dry-run` يعرض دون حفظ. `--force`
                // مستبعَد: يتخطّى فحص الاستحقاق فيسوّي فترة غير منتهية
                'options' => ['type', 'dry-run'],
                'suggested_schedule' => 'hourly',
            ],
            'permissions:sync' => [
                'label' => 'مزامنة الصلاحيات',
                'description' => 'يزامن قائمة الصلاحيات من الكود إلى قاعدة البيانات.',
                'type' => 'maintenance',
                'options' => [],
                'suggested_schedule' => 'weekly',
            ],

            // ---------- التنظيف ----------
            'sessions:reconcile' => [
                'label' => 'مصالحة سجلّ الجلسات',
                'description' => 'يغلق جلسات سجلّ الجلسات التي انتهت بلا حدث خروج، وينظّف القديم منها.',
                'type' => 'maintenance',
                'options' => ['prune', 'dry-run'],
                'suggested_schedule' => 'every_10_minutes',
            ],
            'tasks:mark-overdue' => [
                'label' => 'المهام: وضع المتأخّر في حالة «متأخرة»',
                'description' => 'يضع المهامّ التي فات تاريخ استحقاقها ولم تكتمل في حالة «متأخرة» — وبدونه لا تتأخّر مهمّةٌ إلا إن سُحبت يدوياً.',
                'type' => 'maintenance',
                'options' => ['dry-run'],
                'suggested_schedule' => 'daily',
            ],
            'project-invites:cleanup' => [
                'label' => 'تنظيف دعوات المشاريع المنتهية',
                'description' => 'يحذف الدعوات التي انتهت صلاحيتها.',
                'type' => 'cleanup',
                'options' => [],
                'suggested_schedule' => 'daily',
            ],
            'auth:clear-resets' => [
                'label' => 'تنظيف روابط استعادة كلمة المرور',
                'description' => 'يحذف رموز استعادة كلمة المرور المنتهية.',
                'type' => 'cleanup',
                'options' => [],
                'suggested_schedule' => 'daily',
            ],
            'queue:prune-failed' => [
                'label' => 'تنظيف المهام الفاشلة',
                'description' => 'يحذف سجلّات المهام الفاشلة الأقدم من المدّة المحدَّدة.',
                'type' => 'cleanup',
                'options' => ['hours'],
                'suggested_schedule' => 'weekly',
            ],
            'cron:prune-executions' => [
                'label' => 'تنظيف سجلّ تنفيذ المهام',
                'description' => 'يحذف سجلّات التنفيذ الأقدم من المدّة المحدَّدة حتى لا ينمو الجدول بلا حدّ.',
                'type' => 'cleanup',
                'options' => ['days'],
                'suggested_schedule' => 'weekly',
            ],

            // ---------- النسخ الاحتياطي ----------
            'backup:scheduled-run' => [
                'label' => 'تشغيل النسخ الاحتياطي المجدول',
                'description' => 'ينفّذ النسخة الاحتياطية حسب إعدادات النسخ الاحتياطي ثم يطبّق سياسة الاحتفاظ.',
                'type' => 'backup',
                // تسمح بجدولة أكثر من مهمة نسخ بنطاقات مختلفة: كاملة
                // أسبوعياً وجداول الحركات كل ساعة مثلاً. أسماء الجداول
                // تُصفَّى في `BackupService` مقابل جداول القاعدة الفعلية
                'options' => ['type', 'all-tables', 'tables', 'exclude-tables', 'skip-cleanup'],
                'suggested_schedule' => 'daily',
            ],
            'backup:cleanup' => [
                'label' => 'تنظيف النسخ الاحتياطية القديمة',
                'description' => 'يحذف النسخ التي تجاوزت سياسة الاحتفاظ المضبوطة.',
                'type' => 'backup',
                'options' => [],
                'suggested_schedule' => 'daily',
            ],
            'backup:monitor' => [
                'label' => 'مراقبة صحّة النسخ الاحتياطية',
                'description' => 'يفحص وجود نسخة حديثة وسلامة أحجامها.',
                'type' => 'backup',
                'options' => [],
                'suggested_schedule' => 'daily',
            ],

            /*
                مواعيد العملاء — كُتب أمرا التذكير والتوليد، فمكانُهما هنا لا في
                `planned()`. كان `clients:appointment-reminders` هناك وأمرُه مكتوب:
                فبُذر صفُّه موسوماً «(غير مطبَّقة بعد)» ويرفض المُشغّل تشغيله وإن فُعِّل.
            */
            'appointments:generate-recurring' => [
                'label' => 'توليد المواعيد المتكررة',
                'description' => 'ينشئ الموعد التالي لكل موعد متكرر قبل حلوله بـ`--days` يوماً (7 افتراضياً)، بنفس العميل والموظفين والوقت، حتى تاريخ نهاية التكرار.',
                'type' => 'maintenance',
                'options' => ['days', 'dry-run'],
                'suggested_schedule' => 'daily',
            ],
            'clients:appointment-reminders' => [
                'label' => 'تذكير مواعيد العملاء',
                'description' => 'يذكّر موظفي الموعد بموعدهم قبل حلوله بالمدّة المضبوطة في إعدادات المواعيد، ويُقفل المواعيد الفائتة إن كان الإقفال التلقائي مفعَّلاً.',
                'type' => 'notification',
                'options' => ['dry-run'],
                'suggested_schedule' => 'hourly',
            ],

            // كان في `planned()` وأمرُه موجود — فرفضه المُشغّل وإن فُعِّل صفُّه، وبقيت
            // مهلُ التذكير في «إعدادات الحجوزات» تُحفظ ولا يُرسَل شيء. التكرارُ
            // آمن: `reservation_reminders` يسجّل ما أُرسل فلا يُعاد.
            //
            // ساعيّةً لا يوميّة: المهلُ قصيرةٌ («قبل ساعتين») واليوميةُ تلتقط
            // منها ما صادف ساعةَ تشغيلها وحدَه — انظر رأس الأمر.
            'reservations:reminders' => [
                'label' => 'تذكير الحجوزات القادمة',
                'description' => 'يذكّر العملاء بحجوزاتهم المؤكَّدة قبل موعدها حسب المهل المضبوطة في إعدادات الحجوزات، مرّةً واحدة لكل مهلة.',
                'type' => 'notification',
                'options' => ['window', 'dry-run'],
                'suggested_schedule' => 'hourly',
            ],

            // أمرُ إصلاحٍ لا دورةٌ: يسدّ ما فات المزامنةَ الفورية (حجوزات
            // أُكّدت والوحدةُ معطَّلة، أو استيراد). كان مسجَّلاً في المزوّد
            // بلا صفٍّ هنا فترفضه الشاشةُ والمُشغّل معاً.
            'reservations:sync-appointments' => [
                'label' => 'مزامنة مواعيد الحجوزات',
                'description' => 'يُنشئ موعداً لكلّ حجزٍ مؤكَّد لا موعدَ له، ويُحدّث مواعيد الحجوزات القائمة. آمن التكرار.',
                'type' => 'maintenance',
                'options' => ['dry-run', 'locale'],
                'suggested_schedule' => 'daily',
            ],

            // ---------- الاشتراك والتكامل ----------
            // **يجب أن يبقى هذا الصفّ هنا لا في `planned()`**: كل ما في
            // `planned()` يُستكمَل بـ `available => false`، فلو وُضع فيه بذره
            // السيدر معطَّلاً وموسوماً «غير مطبَّقة بعد»، ورفض المُشغّل تشغيله —
            // فيعمل النظام بحدود لقطةٍ قديمة لا يعرف أنها انتهت
            'fawtra:sync' => [
                'label' => 'مزامنة الاشتراك مع لوحة فوترة',
                'description' => 'يجلب حالة الاشتراك والباقة وحدود الاستخدام والتنبيهات من لوحة تحكّم فوترة. لا يفعل شيئاً إن لم يكن النظام مربوطاً، ويحترم التراجع الأسّي بعد الإخفاق.',
                'type' => 'maintenance',
                'options' => ['force'],
                'suggested_schedule' => 'every_15_minutes',

                // مخفيّ من قائمة اختيار المهام: مهمّته الوحيدة مُنشأة ومحميّة،
                // وإتاحته للاختيار تسمح بإنشاء نسخة ثانية تُضاعف النداءات
                // وتستهلك حدّ الطلبات عند فوترة بلا فائدة
                'hidden' => true,
            ],
        ] + static::planned();
    }

    /**
     * دورات ERP القياسية المخطَّطة — معلَنة هنا ولم تُنفَّذ بعد.
     *
     * الغاية أن تكون خطّة الأتمتة مرئية في شاشة المهام لا محفوظة في ذهن
     * أحد: كل صفّ يقول ماذا يفعل ومتى ينبغي أن يعمل، فيبقى ما ينقص النظام
     * ظاهراً ومرتَّباً. كلها `available => false` فلا تُشغَّل قبل كتابتها.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function planned(): array
    {
        $plan = [
            // ---------- الفوترة والمبيعات ----------
            'invoices:generate-recurring' => [
                'label' => 'توليد الفواتير الدورية',
                'description' => 'ينشئ فواتير العقود والاشتراكات التي حان دورها.',
                'type' => 'maintenance',
                'suggested_schedule' => 'daily',
            ],
            'invoices:mark-overdue' => [
                'label' => 'تعليم الفواتير المتأخّرة',
                'description' => 'ينقل الفواتير التي تجاوزت تاريخ استحقاقها إلى حالة «متأخّرة».',
                'type' => 'maintenance',
                'suggested_schedule' => 'daily',
            ],
            // ---------- العملاء والتحصيل ----------
            'clients:credit-limit-alerts' => [
                'label' => 'تنبيه تجاوز حدّ الائتمان',
                'description' => 'ينبّه على العملاء الذين تجاوزت أرصدتهم حدّ الائتمان المسموح.',
                'type' => 'notification',
                'suggested_schedule' => 'daily',
            ],
            'clients:inactivity-alerts' => [
                'label' => 'تنبيه العملاء الخاملين',
                'description' => 'يرصد العملاء بلا حركة منذ مدّة محدَّدة ويُشعِر مندوبيهم.',
                'type' => 'notification',
                'suggested_schedule' => 'weekly',
            ],
            'clients:aging-report' => [
                'label' => 'تقرير أعمار الديون',
                'description' => 'يبني تقرير أعمار ذمم العملاء ويرسله للإدارة.',
                'type' => 'report',
                'suggested_schedule' => 'monthly',
            ],
            // ---------- المخزون ----------
            'inventory:low-stock-alerts' => [
                'label' => 'تنبيه حدّ إعادة الطلب',
                'description' => 'ينبّه على الأصناف التي نزلت تحت حدّ إعادة الطلب في كل مستودع.',
                'type' => 'notification',
                'suggested_schedule' => 'daily',
            ],
            'inventory:expiry-alerts' => [
                'label' => 'تنبيه الأصناف قاربت انتهاء صلاحيتها',
                'description' => 'يرصد الدفعات التي اقترب تاريخ انتهائها ضمن مدّة الإنذار.',
                'type' => 'notification',
                'suggested_schedule' => 'daily',
            ],
            'inventory:recalculate-costs' => [
                'label' => 'إعادة احتساب متوسّط التكلفة',
                'description' => 'يعيد حساب متوسّط تكلفة الأصناف بعد حركات اليوم.',
                'type' => 'maintenance',
                'suggested_schedule' => 'daily',
            ],

            // ---------- المشتريات والموردون ----------
            'purchases:reorder-suggestions' => [
                'label' => 'اقتراح أوامر الشراء',
                'description' => 'يبني اقتراحات إعادة الطلب من الاستهلاك وحدود المخزون.',
                'type' => 'maintenance',
                'suggested_schedule' => 'weekly',
            ],

            // ---------- المحاسبة ----------
            'accounting:post-recurring-entries' => [
                'label' => 'ترحيل القيود المتكرّرة',
                'description' => 'يرحّل القيود الدورية المعرَّفة (إيجارات، إهلاكات، مخصّصات).',
                'type' => 'maintenance',
                'suggested_schedule' => 'daily',
            ],
            'checks:due-alerts' => [
                'label' => 'تنبيه الشيكات المستحقّة',
                'description' => 'ينبّه على الشيكات الواردة والصادرة التي يحلّ تاريخها.',
                'type' => 'notification',
                'suggested_schedule' => 'daily',
            ],
            'accounting:close-period' => [
                'label' => 'إقفال الفترة المحاسبية',
                'description' => 'يقفل الشهر المنتهي ويمنع القيد عليه بعد المراجعة.',
                'type' => 'maintenance',
                'suggested_schedule' => 'monthly',
            ],

            // ---------- الموارد البشرية ----------
            'hr:accrue-leave-balances' => [
                'label' => 'استحقاق أرصدة الإجازات',
                'description' => 'يضيف استحقاق الإجازة الشهري لكل موظف على رأس العمل.',
                'type' => 'maintenance',
                'suggested_schedule' => 'monthly',
            ],

            // ---------- الاشتراكات والعضويات والحجوزات ----------
            'subscriptions:renew-due' => [
                'label' => 'تجديد الاشتراكات المستحقّة',
                'description' => 'يجدّد الاشتراكات التي حان موعدها ويصدر فواتيرها.',
                'type' => 'maintenance',
                'suggested_schedule' => 'daily',
            ],
            'memberships:expire' => [
                'label' => 'إنهاء العضويات المنتهية',
                'description' => 'ينهي العضويات التي انقضت مدّتها ويحدّث مزاياها.',
                'type' => 'maintenance',
                'suggested_schedule' => 'daily',
            ],
            'rentals:overdue-alerts' => [
                'label' => 'تنبيه تأخّر الإيجارات',
                'description' => 'يرصد عقود التأجير المتأخّرة في الإرجاع أو السداد.',
                'type' => 'notification',
                'suggested_schedule' => 'daily',
            ],

            // ---------- نقاط البيع والمتجر ----------
            'pos:auto-close-sessions' => [
                'label' => 'إغلاق جلسات الكاشير المفتوحة',
                'description' => 'يقفل جلسات نقاط البيع التي بقيت مفتوحة بعد نهاية اليوم.',
                'type' => 'maintenance',
                'suggested_schedule' => 'daily',
            ],
            'store:abandoned-cart-reminders' => [
                'label' => 'تذكير السلال المتروكة',
                'description' => 'يذكّر زبائن المتجر بالسلال التي تُركت دون إتمام الطلب.',
                'type' => 'notification',
                'suggested_schedule' => 'daily',
            ],
            'store:sync-inventory' => [
                'label' => 'مزامنة مخزون المتجر',
                'description' => 'يزامن كميات المتجر الإلكتروني مع المخزون الفعلي.',
                'type' => 'maintenance',
                'suggested_schedule' => 'hourly',
            ],

            // ---------- الولاء والنقاط ----------
            'points:expire-due' => [
                'label' => 'إسقاط النقاط المنتهية',
                'description' => 'يسقط نقاط الولاء التي انتهت صلاحيتها حسب إعدادات الانتهاء.',
                'type' => 'maintenance',
                'suggested_schedule' => 'daily',
            ],

            // ---------- النظام ----------
            'activity-logs:prune' => [
                'label' => 'تنظيف سجلّ النشاط',
                'description' => 'يحذف سجلّات النشاط الأقدم من مدّة الاحتفاظ المضبوطة.',
                'type' => 'cleanup',
                'suggested_schedule' => 'weekly',
            ],
            'system:health-check' => [
                'label' => 'فحص صحّة النظام',
                'description' => 'يفحص القرص والطابور والاتصال بالقاعدة ويطلق تنبيهاً عند الخلل.',
                'type' => 'maintenance',
                'suggested_schedule' => 'hourly',
            ],
        ];

        // الخصائص المشتركة تُضاف هنا مرّة واحدة: تكرارها في كل صفّ يغري
        // بنسيان `available` في صفّ جديد فيصير قابلاً للتشغيل وهو غير مكتوب
        foreach ($plan as $handler => $meta) {
            $plan[$handler] = $meta + ['options' => [], 'available' => false];
        }

        return $plan;
    }

    /**
     * أصناف الـ Job المسموح جدولتها.
     *
     * مستبعَد منها ما يُطلَق ضمن سياق آخر ولا يعمل وحده — مثل
     * `DeliverNotificationChannels` الذي يحتاج معرّف إشعار قائم.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function jobs(): array
    {
        return [
            ProcessAutoDepartures::class => [
                'label' => 'مغادرة الزوار تلقائياً',
                'description' => 'ينهي جلسات الزوار الذين تجاوزوا المدّة دون تسجيل مغادرة.',
                'type' => 'job',
                'suggested_schedule' => 'every_minute',
            ],
            EvaluateCustomerTargetsJob::class => [
                'label' => 'تقييم أهداف العملاء',
                'description' => 'يعيد احتساب تحقيق أهداف العملاء.',
                'type' => 'job',
                'suggested_schedule' => 'daily',
            ],
        ];
    }

    public static function isAllowedCommand(?string $name): bool
    {
        return $name !== null && array_key_exists($name, static::commands());
    }

    public static function isAllowedJob(?string $class): bool
    {
        return $class !== null && array_key_exists($class, static::jobs());
    }

    /** هل هذا المُعالِج مسموح، أمراً كان أو Job؟ */
    public static function isAllowed(?string $handler): bool
    {
        return static::isAllowedCommand($handler) || static::isAllowedJob($handler);
    }

    /**
     * هل المُعالِج مكتوب فعلاً؟
     *
     * المهام المخطَّطة مسجَّلة لتظهر في الشاشة كخطّة، وأمرها غير موجود —
     * فتشغيلها يجب أن يُرفَض بوضوح لا أن يُترك ليفشل بخطأ غامض.
     */
    public static function isAvailable(?string $handler): bool
    {
        return (static::find($handler)['available'] ?? true) === true;
    }

    /** المُعالِجات المخطَّطة وحدها — قائمة ما ينقص تنفيذه. */
    public static function plannedHandlers(): array
    {
        return array_keys(array_filter(
            static::commands(),
            fn ($meta) => ($meta['available'] ?? true) !== true
        ));
    }

    /** هل هذا المُعالِج مخفيّ؟ يُنفَّذ ولا يُختار. */
    public static function isHidden(?string $handler): bool
    {
        return (static::find($handler)['hidden'] ?? false) === true;
    }

    /**
     * المُعالِجات التي يجوز للمسؤول إنشاء مهمّة عليها.
     *
     * القائمة البيضاء ناقصةً المخفيّ. الفرق مقصود: `isAllowed()` تحكم
     * **التنفيذ** فتضمّ المخفيّ، وهذه تحكم **الإنشاء** فتستثنيه — وإلا
     * أمكن بطلب مُصاغ يدوياً إنشاء نسخة ثانية من مزامنة الاشتراك تُضاعف
     * النداءات على لوحة فوترة وتستهلك حدّ الطلبات، أو تُعدَّل من شاشةٍ لا
     * حماية فيها.
     *
     * @return array<int, string>
     */
    public static function selectableHandlers(): array
    {
        return array_values(array_filter(
            array_merge(array_keys(static::commands()), array_keys(static::jobs())),
            fn (string $handler) => ! static::isHidden($handler)
        ));
    }

    /** @return array<string, mixed>|null */
    public static function find(?string $handler): ?array
    {
        return static::commands()[$handler] ?? static::jobs()[$handler] ?? null;
    }

    public static function labelFor(?string $handler): string
    {
        return static::find($handler)['label'] ?? (string) $handler;
    }

    /**
     * أسماء الخيارات المسموح تمريرها لهذا المُعالِج. الـ Jobs بلا خيارات.
     *
     * @return array<int, string>
     */
    public static function allowedOptions(?string $handler): array
    {
        return static::commands()[$handler]['options'] ?? [];
    }

    /**
     * كل المُعالِجات مجموعةً بالنوع — هكذا تُبنى قائمة الاختيار في الواجهة.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (static::commands() as $handler => $meta) {
            // المخفيّ يبقى في القائمة البيضاء (فيُنفَّذ) ويغيب عن الاختيار.
            //
            // التفريق ضروري: `isAllowed()` تقرأ `commands()` فلو حُذف المدخل
            // منها لرُفض تنفيذ المهمة أصلاً. والإخفاء هنا يمنع إنشاء نسخة
            // ثانية من مهمّة محميّة لا أكثر
            if ($meta['hidden'] ?? false) {
                continue;
            }

            $grouped[$meta['type']][$handler] = $meta + ['handler_kind' => 'command'];
        }

        foreach (static::jobs() as $handler => $meta) {
            if ($meta['hidden'] ?? false) {
                continue;
            }

            $grouped[$meta['type']][$handler] = $meta + ['handler_kind' => 'job'];
        }

        return $grouped;
    }

    /**
     * تنقية المعاملات: يُحتفَظ بالخيارات المسموحة فقط، والقيم قياسية.
     *
     * تُستدعى عند الحفظ وعند التنفيذ معاً — صفّ كُتب قبل تضييق القائمة
     * البيضاء يجب ألا يُنفَّذ بخيار لم يعد مسموحاً.
     *
     * @param  array<string, mixed>|null  $parameters
     * @return array<string, mixed>
     */
    public static function sanitizeParameters(?string $handler, ?array $parameters): array
    {
        if (empty($parameters)) {
            return [];
        }

        $allowed = static::allowedOptions($handler);
        $clean = [];

        foreach ($parameters as $name => $value) {
            $name = ltrim((string) $name, '-');

            if (! in_array($name, $allowed, true)) {
                continue;
            }

            // المصفوفات والكائنات لا تمرّ: الخيارات المسموحة كلّها قياسية،
            // وقبولها يفتح باباً لا داعي له
            if (is_array($value) || is_object($value)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            // القيم المنطقية تُمرَّر كأعلام بلا قيمة (مثل `--dry-run`)
            $clean['--'.$name] = is_bool($value) ? $value : (string) $value;
        }

        return $clean;
    }
}
