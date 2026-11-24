{{--
    جدولُ «أداء المندوبين» — مشتركٌ بين لوحة المبيعات (أعلى ٢٥ بالمبيعات)
    وشاشة «أداء المندوبين» (الكلُّ، بالترتيب المختار).

    جدولٌ حقيقيٌّ لا بطاقاتٌ بشبكة CSS: التجربةُ الأولى (بطاقاتٌ بـ
    `grid-template-columns`) كانت تفيض خارج حدودها في شاشاتٍ متوسطة العرض
    لأن مجموع أعمدةٍ بهذا العدد لا يدخل عرض المحتوى مهما ضاقت الأعمدة —
    والجدولُ داخل `.table-responsive` يعالج هذا بتمريرٍ أفقيٍّ مضبوطٍ بدل
    فيضانٍ يكسر الصفحة. البِطاقةُ (اللون، الشارة، صندوق المحصَّل) بقيت،
    فقط وعاؤها عاد جدولاً.

    يقرأ: $salesByEmployee (صفوفُ `SalesDashboardMetrics::salesByEmployee()`)، $money.
    اختيارياً: $showRank (عمودُ المركز)، $sortBy (يُبرز عمودَه)، $repTotals
    (صفُّ مجموعٍ في التذييل — مجموعُ الصفوف نفسِها).

    المحصَّلُ صافٍ مما ردّه المندوبُ نقداً للعميل، ويظهر المردودُ سطراً تحته
    يفتح قائمتَه — كما يظهر ما ألغاه من سندات.
--}}
@php
    // ١٠٠٪ فأكثر محقَّق، و٧٠٪ فأكثر قريب، وما دونه متأخّر
    $targetTone ??= fn ($percent) => $percent >= 100 ? 'var(--sd-success)' : ($percent >= 70 ? 'var(--sd-warning)' : 'var(--sd-danger)');
    $showRank = ! empty($showRank);
    $sortBy = $sortBy ?? null;
    $sorted = fn (string $key) => $sortBy === $key ? ' is-sorted' : '';
    $repTotals = $repTotals ?? null;
    $rankTone = fn (int $rank) => match (true) {
        $rank === 1 => 'sd-rank-1',
        $rank === 2 => 'sd-rank-2',
        $rank === 3 => 'sd-rank-3',
        default => 'sd-rank-plain',
    };
