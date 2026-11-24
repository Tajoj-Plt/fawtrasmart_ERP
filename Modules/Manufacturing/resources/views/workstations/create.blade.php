@extends('manufacturing::master')

@section('title', 'محطة عمل جديدة')

@section('content')
    @php
        /*
            صفوفُ المصروفات يبنيها السكربت، فكانت تضيع كلُّها عند أوّل خطأ
            تحقّق. تُعاد هنا من `old()` بترتيبها — وهو ترتيبُ «المصروف رقم
            كذا» في رسائل الخطأ.
        */
        $hasOldInput = session()->hasOldInput();
        $oldCosts = array_values((array) old('cost_expenses', []));
        $oldAccounts = array_values((array) old('account_expenses', []));
        $expenseRows = [];

        for ($i = 0, $count = max(count($oldCosts), count($oldAccounts)); $i < $count; $i++) {
            $expenseRows[] = ['cost' => $oldCosts[$i] ?? '', 'account' => $oldAccounts[$i] ?? ''];
        }
    @endphp
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'محطة عمل جديدة',
            'icon' => 'cpu',
            'sub' => 'تكلفة ساعة التشغيل: مصروفات وأجور وإهلاك أصل',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'محطات العمل' => route('manufacturing.workstations.index'),
                'جديدة' => null,
            ],
            'actions' => '<a href="' . route('manufacturing.workstations.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="workstationForm" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ المحطة</button>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        <form id="workstationForm" action="{{ route('manufacturing.workstations.store') }}" method="POST">
            @csrf

            {{-- ══════════ بيانات المحطة ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-info"></i> بيانات المحطة</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="name" class="stk-req">الاسم</label>
                                <input type="text" id="name" name="name" required class="form-control"
                                       value="{{ old('name') }}" placeholder="مثال: خط التعبئة">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                {{-- للعرض فقط: معطَّل فلا يُرسَل، والخادم يُصدر الرقم عند الحفظ --}}
                                <label for="code">الكود</label>
                                <input type="text" id="code" disabled
                                       class="form-control text-center stk-num"
                                       value="{{ $serial_number }}">
                                <span class="stk-help">يصدر تلقائياً عند الحفظ من إعدادات الترقيم</span>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                {{-- ثابتة: كلُّ حسابات التصنيع تضرب تكلفةَ المحطّة في ساعات التشغيل --}}
                                <label for="unit">الوحدة</label>
                                <input type="text" id="unit" disabled class="form-control"
                                       value="{{ \App\Models\WorkStations::UNIT }}">
                                <span class="stk-help">تُحسب تكلفة المحطة لكل ساعة تشغيل</span>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label for="description">الوصف</label>
                                <textarea id="description" name="description" class="form-control"
                                          rows="2">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>

                    {{-- «تابع لـ» — الجهة التي تتبعها المحطّة --}}
                    <div class="row mt-1">
                        @include('manufacturing::partials.affiliation-field')
                    </div>
                </div>
            </div>

            {{-- ══════════ المصروفات (صفوف متعدّدة) ══════════ --}}
            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-dollar-sign"></i> المصروفات
                        <span class="stk-count" id="expensesCount">{{ count($expenseRows) }}</span>
                    </h6>

                    <div class="d-flex align-items-center" style="gap:.6rem">
                        <span class="stk-balance stk-num" id="expensesSubtotal">0.00</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addRow">
                            <i class="feather icon-plus"></i> إضافة مصروف
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:26%">التكلفة</th>
                                <th>الحساب</th>
                                <th class="text-center" style="width:60px">حذف</th>
                            </tr>
                        </thead>
                        <tbody id="expensesRows">
                            @foreach ($expenseRows as $row)
                                <tr>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="cost_expenses[]"
                                               class="form-control stk-num text-center js-cost" placeholder="0.00"
                                               value="{{ $row['cost'] }}">
                                    </td>
                                    <td>
                                        <select name="account_expenses[]" class="form-control select2">
                                            <option value="">اختر الحساب…</option>
                                            @foreach ($expenseAccounts as $account)
                                                <option value="{{ $account->id }}"
                                                    {{ (string) $row['account'] === (string) $account->id ? 'selected' : '' }}>
                                                    {{ $account->code }} — {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger js-remove"
                                                aria-label="حذف الصف"><i class="feather icon-trash-2"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-body" id="expensesEmpty" {{ count($expenseRows) ? 'hidden' : '' }}>
                    <div class="stk-empty">
                        <div class="stk-empty__icon"><i class="feather icon-dollar-sign"></i></div>
                        <h5 class="stk-empty__title">لا مصروفات</h5>
                        <p class="stk-empty__hint">اضغط «إضافة مصروف» لإضافة أول صفّ</p>
                    </div>
                </div>
            </div>

            {{-- ══════════ الأجور والأصل ══════════ --}}
            <div class="row">
                <div class="col-lg-6 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-users"></i> الأجور</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group mb-0">
                                        <label for="cost_wages">أجور الساعة</label>
                                        <input type="number" step="0.01" min="0" id="cost_wages" name="cost_wages"
                                               class="form-control stk-num js-cost" value="{{ old('cost_wages') }}">
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <div class="form-group mb-0">
                                        <label for="account_wages">الحساب</label>
                                        <select id="account_wages" name="account_wages" class="form-control select2">
                                            <option value="">اختر الحساب…</option>
                                            @foreach ($expenseAccounts as $account)
                                                <option value="{{ $account->id }}"
                                                    {{ old('account_wages') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->code }} — {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="stk-help">من حسابات المصروفات</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-box"></i> الأصل</h4>
                        </div>
                        <div class="card-body">
                            {{--
                                قيمةُ الأصل لا تُجمع على تكلفة الساعة: تُقسم على
                                عمره بالساعات، والناتجُ — إهلاك الساعة — هو ما يدخل.
                            --}}
                            {{-- مفعَّلاً: إهلاكُ الساعة يدخل تكلفةَ المحطّة ويُقيَّد على حساب
                                 الأصل عند إتمام كل أمر. معطَّلاً: الأصلُ للعلم ولا يدخل التكلفة. --}}
                            <label class="stk-switch mb-2">
                                <input type="checkbox" id="automatic_depreciation" name="automatic_depreciation" value="1"
                                       {{ old('_token') ? (old('automatic_depreciation') ? 'checked' : '') : 'checked' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">إهلاك تلقائي</span>
                                    <span class="stk-switch__hint">
                                        مفعَّل: يُضاف إهلاك الساعة (قيمة الأصل ÷ عمره بالساعات) إلى تكلفة ساعة المحطة.
                                        معطَّل: لا يُحتسب إهلاك.
                                    </span>
                                </span>
                            </label>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="cost_origin">قيمة الأصل</label>
                                        <input type="number" step="0.01" min="0" id="cost_origin" name="cost_origin"
                                               class="form-control stk-num js-cost"
                                               value="{{ old('cost_origin') }}">
                                        <span class="stk-help">ثمن الآلة أو المعدّة كاملاً</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="asset_life_hours" id="assetLifeLabel">عمر الأصل بالساعات</label>
                                        <input type="number" step="0.01" min="0" id="asset_life_hours" name="asset_life_hours"
                                               class="form-control stk-num js-cost"
                                               value="{{ old('asset_life_hours') }}">
                                        <span class="stk-help">ساعات التشغيل المتوقَّعة طوال عمر الأصل — إلزامي مع الإهلاك التلقائي</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="origin">حساب الأصل</label>
                                        <select id="origin" name="origin" class="form-control select2">
                                            <option value="">اختر الحساب…</option>
                                            @foreach ($assetAccounts as $account)
                                                <option value="{{ $account->id }}"
                                                    {{ old('origin') == $account->id ? 'selected' : '' }}>
                                                    {{ $account->code }} — {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="stk-help">من حسابات الأصول الثابتة</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label>إهلاك الساعة</label>
                                        <div class="form-control stk-num" id="originHourly">0.00</div>
                                        <span class="stk-help">قيمة الأصل ÷ عمره بالساعات</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ══════════ الإجمالي ══════════ --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-bar-chart-2"></i> تكلفة ساعة التشغيل</h4>
                    <span class="stk-count">{{ App\Support\CurrencySettings::code() }}</span>
                </div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>المصروفات</dt>
                            <dd class="stk-num" id="totalExpenses">0.00</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>الأجور</dt>
                            <dd class="stk-num" id="totalWages">0.00</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>إهلاك الأصل في الساعة</dt>
                            <dd class="stk-num" id="totalOrigin">0.00</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt><strong>تكلفة الساعة</strong></dt>
                            <dd class="stk-num stk-balance" id="grandTotal">0.00</dd>
                        </div>
                    </dl>

                    <p class="stk-help mb-0 mt-1">المصروفات + الأجور + إهلاك الساعة — يحسبها النظام عند الحفظ.</p>
                </div>

                <div class="stk-form-foot">
                    <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
                    <a href="{{ route('manufacturing.workstations.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ المحطة
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function () {
            @php
                $accountOptions = collect($expenseAccounts)->map(fn ($a) => ['id' => $a->id, 'name' => $a->code . ' — ' . $a->name])->values();
            @endphp

            const accounts = @json($accountOptions);

            const esc = value => $('<div>').text(value == null ? '' : value).html();
            const fixed = value => Number(value || 0).toFixed(2);

            const accountOptionsHtml = '<option value="">اختر الحساب…</option>'
                + accounts.map(a => '<option value="' + a.id + '">' + esc(a.name) + '</option>').join('');

            function refresh() {
                let expenses = 0;

                $('#expensesRows .js-cost').each(function () {
                    expenses += parseFloat(this.value) || 0;
                });

                const wages = parseFloat($('#cost_wages').val()) || 0;
                const assetValue = parseFloat($('#cost_origin').val()) || 0;
                const lifeHours = parseFloat($('#asset_life_hours').val()) || 0;
                /* إهلاك الساعة = قيمة الأصل ÷ عمره بالساعات — صيغةُ الخادم نفسُها */
                const depreciates = $('#automatic_depreciation').is(':checked');
                const origin = (depreciates && assetValue > 0 && lifeHours > 0) ? assetValue / lifeHours : 0;
                const total = expenses + wages + origin;

                // العمرُ إلزاميّ حين يكون الإهلاك التلقائي مفعَّلاً
                $('#assetLifeLabel').toggleClass('stk-req', depreciates);
                $('#originHourly').text(fixed(origin));

                const count = $('#expensesRows tr').length;
                $('#expensesCount').text(count);
                $('#expensesSubtotal').text(fixed(expenses));
                document.getElementById('expensesEmpty').hidden = count > 0;

                $('#totalExpenses').text(fixed(expenses));
                $('#totalWages').text(fixed(wages));
                $('#totalOrigin').text(fixed(origin));
                $('#grandTotal').text(fixed(total));
            }

            function addRow() {
                const $row = $(
                    '<tr>'
                    + '<td><input type="number" step="0.01" min="0" name="cost_expenses[]"'
                    + '  class="form-control stk-num text-center js-cost" placeholder="0.00"></td>'
                    + '<td><select name="account_expenses[]" class="form-control select2">'
                    + accountOptionsHtml + '</select></td>'
                    + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger js-remove"'
                    + '  aria-label="حذف الصف"><i class="feather icon-trash-2"></i></button></td>'
                    + '</tr>'
                );

                $('#expensesRows').append($row);

                if ($.fn.select2) {
                    $row.find('.select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
                }

                refresh();
            }

            $('#addRow').on('click', addRow);

            $('#expensesRows').on('click', '.js-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });

            $(document).on('input', '.js-cost', refresh);

            if ($.fn.select2) {
                $('#workstationForm .select2').select2({ placeholder: 'اختر…', dir: 'rtl', width: '100%' });
            }

            // صفٌّ أوّل جاهز في نموذجٍ جديد — لا بعد خطأ تحقّقٍ أعاد ما أُدخل
            if (! @json($hasOldInput)) addRow();

            refresh();

            let confirmed = false;

            $('#workstationForm').on('submit', function (e) {
                if (confirmed) return;
                e.preventDefault();

                const errors = [];

                if (! $('#name').val().trim()) errors.push('أدخل اسم محطة العمل');

/* الفحصُ نفسُه في الخادم — هنا يُختصر الطريق على المستخدم */
                const negative = $('#workstationForm .js-cost').filter(function () {
                    return parseFloat(this.value) < 0;
                }).length;
                if (negative) errors.push('المبالغ وعمر الأصل لا تكون سالبة');

                let position = 0; // الصفُّ الفارغ يُسقطه الخادم فلا يُعدّ
                $('#expensesRows tr').each(function () {
                    const cost = $(this).find('.js-cost').val();
                    const account = $(this).find('select').val();
                    if (cost === '' && ! account) return;
                    position++;
                    if (cost === '') errors.push('المصروف رقم ' + position + ': أدخل التكلفة أو احذف الصف');
                    if (! account) errors.push('المصروف رقم ' + position + ': اختر حساب المصروف');
                });

                const assetValue = parseFloat($('#cost_origin').val()) || 0;
                const lifeHours = parseFloat($('#asset_life_hours').val()) || 0;
                if ($('#automatic_depreciation').is(':checked')) {
                    if (assetValue <= 0) errors.push('الإهلاك التلقائي مفعَّل — أدخل قيمة الأصل أو عطّل الإهلاك');
                    else if (lifeHours <= 0) errors.push('الإهلاك التلقائي مفعَّل — أدخل عمر الأصل بالساعات');
                }

                if (errors.length) {
                    Swal.fire({
                        icon: 'error',
                        title: 'بيانات غير مكتملة',
                        html: '<ul style="text-align:right;padding-inline-start:1.2rem">'
                            + errors.map(m => '<li>' + m + '</li>').join('') + '</ul>',
                    });
                    return;
                }

                Swal.fire({
                    title: 'حفظ محطة العمل؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احفظ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                }).then(result => {
                    if (! result.isConfirmed) return;
                    confirmed = true;
                    Swal.fire({
                        title: 'جارٍ الحفظ…',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => Swal.showLoading(),
                    });
                    this.submit();
                });
            });
        });
    </script>
@endsection
