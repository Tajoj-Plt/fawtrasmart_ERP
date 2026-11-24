@extends('hr::master')

@section('title')
    بنود الرواتب
@stop

@push('styles')
    <style>
        #table-container.is-loading { opacity: .55; pointer-events: none; transition: opacity .15s; }
    </style>
@endpush

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => 'بنود الرواتب',
            'icon' => 'list',
            'sub' => 'المصدرُ الواحد لتعريف الاستحقاقات والاستقطاعات: قيمتُها، وحسابُها، وخضوعُها للتأمينات وأجرِ نهاية الخدمة',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات الرواتب' => route('SalarySittings.index'),
                'بنود الرواتب' => null,
            ],
            'actions' => hasPermission('salary_items.create')
                ? '<a href="'.route('SalaryItems.create').'" class="btn btn-primary"><i class="fas fa-plus"></i> بند راتب جديد</a>'
                : '',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-salary-items.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه تعريفاتُ بنود الراتب: البدلاتُ والاستحقاقاتُ والحسومات التي يُختار منها في العقد والقسيمة. وكلُّ بندٍ يُنسَخ إلى العقد والقسيمة نسخةً مستقلّة.',
            'blocks' => [
                'ما يترتّب على البند' => [
                    'البندُ يدخل قسيمةَ الراتب فيزيد الصافيَ أو ينقصه بحسب نوعه، وحسابُه هو ما يُقيَّد عليه عند الصرف.',
                    'علمُ «خاضع للتأمينات» يُدخل مبلغَ البند في الأجر الخاضع للتأمينات الاجتماعية عند الصرف، و«أجر نهاية الخدمة» يُدخله في أجر احتساب المكافأة.',
                    'وتُورَّث الخصائصُ إلى نُسَخ البند في العقود والقسائم الجديدة بالاسم والنوع — ولهذا لا يتكرّر الاسمُ داخل النوع الواحد.',
                ],
                'البدل ليس مكافأة' => [
                    'البدلُ (سكن، مواصلات…) بندٌ تعاقديٌّ متكرّر: يُضاف إلى العقد أو القسيمة فيُصرف في كل راتب.',
                    'أمّا «كتالوج المكافآت» (نظام النقاط) فحافزٌ لمرّةٍ واحدة: يستبدل الموظف نقاطه بمكافأة نقدية، فتُعتمد، فتظهر سطراً واحداً «مكافأة: …» على قسيمته التالية وتُصرف معها ثم تُزال — على «حساب مصروف المكافآت» المربوط في إعدادات الحسابات المحاسبية.',
                    'فلا تُعرّف هنا بنداً باسم «مكافأة» لتمنح به مكافأةَ الكتالوج نفسها — يُصرف المبلغ مرّتين. ومكافأة نهاية الخدمة وعمولات المبيعات نظامان مستقلّان كذلك.',
                ],
                'كتالوج البدلات والاستقطاعات' => [
                    'كان شاشةً ثانية تعرّف الأنواعَ نفسَها لتضيف علمَ التأمينات؛ والعلمُ الآن على البند هنا، ورابطُ الكتالوج يحوّل إلى هذه الشاشة.',
                    'التصنيفاتُ القديمة وبياناتُها باقية كما هي، ولم يُحذف منها شيء.',
                ],
                'التعديلُ والحذف' => [
                    'تعديلُ بندٍ يسري على ما يُنسخ ويُحتسَب بعده؛ والعقودُ والقسائمُ القائمة تحتفظ بنسختها، والصرفاتُ التي وقعت لا تتغيّر.',
                    'البندُ المستعمَل في عقدٍ أو قسيمةٍ لا يُحذف — يُعطَّل فيختفي من الاختيار ويبقى أثرُه.',
                ],
            ],
            'facts' => [
                'البندُ يزيد الصافيَ أو ينقصه',
                'علمُ التأمينات يقرّر الأجرَ الخاضع',
                'المستعمَلُ يُعطَّل ولا يُحذف',
            ],
        ])

        {{-- ══════════ ملخّص ══════════ --}}
        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-list"></i></span>
                <span class="stk-metric__label">كل البنود</span>
                <span class="stk-metric__value">{{ $totals['all'] ?? 0 }}</span>
            </div>
            <div class="stk-metric stk-metric--success">
                <span class="stk-metric__icon"><i class="feather icon-plus-circle"></i></span>
                <span class="stk-metric__label">مستحقات</span>
                <span class="stk-metric__value">{{ $totals['additions'] ?? 0 }}</span>
            </div>
            <div class="stk-metric stk-metric--danger">
                <span class="stk-metric__icon"><i class="feather icon-minus-circle"></i></span>
                <span class="stk-metric__label">مستقطعات</span>
                <span class="stk-metric__value">{{ $totals['deductions'] ?? 0 }}</span>
            </div>
            <div class="stk-metric stk-metric--info">
                <span class="stk-metric__icon"><i class="feather icon-shield"></i></span>
                <span class="stk-metric__label">خاضعة للتأمينات</span>
                <span class="stk-metric__value">{{ $totals['insurable'] ?? 0 }}</span>
            </div>
        </div>

        {{-- ══════════ بحث وتصفية ══════════ --}}
        <div class="card stk-filters">
            <div class="card-body">
                <div class="stk-filters__head">
                    <h6 class="stk-filters__title"><i class="feather icon-filter"></i> بحث وتصفية</h6>
                    @if (request()->hasAny(['name', 'status', 'type', 'is_insurable']) && request()->collect()->only(['name', 'status', 'type', 'is_insurable'])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())
                        <span class="stk-count">فلاتر مفعّلة</span>
                    @endif
                </div>

                <form id="search-form" method="GET" action="{{ route('SalaryItems.index') }}" data-no-ux>
                    <div class="row">
                        <div class="col-md-4 col-sm-12">
                            <div class="form-group">
                                <label for="name">اسم البند أو رقمه</label>
                                <input type="text" id="name" class="form-control" name="name"
                                       placeholder="اكتب للبحث في الاسم أو الوصف…" value="{{ request('name') }}">
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="type">النوع</label>
                                <select id="type" name="type" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    <option value="1" {{ request('type') === '1' ? 'selected' : '' }}>مستحق</option>
                                    <option value="2" {{ request('type') === '2' ? 'selected' : '' }}>مستقطع</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group">
                                <label for="status">الحالة</label>
                                <select id="status" name="status" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>نشط</option>
                                    <option value="2" {{ request('status') === '2' ? 'selected' : '' }}>غير نشط</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="is_insurable">
                                    التأمينات الاجتماعية
                                    <i class="feather icon-help-circle stk-tip" title="البنود التي يدخل مبلغُها الأجرَ الخاضعَ للتأمينات عند الصرف"></i>
                                </label>
                                <select id="is_insurable" name="is_insurable" class="form-control hr-s2">
                                    <option value="">الكل</option>
                                    <option value="1" {{ request('is_insurable') === '1' ? 'selected' : '' }}>خاضع</option>
                                    <option value="0" {{ request('is_insurable') === '0' ? 'selected' : '' }}>غير خاضع</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">

                    <div class="stk-filters__foot">
                        <a href="{{ route('SalaryItems.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> إعادة تعيين
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ الجدول (يُستبدل عبر AJAX) ══════════ --}}
        <div id="table-container">
            @include('hr::salaries.salary_items.partials.table', ['salaryItems' => $salaryItems])
        </div>
    </div>
