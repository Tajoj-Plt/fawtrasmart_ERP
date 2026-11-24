<?php

namespace Modules\Client\Http\Controllers\Concerns;

use App\Helpers\ClientSettingsHelper;
use App\Models\ClientModuleSetting;
use Illuminate\Support\Carbon;

/**
 * فرضُ ما تقوله شاشة «إعدادات وحدات العملاء» داخل تدفّق العمل.
 *
 * **لماذا سمة لا نداءٌ مباشر للمساعد؟**
 *
 * `ClientSettingsHelper` يقول *ما هي القيمة*؛ وهذه السمة تقول *ما الذي
 * يترتّب عليها*. والفرق هو ما يجعل الإعداد إعداداً لا زينة: مفتاحٌ يُحفَظ
 * ولا يُقرأ في موضع القرار هو مفتاحٌ يظنّ المستخدمُ أنّه فعل شيئاً ولم
 * يفعل. فكلُّ دالّة هنا تُستدعى عند الحفظ وتردّ **رسالة المنع أو `null`**،
 * على نمط `accountingPeriodBlock()` في الوحدة نفسها.
 *
 * **التعطيل قسمٌ لا رابط.** `sectionBlock()` تحرس الأقسام المطفأة في
 * `client_module_settings`: القائمة الجانبية تخفي رابط القسم المطفأ، لكن
 * الرابط المباشر كان يبقى مفتوحاً — والإخفاءُ البصري ليس منعاً.
 *
 * @see \App\Support\ClientSettings تعريف المجموعات وافتراضاتها
 * @see \App\Helpers\ClientSettingsHelper واجهة القراءة
 */
trait EnforcesClientModuleSettings
{
    /**
     * أمُفعَّلٌ قسمٌ فرعيّ من الوحدة؟
     *
     * @param  string  $section  اسم العمود بلا `_enabled`: visits|itinerary|notes…
     */
    protected function sectionEnabled(string $section): bool
    {
        try {
            return (bool) ClientModuleSetting::current()->{$section.'_enabled'};
        } catch (\Throwable $e) {
            // إعداداتٌ غير مهيّأة يجب ألّا تُقفل الوحدة كلَّها
            return true;
        }
    }

    /** رسالة منع القسم المطفأ — أو `null` إن كان مفعَّلاً. */
    protected function sectionBlock(string $section): ?string
    {
        if ($this->sectionEnabled($section)) {
            return null;
        }

        // مفتاحُ المواعيد انتقل إلى «إعدادات الحجوزات» — الرسالةُ تسمّي مكانه الجديد
        if ($section === 'appointments') {
            return __('client_settings.enforcement.appointments_disabled');
        }

        return __('client_settings.enforcement.section_disabled', [
            'section' => __('client_settings.module_settings.fields.'.$section.'_enabled'),
        ]);
    }

    // ═══════════════════════ المواعيد ═══════════════════════

    /**
     * سببُ رفض موعدٍ بهذا التاريخ والوقت — أو `null`.
     *
     * ثلاثة ضوابط بترتيب الأعمّ فالأخصّ: القسم مفعَّل، ثم التاريخ ليس
     * ماضياً (ما لم يُسمح)، ثم الوقت داخل ساعات العمل (ما لم يكن الضابط
     * إرشادياً).
     *
     * @param  string|null  $time  HH:MM — الغياب يعني موعداً بلا وقت محدَّد
     */
    protected function appointmentBlock(Carbon|string|null $date, ?string $time = null): ?string
    {
        if ($block = $this->sectionBlock('appointments')) {
            return $block;
        }

        if (! $date) {
            return null;
        }

        $moment = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        /* «ماضٍ» = قبل اليوم، لا قبل هذه اللحظة: كان `startOfDay()->isPast()`
           فيصدق على اليوم نفسه (منتصف ليله مضى) — وموعدُ اليوم يُرفض بأنّه
           «بتاريخ مضى» بينما قاعدةُ التحقّق `after_or_equal:today` تقبله. */
        if (! ClientSettingsHelper::appointmentsAllowPastDates() && $moment->startOfDay()->lt(Carbon::today())) {
            return __('client_settings.enforcement.appointment_past');
        }

        if (! $time || ! ClientSettingsHelper::appointmentsEnforceWorkingHours()) {
            return null;
        }

        $start = ClientSettingsHelper::appointmentWorkingHoursStart();
        $end = ClientSettingsHelper::appointmentWorkingHoursEnd();

        // مقارنة نصّية على `HH:MM` صحيحةٌ ما دام الطرفان بنفس الصيغة —
        // و`date_format:H:i` في الشاشة يضمنها
        $at = substr($time, 0, 5);

        if ($at < $start || $at > $end) {
            return __('client_settings.enforcement.appointment_outside_hours', [
                'start' => $start,
                'end' => $end,
            ]);
        }

        return null;
    }