@endphp
@if ($salesByEmployee->isNotEmpty())
    <div class="sd-rep-table-wrap">
        <div class="table-responsive">
            <table class="sd-table sd-rep-table">
                <thead>
                    <tr>
                        @if ($showRank)
                            <th class="sd-rank">#</th>
                        @endif
                        <th>{{ __('sales_dashboard.table.employee') }}</th>
                        <th class="sd-num{{ $sorted('invoices') }}">{{ __('sales_dashboard.table.invoices') }}</th>
                        <th class="sd-num{{ $sorted('sales') }}">{{ __('sales_dashboard.table.sales') }}</th>
                        <th class="sd-num{{ $sorted('returns_total') }}">{{ __('sales_dashboard.table.returns') }}</th>
                        <th class="sd-num{{ $sorted('net_sales') }}">{{ __('sales_dashboard.table.net') }}</th>
                        <th class="sd-num{{ $sorted('collections') }}">{{ __('sales_dashboard.table.collections') }}</th>
                        <th class="sd-num">{{ __('sales_dashboard.table.expenses') }}</th>
                        <th class="{{ trim($sorted('visits')) }}">{{ __('sales_dashboard.table.visits') }}</th>
                        <th>{{ __('sales_dashboard.table.sales_target') }}</th>
                        <th>{{ __('sales_dashboard.table.collections_target') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salesByEmployee as $employee)
                        @php
                            // 0 = قيودٌ بلا منشئ («غير محدّد»)
                            $empKey = (int) ($employee['employee_id'] ?? 0);
                            $empId = $employee['employee_id'];
                            $rank = $loop->iteration;
                        @endphp
                        <tr>
                            @if ($showRank)
                                <td class="sd-rank"><span class="sd-rank-badge {{ $rankTone($rank) }}">{{ $rank }}</span></td>
                            @endif
                            <td class="sd-rep-name" data-segment="emp.{{ $empKey }}.all" role="button" tabindex="0">{{ $employee['name'] }}</td>
                            <td class="sd-num{{ $sorted('invoices') }}" data-segment="emp.{{ $empKey }}.sale" role="button" tabindex="0">{{ $employee['invoices'] }}</td>
                            <td class="sd-num font-weight-bold{{ $sorted('sales') }}" data-segment="emp.{{ $empKey }}.sale" role="button" tabindex="0">{{ $money($employee['sales']) }}</td>
                            <td class="sd-num{{ $sorted('returns_total') }}" data-segment="emp.{{ $empKey }}.returns" role="button" tabindex="0" style="color: var(--sd-danger);">
                                {{ $money($employee['returns_total']) }}
                                @if ($employee['sales'] > 0 && $employee['returns_total'] > 0)
                                    <small class="sd-rep-sub">{{ __('sales_dashboard.rep.return_rate', ['rate' => number_format(($employee['returns_total'] / $employee['sales']) * 100, 1)]) }}</small>
                                @endif
                            </td>
                            <td class="sd-num font-weight-bold{{ $sorted('net_sales') }}">{{ $money($employee['net_sales']) }}</td>
                            <td class="sd-num{{ $sorted('collections') }}" data-segment="emp.{{ $empKey }}.collection" role="button" tabindex="0">
                                <span class="sd-wallet-box"><b class="sd-num">{{ $money($employee['collections']) }}</b></span>
                                {{-- ما ردّه نقداً للعميل مطروحٌ من الرقم أعلاه — والسطرُ يفتح قائمتَه --}}
                                @if ($employee['refunds_count'] > 0)
                                    <small class="sd-rep-sub sd-rep-link" data-segment="emp.{{ $empKey }}.refund" role="button" tabindex="0" style="color: var(--sd-danger);">{{ __('sales_dashboard.rep.refunded', ['count' => $employee['refunds_count'], 'amount' => $money($employee['refunds'])]) }}</small>
                                @endif
                                @if ($employee['cancelled_count'] > 0)
                                    <small class="sd-rep-sub sd-rep-link" data-segment="emp.{{ $empKey }}.cancellation" role="button" tabindex="0" style="color: var(--sd-warning);">{{ __('sales_dashboard.rep.cancelled', ['count' => $employee['cancelled_count'], 'amount' => $money($employee['cancelled_receipts'])]) }}</small>
                                @endif
                            </td>
                            <td class="sd-num" @if ($empId) data-segment="expenses.{{ $empId }}" role="button" tabindex="0" @endif style="color: var(--sd-warning);">
                                {{ $money($employee['expenses']) }}
                                @if ($employee['expense_documents'] > 0)
                                    <small class="sd-rep-sub">{{ __('sales_dashboard.rep.documents', ['count' => $employee['expense_documents']]) }}</small>
                                @endif
                            </td>
                            {{-- عدداً المجموع فقط — لا تفصيل النتائج مبثوثاً هنا؛ كل رقمٍ يفتح تفصيله كاملاً بنقرة (اللوحة الجانبية) --}}
                            @php
                                $visitLines = [
                                    ['title' => __('sales_dashboard.visits.actual'), 'prefix' => 'visits', 'total' => $employee['visits']],
                                    ['title' => __('sales_dashboard.visits.plan'), 'prefix' => 'plan', 'total' => $employee['planned']],
                                ];
                            @endphp
                            <td class="sd-visits{{ $sorted('visits') }}">
                                @foreach ($visitLines as $line)
                                    <div class="sd-visit-line">
                                        <span class="sd-visit-title">{{ $line['title'] }}</span>
                                        <span class="sd-pill is-total" @if ($empId && $line['total'] > 0) data-segment="{{ $line['prefix'] }}.{{ $empId }}" role="button" tabindex="0" @endif><b class="sd-num">{{ number_format($line['total']) }}</b></span>
                                    </div>
                                    {{-- ناجحة + سلبية = المكتملة — وكلٌّ يفتح عملاءه --}}
                                    @if ($line['prefix'] === 'visits' && $empId && $line['total'] > 0)
                                        <small class="sd-rep-sub">
                                            <span class="sd-rep-link" data-segment="visits.{{ $empId }}.success" role="button" tabindex="0" style="color: var(--sd-success);">{{ __('sales_dashboard.visits.success') }} {{ number_format($employee['visit_success']) }}</span>
                                            ·
                                            <span class="sd-rep-link" data-segment="visits.{{ $empId }}.negative" role="button" tabindex="0" style="color: var(--sd-danger);">{{ __('sales_dashboard.visits.negative') }} {{ number_format($employee['visit_negative']) }}</span>
                                        </small>
                                    @endif
                                @endforeach
                                @if ($employee['plan_justified'] > 0)
                                    <small class="sd-rep-sub">{{ __('sales_dashboard.visits.justified', ['count' => $employee['plan_justified']]) }}</small>
                                @endif
                            </td>
                            @foreach (['sales_target', 'collections_target'] as $targetKey)
                                <td class="sd-rep-target">
                                    @if ($target = $employee[$targetKey])
                                        <span class="sd-num font-weight-bold" style="color: {{ $targetTone($target['percent']) }};">{{ number_format($target['percent'], 1) }}%</span>
                                        <div class="sd-bar"><span style="width: {{ min(100, max(2, $target['percent'])) }}%; background: {{ $targetTone($target['percent']) }};"></span></div>
                                        <small class="sd-rep-sub sd-num">{{ $money($target['achieved']) }} / {{ $money($target['target']) }}</small>
                                    @else
                                        <small class="sd-rep-sub">{{ __('sales_dashboard.rep.no_target') }}</small>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if ($repTotals)
                    {{-- مجموعُ الصفوف المعروضة — أعمدةُ المبالغ والأعداد وحدها؛ الأهدافُ لكلِّ مندوبٍ وحده فلا تُجمع --}}
                    <tfoot>
                        <tr>
                            @if ($showRank)
                                <td class="sd-rank"></td>
                            @endif
                            <td class="sd-rep-name">{{ __('sales_dashboard.reps.total_row') }}</td>
                            <td class="sd-num">{{ number_format($repTotals['invoices']) }}</td>
                            <td class="sd-num">{{ $money($repTotals['sales']) }}</td>
                            <td class="sd-num" style="color: var(--sd-danger);">{{ $money($repTotals['returns_total']) }}</td>
                            <td class="sd-num">{{ $money($repTotals['net_sales']) }}</td>
                            <td class="sd-num">
                                <span class="sd-wallet-box"><b class="sd-num">{{ $money($repTotals['collections']) }}</b></span>
                                @if ($repTotals['refunds_count'] > 0)
                                    <small class="sd-rep-sub" style="color: var(--sd-danger);">{{ __('sales_dashboard.rep.refunded', ['count' => $repTotals['refunds_count'], 'amount' => $money($repTotals['refunds'])]) }}</small>
                                @endif
                            </td>
                            <td class="sd-num" style="color: var(--sd-warning);">{{ $money($repTotals['expenses']) }}</td>
                            <td>
                                <div class="sd-visit-line"><span class="sd-visit-title">{{ __('sales_dashboard.visits.actual') }}</span><span class="sd-pill is-total"><b class="sd-num">{{ number_format($repTotals['visits']) }}</b></span></div>
                                <div class="sd-visit-line"><span class="sd-visit-title">{{ __('sales_dashboard.visits.plan') }}</span><span class="sd-pill is-total"><b class="sd-num">{{ number_format($repTotals['planned']) }}</b></span></div>
                            </td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@else
    <div class="sd-empty"><i class="feather icon-user"></i> {{ __('sales_dashboard.empty.no_employees') }}</div>
@endif
