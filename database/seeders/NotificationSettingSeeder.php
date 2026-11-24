<?php

namespace Database\Seeders;

use App\Models\NotificationSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * كتالوج أنواع الإشعارات.
 *
 * القائمة ليست افتراضاً: كل مفتاح فيها مستخرَج إمّا من نصّ صريح في الكود
 * عند `notifications::create()`، أو من عمود `type` في البيانات القائمة.
 * ما لم يظهر في أيٍّ منهما ليس هنا.
 *
 * السيدر لا يكتب فوق ما عدّله المسؤول: الصفوف الموجودة تُحدَّث تسميتها
 * وفئتها فقط، أمّا التفعيل والقنوات والمستلمون فتبقى كما ضبطها.
 */
class NotificationSettingSeeder extends Seeder
{
    /**
     * المفتاح => [التسمية، الوصف، الفئة]
     */
    protected const CATALOG = [
        // ============ المبيعات والفواتير ============
        'invoice' => ['فاتورة جديدة', 'عند إصدار فاتورة مبيعات', 'sales'],
        'invoice_payment' => ['دفعة على فاتورة', 'عند تسجيل دفعة على فاتورة مبيعات', 'sales'],
        'invoice_payment_cancelled' => ['إلغاء دفعة فاتورة', 'عند التراجع عن دفعة مسجّلة', 'sales'],
        'invoice_payment_summary' => ['ملخّص دفعات الفاتورة', 'تجميع دفعات فاتورة واحدة في إشعار', 'sales'],
        'Receipt' => ['سند قبض', 'عند إنشاء سند قبض', 'sales'],
        'receipt_cancelled' => ['إلغاء سند قبض', 'عند إلغاء سند قبض', 'sales'],
        'Payment' => ['سند صرف', 'عند إنشاء سند صرف', 'sales'],
        'payment_create' => ['تسجيل دفعة', 'عند تسجيل دفعة مالية', 'sales'],
        'payment_update' => ['تعديل دفعة', 'عند تعديل دفعة مسجّلة', 'sales'],
        'payment_cancellation' => ['إلغاء دفعة', 'عند إلغاء دفعة مالية', 'sales'],
        'bulk_payment' => ['دفعة مجمّعة', 'عند تسديد عدّة فواتير دفعة واحدة', 'sales'],
        'collection' => ['تحصيل', 'عند تسجيل عملية تحصيل', 'sales'],
        'sales' => ['إشعار مبيعات', 'إشعارات عامة من قسم المبيعات', 'sales'],
        'invoice_transfer' => ['طلب تسليم فاتورة', 'عند طلب تحويل فاتورة إلى موظف آخر', 'sales'],
        'invoice_transfer_accepted' => ['قبول تسليم فاتورة', 'عند قبول طلب التحويل', 'sales'],
        'invoice_transfer_rejected' => ['رفض تسليم فاتورة', 'عند رفض طلب التحويل', 'sales'],
        'invoice_transfer_cancelled' => ['إلغاء تسليم فاتورة', 'عند إلغاء طلب التحويل', 'sales'],

        // ── الأقساط ──
        // الثلاثةُ من مهامّ مجدولة، فلا «مستلم» بشريّ وراءها إلا مُنشئ
        // الاتفاقية. ومن أراد إبلاغ العميل نفسِه يُضيف `client` إلى
        // «من يستلم» في الشاشة، وتتولّاه `NotificationDispatcher`
        'installment_due' => ['استحقاق قسط', 'عند حلول موعد قسطٍ أو قربه — من مهمّة «تنبيه استحقاق الأقساط»', 'sales'],
        'installment_overdue' => ['تأخّر قسط', 'عن كلّ قسطٍ فات استحقاقه ولم يُسدَّد — يتكرّر يومياً ما بقي متأخّراً', 'sales'],
        'installment_late_fee' => ['غرامة تأخير قسط', 'عند تحميل غرامة تأخير وترحيلها إلى حساب الغرامات', 'sales'],

        // ── شروط الدفع ──
        // نظيرُ `purchase_due_payment` في المبيعات. المستلمُ الافتراضيّ مسؤولُ
        // المبيعات على الفاتورة؛ ومن أراد إبلاغ العميل يُضيف `client` إلى
        // «من يستلم» في الشاشة، وتتولّاه `NotificationDispatcher`
        'sales_due_payment' => ['مستحقّات العملاء', 'تنبيه المهمّة المجدولة بفواتير المبيعات الآجلة التي حلّ أجلُها أو قارب', 'sales'],

        // المهمّة المجدولة لم تُصدر نسخةً من اشتراكٍ دوريّ — نقصُ مخزون، حدٌّ ائتماني…
        'sales_periodic_failed' => ['تعذّر إصدار فاتورة دورية', 'عند ردّ نسخةٍ من اشتراكٍ دوريّ: نقصُ مخزون أو حدٌّ ائتماني أو فترةٌ مقفلة — مرّةً لكلّ سببٍ جديد', 'sales'],

        // ============ المشتريات والموردين ============
        'purchase_creation' => ['أمر شراء جديد', 'عند إنشاء أمر شراء', 'purchases'],
        'purchase_request' => ['طلب شراء', 'عند تقديم طلب شراء', 'purchases'],
        'purchase_update' => ['تعديل أمر شراء', 'عند تعديل أمر شراء قائم', 'purchases'],
        'purchase_invoice' => ['فاتورة مشتريات', 'عند إصدار فاتورة مشتريات', 'purchases'],
        'purchase_conversion' => ['تحويل أمر شراء', 'عند تحويل الأمر إلى فاتورة', 'purchases'],
        'purchase_return' => ['مرتجع مشتريات', 'عند تسجيل مرتجع للمورّد', 'purchases'],
        'purchase_return_creation' => ['إنشاء مرتجع مشتريات', 'عند إنشاء مستند المرتجع', 'purchases'],
        'supplier_payment_cancelled' => ['إلغاء دفعة مورّد', 'عند إلغاء دفعة لمورّد', 'purchases'],
        'city_notice_creation' => ['إشعار مدينة', 'عند إنشاء إشعار مدينة', 'purchases'],
        'purchase_due_payment' => ['مستحقّات الموردين', 'تنبيه المهمّة المجدولة بفواتير الموردين غير المسدَّدة', 'purchases'],

        // ============ العملاء والزيارات ============
        'client_note' => ['ملاحظة على عميل', 'عند إضافة ملاحظة في ملف عميل', 'clients'],
        'client_popup' => ['إشعار منبثق للعميل', 'رسالة تظهر للعميل عند دخوله', 'clients'],
        'client_status_request' => ['طلب تغيير حالة عميل', 'عند طلب الموظف تغيير حالة عميل', 'clients'],
        'client_status_approved' => ['قبول تغيير حالة عميل', 'عند موافقة المدير على الطلب', 'clients'],
        'client_status_rejected' => ['رفض تغيير حالة عميل', 'عند رفض المدير للطلب', 'clients'],
        'visit' => ['زيارة عميل', 'عند تسجيل وصول أو انصراف من عميل', 'clients'],
        'visit_update' => ['تعديل زيارة', 'عند تعديل بيانات زيارة', 'clients'],
        'visit_notification' => ['تنبيه زيارة', 'تنبيهات متعلّقة بجدول الزيارات', 'clients'],
        'target_evaluation' => ['تقييم مستهدف العميل', 'ناتج المهمة اليومية لتقييم المستهدفات', 'clients'],
        'مراجعة عميل' => ['مراجعة عميل', 'نوع قديم بتسمية عربية — موجود في البيانات', 'clients'],

        // ── الحجوزات والمواعيد ──
        // لم تكن في الكتالوج، فكانت شاشة «إعدادات الإشعارات» لا تعرضها وتبقى
        // كلُّها على القناة الداخلية وحدها مهما ضبط المسؤول. إشعارات الحجز
        // نفسه مُرسَلة إلى العميل (`client_id` بلا `receiver_id`)، فمستلمُها
        // الافتراضي «العميل نفسه»؛ وما يُنشئه العميل من بوابته أو يخصّ الموظف
        // يذهب إلى «المستلم المحدَّد» كبقيّة النظام. مفاتيح التفعيل نفسها
        // (`notify_*`) في «إعدادات الحجوزات»؛ هنا القناة والمستلم فقط.
        'reservation_created' => ['إنشاء حجز', 'عند تسجيل حجز جديد للعميل — يخضع لمفتاح «عند إنشاء الحجز» في إعدادات الحجوزات', 'clients',
            ['recipients' => ['client']]],
        'reservation_confirmed' => ['تأكيد حجز', 'عند تأكيد الحجز', 'clients', ['recipients' => ['client']]],
        'reservation_cancelled' => ['إلغاء حجز', 'عند إلغاء الحجز أو رفضه', 'clients', ['recipients' => ['client']]],
        'reservation_rescheduled' => ['تغيير موعد حجز', 'عند نقل الحجز إلى موعد آخر', 'clients', ['recipients' => ['client']]],
        'reservation_completed' => ['اكتمال حجز', 'عند اكتمال الخدمة المحجوزة', 'clients', ['recipients' => ['client']]],
        'reservation_no_show' => ['عدم حضور لحجز', 'عند تسجيل عدم حضور العميل', 'clients', ['recipients' => ['client']]],
        'reservation_reminder' => ['تذكير بموعد حجز', 'قبل الموعد بالمهل المضبوطة في إعدادات الحجوزات — من مهمّة «تذكير الحجوزات القادمة»', 'clients',
            ['recipients' => ['client']]],
        'reservation_reminder_employee' => ['تذكير الموظّف بحجزٍ عليه', 'قبل الموعد بالمهل نفسها — للموظّف المعيَّن على الحجز، خلف مفتاح «تذكير الموظّف المعيَّن» في إعدادات الحجوزات', 'clients'],
        'reservation_portal_request' => ['طلب حجز من بوابة العميل', 'عند حجز العميل موعداً بنفسه من بوابته — للموظف المسند إليه', 'clients'],
        'client_appointment_reminder' => ['تذكير بموعد عميل', 'قبل موعد العميل بالمدّة المضبوطة في إعدادات المواعيد — من مهمّة «تذكير مواعيد العملاء»', 'clients'],
        'client_appointment_assigned' => ['إسناد موعد عميل', 'عند إضافة موظف إلى موعد عميل', 'clients'],
        'client_appointment_shared' => ['مشاركة موعد مع العميل', 'عند مشاركة الموعد مع العميل في بوابته', 'clients', ['recipients' => ['client']]],
        'client_payment_notice' => ['إشعار دفع من بوابة العميل', 'عند إبلاغ العميل عن دفعة من بوابته — للموظف المسند إليه', 'clients'],
        'client_payment_notice_reviewed' => ['البتّ في إشعار دفع', 'عند اعتماد إشعار الدفع أو رفضه — للعميل', 'clients', ['recipients' => ['client']]],
        'client_visit_request' => ['طلب زيارة ميدانية', 'عند طلب العميل زيارةً من بوابته — للموظف المسند إليه', 'clients'],
        'client_visit_request_reviewed' => ['البتّ في طلب زيارة', 'عند قبول طلب الزيارة أو رفضه — للعميل', 'clients', ['recipients' => ['client']]],

        // ============ الموارد البشرية ============
        'advance_request' => ['طلب سلفة', 'عند تقديم موظف طلب سلفة', 'hr'],
        'advance_approved' => ['قبول سلفة', 'عند الموافقة على طلب السلفة', 'hr'],
        'advance_rejected' => ['رفض سلفة', 'عند رفض طلب السلفة', 'hr'],
        'advance_settled' => ['تسوية سلفة', 'عند تسوية سلفة موظف', 'hr'],
        'deduction' => ['خصم على موظف', 'عند تسجيل خصم', 'hr'],
        'salary_disbursement' => ['صرف راتب', 'عند صرف رواتب', 'hr'],
        'contract_expiration' => ['قرب انتهاء عقد', 'تنبيه الموظف بقرب انتهاء عقده', 'hr'],
        'contract_expiration_admin' => ['قرب انتهاء عقد — للإدارة', 'تنبيه الإدارة بالعقود المنتهية', 'hr'],
        'expiry' => ['انتهاء صلاحية', 'تنبيهات انتهاء الصلاحية', 'hr'],

        // ============ المخزون والتصنيع ============
        'manufacturing_order' => ['أمر تصنيع', 'عند إنشاء أمر تصنيع', 'stock'],
        'low_stock' => ['نفاد مخزون', 'عند هبوط الكمية دون الحدّ الأدنى', 'stock'],
        'Products' => ['إشعار أصناف', 'إشعارات متعلّقة بالأصناف وأذون المخزن', 'stock'],

        // ============ إدارة العقارات ============
        // المفاتيح ثوابتُ `Modules\RentalManagement\Support\RealEstateNotifications`
        // — تُكتب هنا نصّاً لأنّ السيدر يعمل ولو عُطّلت الوحدة. الثلاثةُ الأولى من
        // المهمّة المجدولة `realestate:alerts`: إشعارٌ مجمَّعٌ واحد لكلّ عقدٍ
        // ولكلّ مستلم، يذهب إلى منشئ العقد ومن يرى فرعَه من حَمَلة الصلاحية.
        'realestate_contract_expiring' => ['قرب انتهاء عقد إيجار', 'من المهمّة المجدولة «تنبيهات العقود والدفعات» قبل انتهاء العقد بالمدّة المضبوطة في إعدادات العقارات', 'realestate'],
        'realestate_payment_due' => ['استحقاق أقساط إيجار', 'إشعارٌ واحد لكلّ عقد بأقساطه التي حلّت أو قاربت — بالمدّة المضبوطة في إعدادات العقارات', 'realestate'],
        'realestate_payment_overdue' => ['تأخّر أقساط إيجار', 'إشعارٌ واحد لكلّ عقد بعدد أقساطه المتأخّرة وإجماليّها بعد مهلة السماح', 'realestate'],
        'realestate_maintenance_assigned' => ['إسناد طلب صيانة عقارية', 'عند إسناد طلب صيانة إلى مسؤول', 'realestate'],
        'realestate_maintenance_closed' => ['إغلاق طلب صيانة عقارية', 'عند اكتمال طلب الصيانة أو إلغائه — يصل إلى من فتحه', 'realestate'],
        'realestate_task_assigned' => ['مهمة عقارية جديدة', 'عند إسناد مهمة أو متابعة عقارية إلى مستخدم', 'realestate'],
        'realestate_task_due' => ['مهمة عقارية فات موعدها', 'مرّةً واحدة عن كلّ مهمة مفتوحة تجاوزت موعدها — للمكلَّف بها', 'realestate'],

        // ============ النظام والتنبيهات ============
        'general' => ['إشعار عام', 'إشعار يدوي يرسله المستخدم', 'system'],
        'notification' => ['إشعار', 'نوع افتراضي حين لا يُحدَّد نوع', 'system'],
        'automatic_notification' => ['إشعار تلقائي', 'إشعار ينشئه النظام بلا تدخّل', 'system'],
        'notes' => ['ملاحظات', 'ملاحظات عامة', 'system'],
        'reply' => ['ردّ على إشعار', 'عند ردّ مستخدم على إشعار وصله', 'system'],
        'alert' => ['تنبيه', 'تنبيه يستدعي انتباهاً', 'system'],
        'urgent' => ['عاجل', 'إشعار عاجل', 'system'],
        'reminder' => ['تذكير', 'تذكير بموعد أو مهمّة', 'system'],
        'warning' => ['تحذير', 'تحذير من إجراء أو حالة', 'system'],
        'error' => ['خطأ', 'إبلاغ بخطأ وقع في عملية', 'system'],
        'success' => ['نجاح عملية', 'تأكيد إتمام عملية', 'system'],
        'status_change' => ['تغيير حالة', 'عند تغيّر حالة مستند أو سجلّ', 'system'],
        'finance_log' => ['سجلّ مالي', 'قيود ومتابعات مالية', 'system'],
        'workspace' => ['مساحة عمل', 'إشعارات مدير المهام', 'system'],
        // قناة الرسائل موقوفة هنا عمداً: التنبيه على تعطّل بوابة الرسائل
        // لا يُرسل عبر بوابة الرسائل المتعطّلة
        'sms_gateway_failing' => ['تعطّل بوابة الرسائل', 'عند تتابع فشل إرسال الرسائل النصية بعدد يتجاوز الحدّ', 'system',
            ['recipients' => ['owners', 'managers'], 'channels' => ['database']]],

        // ============ المهام المجدولة والنسخ الاحتياطي ============
        // العنصر الرابع (اختياري) قيم افتراضية عند الإنشاء أول مرّة فقط.
        // هذه التنبيهات تخصّ مسؤولي النظام لا «المستلم» — والمستلم في
        // تنبيه آليّ لا معنى له إذ لا مستخدم وراءه
        'cron_job_failed' => ['فشل مهمة مجدولة', 'عند فشل تنفيذ مهمة من مهام Cron', 'system',
            ['recipients' => ['owners', 'managers']]],
        'cron_job_stuck' => ['مهمة مجدولة متعثّرة', 'عند فشل المهمة نفسها ثلاث مرّات متتالية', 'system',
            ['recipients' => ['owners', 'managers']]],
        'cron_job_succeeded' => ['نجاح مهمة مجدولة', 'عند نجاح مهمة فُعِّل لها «إشعار عند النجاح»', 'system',
            // موقوف ابتداءً: مهمة تعمل كل دقيقة تنتج 1440 إشعاراً يومياً
            ['recipients' => ['owners'], 'is_enabled' => false]],
        'scheduler_down' => ['توقّف المجدول', 'عند انقطاع نبض المجدول لفترة تتجاوز الحدّ', 'system',
            ['recipients' => ['owners', 'managers']]],
        'target_settlement_run' => ['نتيجة التسوية المجدولة للأهداف', 'عند كلّ تشغيلٍ مستحقّ لتسوية أهداف المبيعات/التحصيلات: ما صُرف، أو لا مستحقّ، أو سبب التخطّي', 'system',
            ['recipients' => ['owners', 'managers']]],
        'backup_completed' => ['اكتمال نسخة احتياطية', 'عند نجاح إنشاء نسخة احتياطية', 'system',
            ['recipients' => ['owners']]],
        'backup_failed' => ['فشل نسخة احتياطية', 'عند فشل إنشاء نسخة احتياطية', 'system',
            ['recipients' => ['owners', 'managers']]],
        'backup_cleanup_failed' => ['فشل تنظيف النسخ القديمة', 'عند تعذّر حذف النسخ التي تجاوزت سياسة الاحتفاظ', 'system',
            ['recipients' => ['owners']]],
        'backup_storage_full' => ['امتلاء مساحة النسخ الاحتياطية', 'عند تجاوز حجم النسخ الحدّ المضبوط', 'system',
            ['recipients' => ['owners', 'managers']]],
        'backup_restore_completed' => ['اكتمال استرجاع نسخة', 'عند نجاح استرجاع قاعدة البيانات من نسخة', 'system',
            ['recipients' => ['owners', 'managers']]],
        'backup_restore_failed' => ['فشل استرجاع نسخة', 'عند فشل استرجاع قاعدة البيانات', 'system',
            ['recipients' => ['owners', 'managers']]],

        // ============ الاشتراك عند لوحة فوترة ============
        // مصدرها المزامنة الدورية لا نداءٌ لحظي، والمستلم مسؤول لا «receiver»
        'subscription_usage_threshold' => ['بلوغ حدّ استخدام في الاشتراك', 'عند بلوغ ميزة ٨٠٪ من حدّها في الباقة', 'system',
            ['recipients' => ['owners', 'managers']]],
        'subscription_usage_exhausted' => ['استنفاد حدّ استخدام في الاشتراك', 'عند استنفاد حدّ ميزة بالكامل', 'system',
            ['recipients' => ['owners', 'managers']]],
        'subscription_status' => ['حالة الاشتراك', 'قرب الانتهاء أو الانتهاء أو الإيقاف أو تعثّر المزامنة', 'system',
            ['recipients' => ['owners', 'managers']]],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('notification_settings')) {
            $this->command?->error('جدول notification_settings غير موجود — شغّل php artisan migrate أولاً.');

            return;
        }