    /** مدّة الموعد الافتراضية — لملء النموذج ولحساب النهاية عند الغياب. */
    protected function appointmentDefaultDuration(): int
    {
        return ClientSettingsHelper::appointmentDefaultDurationMinutes();
    }

    // ═══════════════════════ خطوط السير ═══════════════════════

    /**
     * سببُ رفض إضافة عميل إلى خط سيرٍ بلغ سقفه — أو `null`.
     *
     * `null` سقفاً تعني بلا حدّ، لا صفراً: الفرق بينهما هو الفرق بين
     * «بلا تقييد» و«ممنوعٌ تماماً».
     */
    protected function itineraryCapacityBlock(int $currentCount, int $adding = 1): ?string
    {
        $max = ClientSettingsHelper::itineraryMaxClientsPerDay();

        if ($max === null || $currentCount + $adding <= $max) {
            return null;
        }

        return __('client_settings.enforcement.itinerary_full', [
            'max' => $max,
            'current' => $currentCount,
        ]);
    }

    // ═══════════════════════ موظفو العميل ═══════════════════════

    /**
     * سببُ رفض إسناد موظفٍ إضافي لعميل — أو `null`.
     *
     * ضابطان: منعُ التعدّد أصلاً، ثم السقفُ العددي.
     */
    protected function clientEmployeeBlock(int $currentCount, int $adding = 1): ?string
    {
        $after = $currentCount + $adding;

        if (! ClientSettingsHelper::clientEmployeesAllowMultiple() && $after > 1) {
            return __('client_settings.enforcement.employee_single_only');
        }

        $max = ClientSettingsHelper::clientEmployeesMax();

        if ($max !== null && $after > $max) {
            return __('client_settings.enforcement.employee_limit', ['max' => $max]);
        }

        return null;
    }

    // ═══════════════════════ تحويل الفواتير ═══════════════════════

    /**
     * سببُ رفض تحويل فاتورة — أو `null`.
     *
     * @param  bool  $isFullyPaid  أسُدّدت الفاتورة بالكامل؟
     * @param  int  $openTransfers  عدد التحويلات المعلّقة للموظف المستلِم
     */
    protected function invoiceTransferBlock(bool $isFullyPaid, int $openTransfers = 0): ?string
    {
        if ($block = $this->sectionBlock('invoice_transfer')) {
            return $block;
        }

        if ($isFullyPaid && ! ClientSettingsHelper::invoiceTransferAllowPaid()) {
            return __('client_settings.enforcement.transfer_paid_invoice');
        }

        $max = ClientSettingsHelper::invoiceTransferMaxOpenPerEmployee();

        if ($max !== null && $openTransfers >= $max) {
            return __('client_settings.enforcement.transfer_limit', ['max' => $max]);
        }

        return null;
    }

    /** أيُشترَط سببٌ مكتوب في التحويل والرفض؟ — لبناء قاعدة التحقّق. */
    protected function transferReasonRule(): string
    {
        return ClientSettingsHelper::invoiceTransferReasonRequired()
            ? 'required|string|max:1000'
            : 'nullable|string|max:1000';
    }

    // ═══════════════════════ الديون ═══════════════════════

    /**
     * سببُ رفض شطب دينٍ يتجاوز حدَّ الاعتماد — أو `null`.
     *
     * الحدّ `null` يُبقي السلوك السابق: من يملك «إدارة الديون» يشطب أيّ
     * مبلغ. وضبطُه يجعل ما فوقه يحتاج صلاحيةً ثانية.
     */
    protected function debtWriteoffBlock(float $amount): ?string
    {
        $threshold = ClientSettingsHelper::badDebtWriteoffApprovalThreshold();

        if ($threshold === null || $amount <= $threshold) {
            return null;
        }

        return hasPermission('clients_approve_large_debt_writeoff')
            ? null
            : __('client_settings.enforcement.writeoff_needs_approval', [
                'threshold' => \App\Support\CurrencySettings::format($threshold),
            ]);
    }
}
