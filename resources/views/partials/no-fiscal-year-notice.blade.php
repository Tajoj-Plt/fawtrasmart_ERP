{{--
    تنبيهٌ قبل إنشاء أيّ مستند حين لا سنةَ مالية في النظام.

    **لماذا قبل الإنشاء لا بعده؟**

    المستندُ يُختَم بفترته لحظةَ حفظه (`BelongsToAccountingPeriod`). فإن لم
    يكن ثمّة تقويمٌ حُفظ **بلا نسبة**: لا يظهر في قائمةٍ ولا تقرير ولا إقرار،
    وهو قائمٌ في الدفتر ورصيدُ الحساب يحمله. وإصلاحُ ذلك بعد مئة مستندٍ أشقُّ
    من رسم سنةٍ قبل الأوّل.

    **ولماذا «تخطّي» لا منعٌ؟**

    قد يكون المستخدمُ يجرّب النظام، أو يُدخل رصيداً افتتاحياً قبل أن يستقرّ
    تقويمُه. والمنعُ هنا يوقف عملاً مشروعاً؛ فيُقال له ما يترتّب ويُترك له
    القرار — وما أُنشئ بلا نسبة يُربط لاحقاً من شاشة الفترات.

    الوسائط:
      once   مفتاحُ الجلسة لتذكّر التخطّي (افتراضاً: الشاشة الحالية)
--}}

@php
    $hasCalendar = \App\Support\AccountingPeriodFilter::hasCalendar();
@endphp

@if (! $hasCalendar)
    <div class="modal fade" id="noFiscalYearModal" tabindex="-1" role="dialog"
        aria-labelledby="noFiscalYearTitle" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="noFiscalYearTitle">
                        <i class="feather icon-calendar text-warning"></i>
                        لا توجد سنة مالية
                    </h5>
                </div>

                <div class="modal-body">
                    <p class="mb-2">
                        لم تُعرَّف سنة مالية في النظام بعد. المستند الذي تنشئه الآن
                        <strong>لن يرتبط بأي فترة محاسبية</strong> — فلن يظهر في القوائم
                        ولا في التقارير ولا في الإقرار الضريبي، وإن كان مسجَّلاً في الدفتر.
                    </p>

                    <p class="mb-0 text-muted">
                        أضف سنة مالية أولاً ثم ولّد فتراتها. وما أُنشئ قبل ذلك يمكن ربطه
                        لاحقاً من شاشة «الفترات المحاسبية».
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="skipFiscalYear">
                        تخطّي والمتابعة
                    </button>

                    @if (Route::has('AccountingPeriods.index'))
                        <a href="{{ route('AccountingPeriods.index') }}" class="btn btn-primary">
                            <i class="feather icon-plus"></i> أضف سنة مالية
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                'use strict';

                /*
                    التخطّي يُحفظ للجلسة الواحدة: من قرّر المتابعة مرّةً لا يُسأل
                    عند كلّ سندٍ يفتحه — ويُسأل من جديد في جلسةٍ تالية، فالحالُ
                    قد تكون تغيّرت.
                */
                var KEY = 'fiscal_year_notice_skipped';

                try {
                    if (sessionStorage.getItem(KEY) === '1') return;
                } catch (e) {
                    // خزنةُ المتصفّح مغلقة: يُعرض التنبيه، وهو الأسلم
                }

                document.addEventListener('DOMContentLoaded', function () {
                    var node = document.getElementById('noFiscalYearModal');
                    if (! node || typeof bootstrap === 'undefined') return;

                    var modal = new bootstrap.Modal(node);
                    modal.show();

                    var skip = document.getElementById('skipFiscalYear');

                    if (skip) {
                        skip.addEventListener('click', function () {
                            try {
                                sessionStorage.setItem(KEY, '1');
                            } catch (e) {
                                // لا ذاكرة: يُعرض التنبيه في الشاشة التالية
                            }

                            modal.hide();
                        });
                    }
                });
            })();
        </script>
    @endpush
@endif
