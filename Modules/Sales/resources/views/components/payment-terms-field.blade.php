{{--
    ══════════════════════════════════════════════════════════════════════
    حقل «شروط الدفع» — المهلةُ بالأيام، وتاريخُ الاستحقاق يُحسب أمام عين
    من يكتبها
    ══════════════════════════════════════════════════════════════════════

    ── لماذا جزءٌ مشترك ──

    ثلاثُ شاشاتٍ تحمل الحقلَ نفسَه (فاتورة المبيعات وعرضُ السعر وأمرُ
    البيع)، والتاريخُ المحسوب يجب أن يوافق ما يحسبه الخادم في
    {@see \App\Support\PaymentTerms}. ونسخُ الحسبة في كل شاشة يعني ثلاثَ
    نسخٍ تتباعد عند أوّل تعديل — وهو بعينه ما وقع في حسّاب الإجماليات قبل
    توحيده في `sales-document-form.js`.

    ── التنبيه ──

    رقمُ الأيام وحدَه لا يقول شيئاً: «٣٠» تحتاج من يقرؤها أن يحسب في رأسه.
    فالتلميحُ يقول التاريخَ صراحةً، ويتحدّث مع كل تغييرٍ في المهلة أو في
    تاريخ الإصدار. ويُلوَّن تحذيراً إن كان الاستحقاقُ المحسوب قد مضى —
    وهي حالةٌ واقعة: فاتورةٌ تُسجَّل اليوم عن بيعٍ وقع قبل شهرين.

    @param string $baseDateId  مُعرّف حقل تاريخ الإصدار الذي تُحسب منه المهلة
    @param string $name        اسم الحقل المُرسَل (افتراضاً `terms`)
    @param mixed  $value       القيمة الحالية
    @param string $colClass    أصناف عمود الشبكة
--}}

@php
    $ptName = $name ?? 'terms';
    $ptId = $ptName;
    $ptBase = $baseDateId ?? 'issue_date';
    $ptValue = $value;
    $ptCol = $colClass ?? 'col-lg-3 col-md-6';
@endphp

<div class="{{ $ptCol }}">
    <div class="sl-field">
        <label for="{{ $ptId }}">{{ __('payment_terms.label') }}</label>

        <div class="input-group">
            <input class="form-control js-payment-terms" type="number" min="0" step="1"
                   id="{{ $ptId }}" name="{{ $ptName }}"
                   data-base-date="{{ $ptBase }}"
                   data-hint="pt-hint-{{ $ptId }}"
                   value="{{ $ptValue }}" placeholder="0">
            <span class="input-group-text">{{ __('payment_terms.days_unit') }}</span>
        </div>

        <span class="sl-hint d-block" id="pt-hint-{{ $ptId }}"
              data-summary="{{ __('payment_terms.summary', ['days' => ':days', 'date' => ':date']) }}"
              data-immediate="{{ __('payment_terms.immediate') }}"
              data-overdue="{{ __('payment_terms.state.due_today') }}">
            {{ __('payment_terms.hint') }}
        </span>
    </div>
</div>

@once
    @push('scripts')
        <script>
            /*
                تاريخُ الاستحقاق = تاريخُ الإصدار + المهلة — نفسُ حسبة
                `PaymentTerms::make()` سطراً بسطر، فلا يفترق ما يراه
                المستخدمُ عمّا يُحفَظ.

                والتاريخُ يُنسَّق بمحلّية الصفحة لا بصيغةٍ مثبَّتة: الشاشةُ
                تتكلّم لغةَ المستخدم النشطة.
            */
            (function () {
                'use strict';

                var locale = document.documentElement.lang || 'ar';

                function refresh(input) {
                    var hint = document.getElementById(input.dataset.hint);
                    if (!hint) return;

                    var days = parseInt(input.value, 10);
                    var base = document.getElementById(input.dataset.baseDate);

                    hint.classList.remove('text-danger', 'text-warning');

                    if (!days || days <= 0) {
                        hint.textContent = hint.dataset.immediate;
                        return;
                    }

                    if (!base || !base.value) {
                        hint.textContent = hint.dataset.summary
                            .replace(':days', days)
                            .replace(':date', '—');
                        return;
                    }

                    var due = new Date(base.value + 'T00:00:00');
                    due.setDate(due.getDate() + days);

                    hint.textContent = hint.dataset.summary
                        .replace(':days', days)
                        .replace(':date', due.toLocaleDateString(locale));

                    var today = new Date();
                    today.setHours(0, 0, 0, 0);

                    // استحقاقٌ مضى قبل أن يُحفظ المستند — يُقال لا يُخفى
                    if (due < today) {
                        hint.classList.add('text-danger');
                    } else if ((due - today) / 86400000 <= 7) {
                        hint.classList.add('text-warning');
                    }
                }

                function bind() {
                    document.querySelectorAll('.js-payment-terms').forEach(function (input) {
                        refresh(input);

                        input.addEventListener('input', function () { refresh(input); });

                        var base = document.getElementById(input.dataset.baseDate);
                        if (base) base.addEventListener('change', function () { refresh(input); });
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bind);
                } else {
                    bind();
                }
            })();
        </script>
    @endpush
@endonce
