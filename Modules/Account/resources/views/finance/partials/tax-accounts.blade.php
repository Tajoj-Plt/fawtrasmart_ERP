{{--
    حسابا الضريبة — من «الحسابات المحاسبية» في الإعدادات لا من ثوابت الشيفرة.

    كانت شاشة سند الصرف تكتب المعرّفين عبر ثابت `SystemAccountIds` مباشرةً،
    وشاشة سند القبض لا تعرضهما إطلاقاً — فمبلغ الضريبة يُحفظ في السجلّ ولا
    يُرحَّل إلى أي حساب. هذا المكوّن يقرأ المفتاحين `vat_payable` و`vat_paid`
    من `AccountingSettings`، فما يضبطه المحاسب في الإعدادات هو ما يُرحَّل
    إليه فعلاً — والثابت احتياطٌ قبل الزرع فقط.

    الوسائط:
      index     1 أو 2 — رقم الضريبة، يبني أسماء الحقول ومعرّفاتها
      selected  المعرّف المحفوظ (في شاشة التعديل)
      preferred 'payable' للمقبوضات (ضريبة محصَّلة) أو 'paid' للمصروفات
--}}

@php
    $vatPayableId = \App\Support\AccountingSettings::id('vat_payable', \App\Constants\SystemAccountIds::VAT_PAYABLE);
    $vatPaidId = \App\Support\AccountingSettings::id('vat_paid', \App\Constants\SystemAccountIds::VAT_PAID);

    $index = $index ?? 1;
    $selected = $selected ?? null;
    $preferred = $preferred ?? 'payable';

    // الافتراضي الأنسب للمستند: المحصَّلة في القبض، والمدفوعة في الصرف
    $default = $preferred === 'paid' ? $vatPaidId : $vatPayableId;
    $current = $selected ?: $default;

    $field = 'tax' . $index . '_account_id';
@endphp

<label for="{{ $field }}" class="mt-2">حساب الضريبة</label>

<select id="{{ $field }}" name="{{ $field }}" class="form-control">
    <option value="">بلا ترحيل</option>

    @if ($vatPayableId)
        <option value="{{ $vatPayableId }}" @selected($current == $vatPayableId)>
            ضريبة القيمة المضافة المحصَّلة
        </option>
    @endif

    @if ($vatPaidId)
        <option value="{{ $vatPaidId }}" @selected($current == $vatPaidId)>
            ضريبة القيمة المضافة المدفوعة
        </option>
    @endif
</select>

@if (! $vatPayableId && ! $vatPaidId)
    <small class="stk-help text-danger">
        <i class="feather icon-alert-triangle"></i>
        لم تُربط حسابات الضريبة بعدُ — اربطها من «الإعدادات ← الحسابات المحاسبية».
    </small>
@else
    <small class="stk-help">الحساب الذي تُرحَّل إليه الضريبة — من «الحسابات المحاسبية».</small>
@endif