@endsection

@push('scripts')
    @include('hr::components.table-menu')
    <script>
        $(function () {
            var $container = $('#table-container');

            function load(url, data) {
                $container.addClass('is-loading');
                $.ajax({ url: url, type: 'GET', data: data || {}, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .done(function (response) {
                        $container.html(response.html);
                        if (window.history && window.history.replaceState) {
                            var qs = typeof data === 'string' ? data : '';
                            window.history.replaceState(null, '', qs ? ('{{ route('SalaryItems.index') }}?' + qs) : url);
                        }
                    })
                    .fail(function () {
                        if (window.toastr) { toastr.error('تعذّر تحميل البنود — أعد المحاولة.'); }
                    })
                    .always(function () { $container.removeClass('is-loading'); });
            }

            $('#search-form').on('submit', function (e) {
                e.preventDefault();
                load('{{ route('SalaryItems.index') }}', $(this).serialize());
            });

            $(document).on('click', '#table-container .pagination a', function (e) {
                e.preventDefault();
                load($(this).attr('href'));
                $('html, body').animate({ scrollTop: $container.offset().top - 100 }, 300);
            });

            $(document).on('change', '#salaryItemsPerPage', function () {
                $('#search-form input[name="per_page"]').val(this.value);
                $('#search-form').trigger('submit');
            });
        });
    </script>
@endpush
