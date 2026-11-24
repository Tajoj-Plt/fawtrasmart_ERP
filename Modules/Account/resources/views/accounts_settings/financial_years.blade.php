@extends('account::master')

@section('title', 'السنوات المالية')

@section('content')
    <div class="content-body">

        {{--
            كان القالب يحمل ورقة أنماط خاصة به: ترويسة زرقاء `#007bff` وزرّ
            أخضر `#28a745` مكتوبَين في الصفحة — لا يتبعان «مظهر النظام» ولا
            يتغيّران بتغييره. وزرّ «إلغاء» كان `href=""`: يُعيد تحميل الصفحة
            نفسها فيمحو ما كُتب.
        --}}
        @include('account::components.page-head', [
            'title' => 'السنوات المالية',
            'icon' => 'calendar',
            'sub' => 'بداية السنة المالية ونهايتها — يبني عليها كل تقرير مداه الافتراضي',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'إعدادات الحسابات' => route('accounts_settings.index'),
                'السنوات المالية' => null,
            ],
        ])

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-plus-circle"></i> سنة مالية جديدة
                </h6>
            </div>

            <form action="{{ route('accounts_settings.store_financial_year') }}" method="POST">
                @csrf

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="name">اسم السنة</label>
                                <input type="text" name="name" id="name" class="form-control"
                                    value="{{ old('name') }}" placeholder="يُشتقّ من سنة البداية إن تُرك فارغاً">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="start_date">تاريخ البدء <span class="stk-req"></span></label>
                                <input type="date" name="start_date" id="start_date" class="form-control"
                                    value="{{ old('start_date', date('Y-m-d')) }}" required>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="period">المدّة <span class="stk-req"></span></label>
                                <select name="period" id="period" class="form-control" required>
                                    <option value="12" @selected(old('period', 12) == 12)>سنة كاملة (12 شهراً)</option>
                                    <option value="6" @selected(old('period') == 6)>نصف سنة (6 أشهر)</option>
                                    <option value="3" @selected(old('period') == 3)>ربع سنة (3 أشهر)</option>
                                </select>
                                <small class="stk-help">تُحسب نهاية السنة تلقائياً من البداية والمدّة</small>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="end_date">تاريخ الانتهاء <span class="stk-req"></span></label>
                                <input type="date" name="end_date" id="end_date" class="form-control"
                                    value="{{ old('end_date') }}" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label for="description">الوصف</label>
                                <textarea name="description" id="description" rows="2" class="form-control"
                                    placeholder="ملاحظة تُميّز هذه السنة عند مراجعتها لاحقاً">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stk-form-foot">
                    <a href="{{ route('accounts_settings.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> حفظ السنة المالية
                    </button>
                </div>
            </form>
        </div>

        <div class="card stk-table-card">
            @if (($years ?? collect())->isNotEmpty())
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> السنوات المعرَّفة
                        <span class="stk-count">{{ $years->count() }}</span>
                    </h6>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>السنة</th>
                                <th class="text-center">من</th>
                                <th class="text-center">إلى</th>
                                <th class="text-center">المدّة</th>
                                <th class="text-center">الفترات</th>
                                <th class="text-center">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($years as $year)
                                <tr>
                                    <td>
                                        <span class="stk-media__title">{{ $year->name }}</span>
                                        @if ($year->is_current)
                                            <span class="stk-state stk-state--on">الجارية</span>
                                        @endif
                                        @if ($year->notes)
                                            <small class="stk-media__sub d-block">{{ $year->notes }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center stk-num">{{ $year->start_date?->toDateString() }}</td>
                                    <td class="text-center stk-num">{{ $year->end_date?->toDateString() }}</td>
                                    <td class="text-center stk-num">{{ $year->lengthInDays() }} يوماً</td>
                                    <td class="text-center stk-num">{{ $year->periods_count }}</td>
                                    <td class="text-center">
                                        @if ($year->isOpen())
                                            <span class="stk-state stk-state--on">مفتوحة</span>
                                        @else
                                            <span class="stk-state stk-state--off">مقفلة</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="stk-table-foot">
                    <span class="stk-table-foot__count">
                        إدارة الفترات وإقفالها من شاشة «الفترات المحاسبية» في الإعدادات
                    </span>
                    @if (Route::has('AccountingPeriods.index') && hasPermission('settings.accounting_periods.view'))
                        <a href="{{ route('AccountingPeriods.index') }}" class="btn btn-sm btn-outline-primary">
                            <i class="feather icon-external-link"></i> فتح الشاشة
                        </a>
                    @endif
                </div>
            @else
                <div class="stk-empty">
                    <span class="stk-empty__icon"><i class="feather icon-calendar"></i></span>
                    <h6 class="stk-empty__title">لا سنة مالية معرَّفة بعد</h6>
                    <p class="stk-empty__hint">
                        بلا سنةٍ مالية تفتح التقارير على السنة الميلادية — أول يناير —
                        ولو كانت سنة المنشأة تبدأ في شهرٍ آخر.
                    </p>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            /*
                نهاية السنة تتبع بدايتها ومدّتها.

                كان الحقلان الثلاثة مستقلّة: يُدخل المستخدم البداية والمدّة ثم
                يحسب النهاية بنفسه — ويكتب تاريخاً لا يطابقهما فتُحفظ سنةٌ
                مدّتها غير مدّتها المعلنة.
            */
            var start = document.getElementById('start_date');
            var period = document.getElementById('period');
            var end = document.getElementById('end_date');

            function syncEnd() {
                if (! start.value) return;

                var months = parseInt(period.value, 10) || 12;
                var date = new Date(start.value);

                date.setMonth(date.getMonth() + months);
                date.setDate(date.getDate() - 1);   // اليوم الأخير من المدّة لا أول ما بعدها

                end.value = date.toISOString().slice(0, 10);
            }

            start.addEventListener('change', syncEnd);
            period.addEventListener('change', syncEnd);

            if (! end.value) syncEnd();
        })();
    </script>
@endsection
