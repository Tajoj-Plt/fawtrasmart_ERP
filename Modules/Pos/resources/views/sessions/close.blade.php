{{--
    شاشةُ إقفال الجلسة.

    ═══════════ القالبُ المفقود ═══════════

    `SessionsController::closeForm()` كانت تُعيد `view('pos::sessions.close')`
    منذ البداية — **وقالبٌ بهذا الاسم غير موجودٍ في الوحدة**. القوالبُ
    الموجودة: create، index، print، show، summary. فزرُّ «إغلاق الجلسة»
    كان يؤدّي إلى صفحة خطأ.

    ═══════════ ولماذا العدُّ بالفئات؟ ═══════════

    خانةٌ واحدة يكتب فيها الكاشير «١٢٤٠» تُخفي الخطأ الأشيع: أن يُحصى
    ورقةُ خمسينٍ مكان مئة. والعدُّ بالفئات يجعل المجموع يُحسب لا يُكتب،
    فيقلّ الخطأ ويصير مراجعتُه ممكنة.
--}}
@extends('pos::master')

@section('title')
إغلاق الجلسة — {{ $session->session_number }}
@stop

@section('content')

<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">
                    إغلاق الجلسة — {{ $session->session_number }}
                </h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('POS.sales_start.index') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('pos.sessions.index') }}">الجلسات</a></li>
                        <li class="breadcrumb-item active">إغلاق الجلسة</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">


    <form action="{{ route('pos.sessions.close', $session->id) }}" method="POST" id="closeSessionForm">
        @csrf

        <div class="row">

            {{-- ═══════════ الملخّص المتوقَّع ═══════════ --}}
            <div class="col-lg-7 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">ملخّص الجلسة</h4>
                        <span class="badge badge-light-primary">{{ $summary['currency'] }}</span>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td>الكاشير</td>
                                    <td class="text-end fw-bold">{{ $session->user->name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td>الجهاز / الوردية</td>
                                    <td class="text-end">{{ $session->device->device_name ?? '—' }} / {{ $session->shift->name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td>بدأت في</td>
                                    <td class="text-end">{{ $session->started_at?->format('Y-m-d H:i') }}</td>
                                </tr>

                                <tr><td colspan="2"><hr class="my-1"></td></tr>

                                <tr>
                                    <td>الرصيد الافتتاحي</td>
                                    <td class="text-end">{{ number_format($summary['opening_balance'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>مبيعات نقدية</td>
                                    <td class="text-end text-success">+ {{ number_format($summary['total_cash'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>مبيعات بالبطاقة <small class="text-muted">(لا تدخل الدرج)</small></td>
                                    <td class="text-end text-muted">{{ number_format($summary['total_card'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>المرتجعات</td>
                                    <td class="text-end text-danger">− {{ number_format($summary['total_returns'], 2) }}</td>
                                </tr>

                                {{-- حركاتُ الدرج — لم تكن تدخل في الحساب لأنها لم تكن موجودة --}}
                                <tr>
                                    <td>إيداعات في الدرج</td>
                                    <td class="text-end text-success">+ {{ number_format($summary['drawer']['pay_in'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>سحوبات من الدرج</td>
                                    <td class="text-end text-danger">− {{ number_format($summary['drawer']['pay_out'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>ترحيل إلى الخزينة</td>
                                    <td class="text-end text-danger">− {{ number_format($summary['drawer']['drop'], 2) }}</td>
                                </tr>

                                <tr class="border-top">
                                    <td class="fw-bold fs-5 pt-1">الرصيد النقدي المتوقَّع</td>
                                    <td class="text-end fw-bold fs-5 pt-1" id="expectedCash"
                                        data-expected="{{ $summary['expected_cash'] }}">
                                        {{ number_format($summary['expected_cash'], 2) }} {{ $summary['symbol'] }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        @if ($summary['drawer']['pending'] > 0)
                            <div class="alert alert-warning mt-2 mb-0">
                                <strong>تنبيه:</strong>
                                هناك {{ $summary['drawer']['pending'] }} حركة درج بانتظار الاعتماد —
                                وهي لا تدخل في الرصيد المتوقَّع حتى تُعتمَد.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═══════════ العدُّ الفعلي ═══════════ --}}
            <div class="col-lg-5 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">عدّ الدرج</h4>
                    </div>
                    <div class="card-body">

                        {{--
                            العدُّ بالفئات — اختياريّ.

                            من يعدّ بالفئات يُحسب له المجموعُ تلقائياً، ومن
                            يكتب المجموع مباشرةً يكتبه في الخانة أدناه.
                        --}}
                        <div class="mb-2">
                            <button class="btn btn-sm btn-outline-secondary w-100" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#denominations">
                                عدّ بالفئات (اختياري)
                            </button>
                        </div>

                        <div class="collapse mb-2" id="denominations">
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>الفئة</th><th>العدد</th><th class="text-end">القيمة</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ([500, 200, 100, 50, 20, 10, 5, 1, 0.5, 0.25] as $denom)
                                        <tr>
                                            <td>{{ $denom }}</td>
                                            <td style="width:100px">
                                                <input type="number" min="0" step="1"
                                                       class="form-control form-control-sm denom-count"
                                                       data-value="{{ $denom }}" value="">
                                            </td>
                                            <td class="text-end denom-line">0.00</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="2">مجموع العدّ</td>
                                        <td class="text-end" id="denomTotal">0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold" for="actual_closing_balance">
                                الرصيد النقدي الفعلي <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" required
                                       class="form-control form-control-lg @error('actual_closing_balance') is-invalid @enderror"
                                       id="actual_closing_balance" name="actual_closing_balance"
                                       value="{{ old('actual_closing_balance') }}"
                                       placeholder="0.00" autocomplete="off">
                                <span class="input-group-text">{{ $summary['symbol'] }}</span>
                            </div>
                            @error('actual_closing_balance')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- الفرقُ يُحسب أمام الكاشير قبل الحفظ لا بعده --}}
                        <div class="alert d-none mb-2" id="varianceBox">
                            <div class="d-flex justify-content-between">
                                <span>الفرق</span>
                                <strong id="varianceValue">0.00</strong>
                            </div>
                            <small id="varianceHint" class="d-block mt-1"></small>
                        </div>

                        <div class="mb-2">
                            <label class="form-label" for="closing_notes">ملاحظات الإقفال</label>
                            <textarea class="form-control" id="closing_notes" name="closing_notes"
                                      rows="3" maxlength="1000"
                                      placeholder="سبب الفرق إن وُجد…">{{ old('closing_notes') }}</textarea>
                        </div>

                        @if ($summary['needs_confirmation'])
                            <div class="alert alert-info">
                                بعد الإقفال تنتقل الجلسة إلى <strong>«بانتظار التأكيد»</strong>،
                                ولا تُعتمد العهدة حتى يؤكّدها مشرف.
                            </div>
                        @endif

                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary flex-grow-1" id="submitBtn">
                                إغلاق الجلسة
                            </button>
                            <a href="{{ route('pos.sessions.show', $session->id) }}" class="btn btn-outline-secondary">
                                إلغاء
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

@endsection

@section('script')
<script>
(function () {
    const expected  = parseFloat(document.getElementById('expectedCash').dataset.expected) || 0;
    const actualInp = document.getElementById('actual_closing_balance');
    const box       = document.getElementById('varianceBox');
    const value     = document.getElementById('varianceValue');
    const hint      = document.getElementById('varianceHint');
    const notes     = document.getElementById('closing_notes');

    // مجموعُ الفئات يملأ خانة الفعلي — فالمجموع يُحسب لا يُكتب
    function recalcDenominations() {
        let total = 0;

        document.querySelectorAll('.denom-count').forEach(function (input) {
            const denom = parseFloat(input.dataset.value) || 0;
            const count = parseInt(input.value, 10) || 0;
            const line  = denom * count;

            total += line;
            input.closest('tr').querySelector('.denom-line').textContent = line.toFixed(2);
        });

        document.getElementById('denomTotal').textContent = total.toFixed(2);

        if (total > 0) {
            actualInp.value = total.toFixed(2);
            showVariance();
        }
    }

    function showVariance() {
        const actual = parseFloat(actualInp.value);

        if (isNaN(actual)) {
            box.classList.add('d-none');
            return;
        }

        const diff = +(actual - expected).toFixed(2);

        box.classList.remove('d-none', 'alert-success', 'alert-warning', 'alert-danger');
        value.textContent = (diff > 0 ? '+' : '') + diff.toFixed(2);

        if (Math.abs(diff) <= 0.01) {
            box.classList.add('alert-success');
            hint.textContent = 'الدرج متوازن.';
        } else if (diff > 0) {
            box.classList.add('alert-warning');
            hint.textContent = 'زيادة في الصندوق — تُقيَّد في حساب فائض الدرج.';
        } else {
            box.classList.add('alert-danger');
            hint.textContent = 'نقص في الصندوق — يُقيَّد في حساب عجز الدرج.';
        }
    }

    actualInp.addEventListener('input', showVariance);
    document.querySelectorAll('.denom-count').forEach(function (input) {
        input.addEventListener('input', recalcDenominations);
    });

    // فرقٌ بلا تفسير: يُطلب السبب قبل الإرسال لا بعده
    document.getElementById('closeSessionForm').addEventListener('submit', function (e) {
        const actual = parseFloat(actualInp.value);
        const diff   = Math.abs(actual - expected);

        if (diff > 0.01 && notes.value.trim() === '') {
            e.preventDefault();
            notes.focus();
            hint.textContent = 'اكتب سبب الفرق قبل الإقفال.';
            box.classList.remove('alert-success');
            box.classList.add('alert-danger');
        }
    });
})();
</script>
@endsection
