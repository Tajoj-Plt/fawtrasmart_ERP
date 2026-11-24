@extends('account::master')

@section('title')
    سند قبض
@stop

@section('content')
    {{--
        لا سنةَ مالية ⇒ المستندُ يُحفَظ بلا نسبة. يُقال ذلك قبل الإنشاء لا بعده.
    --}}
    @include('partials.no-fiscal-year-notice')

    @section('page-actions')
        <a href="{{ route('incomes.index') }}" class="btn btn-outline-secondary">
            <i class="feather icon-x"></i> إلغاء
        </a>
        <button type="submit" form="income_form" id="submitBtn" class="btn btn-primary">
            <i class="feather icon-save"></i> حفظ السند
        </button>
    @endsection

    @include('account::components.page-head', [
        'title' => 'سند قبض',
        'icon' => 'download',
        'sub' => 'إثبات مبلغٍ مقبوض وقيده على الخزينة والحساب المقابل',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'سندات القبض' => route('incomes.index'),
            'سند جديد' => null,
        ],
    ])

    <div class="content-body">
        {{--
            **ما كان**

            كانت الشاشة صفوفاً متجاورةً بلا أقسام: المبلغ والوصف والمرفق في
            سطر، ثم الكود والتاريخ، ثم الحساب والخزينة — ثم بطاقةٌ وحيدة
            داخل `container` تحمل «مكرر» فتخرج عن عرض ما فوقها. لا عناوين
            تفصل ما يخصّ المبلغ عمّا يخصّ القيد.

            **ما صار**

            أربع بطاقاتٍ معنونة على نسق سند الصرف: بيانات السند، ثم طرفا
            القيد، ثم الضرائب، ثم التكرار والمرفق.
        --}}
        <form id="income_form" action="{{ route('incomes.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <input type="hidden" name="created_by" value="{{ auth()->id() }}">

            @include('account::finance.incomes._fields')

            <div class="stk-form-foot">
                <a href="{{ route('incomes.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather icon-save"></i> حفظ السند
                </button>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    {{--
        jQuery وsweetalert2 يحمّلهما القالب الرئيسي.

        كانت هذه الشاشة تجلب `jquery-3.6.0` من CDN بعد تحميل القالب لها،
        فيستبدل الكائن الجديد `window.$` ويمحو ما سُجِّل عليه من إضافات —
        ومنها `select2`. فيظهر الحساب قائمةً عاديةً بلا بحث، وهو ما لا يُحتمل
        في قائمةٍ بمئات الحسابات.
    --}}
    @include('account::finance.incomes._fields_script')
    <script>
        (function () {
            'use strict';

            // نقرتان سريعتان — أو Enter مرّتين في حقل المبلغ — كانتا تفتحان
            // حَدَثَي `submit` قبل أن يظهر أثر تعطيل الأزرار (لا يقع إلا
            // بعد التأكيد)، فيُرسل سندا قبضٍ لعمليةٍ واحدة فعلاً. عَلَمٌ
            // واحد يُغلق الباب من أول حدثٍ مقبول حتى ينتهي أثره كاملاً.
            var isVoucherSubmitting = false;

            // ═══ الحفظ ═══
            document.getElementById('income_form').addEventListener('submit', function (event) {
                event.preventDefault();

                if (isVoucherSubmitting) {
                    return;
                }

                var form = this;

                /*
                    زرّ الحفظ يُلتقط من النموذج لا بمعرّفٍ ثابت.

                    كان الكود يقرأ `getElementById('submitBtn')` ولا عنصر بهذا
                    المعرّف في الصفحة، فيرمي السطر التالي خطأً عند التأكيد —
                    فلا يُرسَل السند ولا تظهر رسالة.
                */
                var buttons = [].slice.call(
                    document.querySelectorAll('#submitBtn, #income_form button[type="submit"]')
                );

                isVoucherSubmitting = true;
                buttons.forEach(function (button) {
                    button.disabled = true;
                });

                var summary = ''
                    + '<div class="text-right" dir="rtl">'
                    + '<p><strong>المبلغ:</strong> ' + (document.getElementById('amount_input').value || '0')
                    + ' {{ \App\Support\CurrencySettings::symbol() }}</p>'
                    + '<p><strong>التاريخ:</strong> ' + document.getElementById('date').value + '</p>';

                var description = document.getElementById('description').value;
                if (description) {
                    summary += '<p><strong>الوصف:</strong> ' + description + '</p>';
                }

                var categorySelect = document.getElementById('incomes_category_id');
                if (categorySelect.selectedIndex > 0) {
                    summary += '<p><strong>التصنيف:</strong> '
                        + categorySelect.options[categorySelect.selectedIndex].text.trim() + '</p>';
                }

                summary += '</div>';

                Swal.fire({
                    title: 'تأكيد إضافة سند القبض',
                    html: summary,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احفظ السند',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then(function (result) {
                    if (! result.isConfirmed) {
                        // إلغاءٌ لا يُبقي الأزرار مقفلةً على أثر حدثٍ لم يكتمل
                        isVoucherSubmitting = false;
                        buttons.forEach(function (button) {
                            button.disabled = false;
                        });
                        return;
                    }

                    fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        }
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم بنجاح',
                                    text: data.message || 'أُضيف سند القبض',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(function () {
                                    window.location.href = data.redirect || '{{ route('incomes.index') }}';
                                });

                                return;
                            }

                            var message = data.message || 'تعذّر حفظ السند';
                            if (data.errors) {
                                message = Object.keys(data.errors)
                                    .map(function (key) { return [].concat(data.errors[key]).join('\n'); })
                                    .join('\n');
                            }

                            /*
                                رفضُ الخادم لتكرار نقرةٍ سابقة (حارس التكرار في
                                `AccountingOperations::guardDuplicateSubmission`)
                                ليس خطأً في بياناتٍ أدخلها المندوب — فلا يُعرض
                                بأيقونة الخطأ الحمراء نفسها التي توحي بأن رقماً
                                أو حقلاً خاطئ، بل بأيقونة تهدئ لا تُنذر.
                            */
                            var isDuplicateNotice = message.indexOf('تم استلام هذا الطلب بالفعل') !== -1;

                            Swal.fire(isDuplicateNotice ? {
                                icon: 'info',
                                title: 'السند قيد الحفظ بالفعل',
                                text: message
                            } : {
                                icon: 'error',
                                title: 'خطأ في البيانات',
                                text: message
                            });
                        })
                        .catch(function () {
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ في الشبكة',
                                text: 'تعذّر إرسال البيانات — أعد المحاولة.'
                            });
                        })
                        .finally(function () {
                            isVoucherSubmitting = false;
                            buttons.forEach(function (button) {
                                button.disabled = false;
                            });
                        });
                });
            });
        })();
    </script>
@endsection