        $order = 0;

        foreach (self::CATALOG as $key => $entry) {
            [$label, $description, $category] = $entry;

            $this->upsert($key, $label, $description, $category, $order += 10, false, $entry[3] ?? []);
        }

        $this->discoverUnknown($order);

        NotificationSetting::flush();

        $this->command?->info('كتالوج الإشعارات: '.NotificationSetting::count().' نوعاً.');
    }

    /**
     * أنواع في البيانات لم يعرفها الكتالوج — تُسجَّل تحت «غير مصنّفة»
     * كي تظهر للمسؤول بدل أن تبقى خارج أي إعداد.
     */
    protected function discoverUnknown(int $order): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $known = array_keys(self::CATALOG);

        $unknown = DB::table('notifications')
            ->select('type', DB::raw('COUNT(*) as total'))
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->whereNotIn('type', $known)
            ->groupBy('type')
            ->pluck('total', 'type');

        foreach ($unknown as $key => $total) {
            $this->upsert(
                (string) $key,
                (string) $key,
                'نوع اكتُشف في البيانات ('.number_format($total).' إشعاراً) ولم يُصنَّف بعد.',
                'other',
                $order += 10,
                true
            );
        }
    }

    /**
     * @param  array<string, mixed>  $defaults  قيم تُطبَّق عند الإنشاء أول مرّة فقط
     */
    protected function upsert(string $key, string $label, string $description, string $category, int $order, bool $discovered, array $defaults = []): void
    {
        $existing = NotificationSetting::where('key', $key)->first();

        if ($existing) {
            // التسمية والوصف والفئة من الكتالوج؛ خيارات المسؤول لا تُمَسّ
            $existing->update([
                'label' => $label,
                'description' => $description,
                'category' => $category,
                'sort_order' => $order,
                'is_discovered' => $discovered,
            ]);

            return;
        }

        NotificationSetting::create(array_merge([
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'category' => $category,
            // كل شيء مفعّل ابتداءً: الهدف إتاحة التحكّم لا تغيير السلوك
            'is_enabled' => true,
            'channels' => ['database'],
            'recipients' => ['receiver'],
            'sort_order' => $order,
            'is_discovered' => $discovered,
        ], $defaults));
    }
}
