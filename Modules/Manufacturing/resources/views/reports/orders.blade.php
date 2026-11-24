@extends('manufacturing::reports.layout', [
    'reportTitle' => 'تقرير أوامر التصنيع',
    'reportIcon' => 'clipboard',
    'reportSub' => 'الأوامر ضمن المدى بحالاتها وكمّياتها وتكلفتها — بلا الملغي',
])

@php
    $money = fn ($v) => number_format((float) $v, 2);
    // الكمية بكسورها إن وُجدت — «٩٨٠٠» لا «٩٨٠٠٫٠٠٠٠»، و«٠٫٥» لا «١»
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');

    /*
        الألوان بمفاتيح نصّية كما يخزّنها العمود — كانت أرقاماً (0..3)
        فلا تطابق شيئاً، وتُعرض كل الحالات رماديةً.
    */
    $tone = collect(\App\Enums\ManufacturOrderStatus::cases())
        ->mapWithKeys(fn ($c) => [$c->value => $c->tone()])
        ->all();

    $doneStatuses = ['completed', 'closed'];
    $cancelledOnly = $f['status'] === 'cancelled';
    $ordersCount = $totals->sum('n');

    /*
        مجموعُ الكمية يصحّ لصنفٍ واحد وحده: عشرةُ آلاف مليلتر من عطرٍ
        سائب وثمانٍ وتسعون زجاجةً ليست «١٠٬٠٩٨» من شيء.
    */
    $qtyTotals = ($singleProduct && $byProduct->isNotEmpty())
        ? [
            'planned' => $byProduct->sum('planned'),
            'done_planned' => $byProduct->sum('done_planned'),
            'produced' => $byProduct->sum('produced'),
        ]
        : null;
@endphp

@section('metrics')
    <div class="stk-metrics">
        <div class="stk-metric">
            <span class="stk-metric__icon text-primary"><i class="feather icon-clipboard"></i></span>
            <span class="stk-metric__value stk-num text-primary">{{ number_format($ordersCount) }}</span>
            <span class="stk-metric__label">أوامر (بلا الملغي)</span>
        </div>

        @if ($qtyTotals)
            <div class="stk-metric">
                <span class="stk-metric__icon text-info"><i class="feather icon-package"></i></span>
                <span class="stk-metric__value stk-num text-info">{{ $num($qtyTotals['planned']) }}</span>
                <span class="stk-metric__label">الكمّية المخطَّطة</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon text-success"><i class="feather icon-check-circle"></i></span>
                <span class="stk-metric__value stk-num text-success">{{ $num($qtyTotals['produced']) }}</span>
                <span class="stk-metric__label">الكمّية المنتَجة</span>
            </div>
        @endif

        @foreach ($totals as $t)
            <div class="stk-metric">
                <span class="stk-metric__icon text-danger"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__value stk-num text-danger">{{ $money($t->actual_cost) }}</span>
                <span class="stk-metric__label">التكلفة الفعلية للمكتملة — {{ $t->currency_code }}</span>
            </div>
            @if ($t->open_n > 0)
                <div class="stk-metric">
                    <span class="stk-metric__icon text-secondary"><i class="feather icon-edit-3"></i></span>
                    <span class="stk-metric__value stk-num text-secondary">{{ $money($t->open_cost) }}</span>
                    <span class="stk-metric__label">تقديرية للمفتوحة — {{ $t->currency_code }}</span>
                </div>
            @endif
        @endforeach

        @foreach ($statuses as $key => $label)
            <div class="stk-metric">
                <span class="stk-metric__icon text-{{ $tone[$key] }}"><i class="feather icon-flag"></i></span>
                <span class="stk-metric__value stk-num text-{{ $tone[$key] }}">{{ number_format($byStatus[$key] ?? 0) }}</span>
                <span class="stk-metric__label">{{ $label }}</span>
            </div>
        @endforeach
    </div>

    @if ($cancelledOnly)
        <div class="alert alert-warning py-1 px-2 mb-2" style="font-size:.85rem;">
            <i class="feather icon-info"></i>
            الأوامر الملغاة تُعرض هنا للاطّلاع فقط — لا تدخل أيَّ مجموعِ كميةٍ أو تكلفة.
        </div>
    @endif

    {{-- ══════════ الكمّيات بالصنف ══════════
         الموضعُ الوحيد الذي تُجمع فيه الكمية: كلُّ صنفٍ بوحدته. --}}
    @if ($byProduct->isNotEmpty())
        <div class="card stk-table-card mb-2">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title">
                    <i class="feather icon-package"></i> الكمّيات والتكلفة حسب الصنف
                    <span class="stk-count">{{ number_format($byProduct->count()) }}</span>
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>الصنف</th>
                            <th class="text-center" style="width:9%">أوامر</th>
                            <th class="text-center" style="width:9%">مكتملة</th>
                            <th class="text-end" style="width:13%">مخطَّطة (الكلّ)</th>
                            <th class="text-end" style="width:13%">مخطَّطة للمكتملة</th>
                            <th class="text-end" style="width:13%">منتَجة فعلاً</th>
                            <th class="text-end" style="width:14%">التكلفة الفعلية</th>
                            <th class="text-end" style="width:12%">تكلفة الوحدة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byProduct as $p)
                            <tr>
                                <td>{{ $p->product_name ?: '—' }}</td>
                                <td class="text-center stk-num">{{ number_format($p->n) }}</td>
                                <td class="text-center stk-num">{{ number_format($p->done_n) }}</td>
                                <td class="text-end stk-num">{{ $num($p->planned) }}</td>
                                <td class="text-end stk-num">{{ $p->done_n > 0 ? $num($p->done_planned) : '—' }}</td>
                                <td class="text-end stk-num fw-bold">{{ $p->done_n > 0 ? $num($p->produced) : '—' }}</td>
                                <td class="text-end stk-num">
                                    {{ $p->done_n > 0 ? $money($p->actual_cost) . ' ' . $p->currency_code : '—' }}
                                </td>
                                <td class="text-end stk-num">
                                    {{ (float) $p->produced > 0 ? number_format($p->actual_cost / $p->produced, 4) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@section('table')
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th style="width:11%">الكود</th>
                <th>الأمر</th>
                <th style="width:16%">الصنف</th>
                <th class="text-center" style="width:9%">من</th>
                <th class="text-center" style="width:9%">إلى</th>
                <th class="text-end" style="width:8%">مخطَّطة</th>
                <th class="text-end" style="width:8%">منتَجة</th>
                <th class="text-end" style="width:14%">التكلفة</th>
                <th class="text-center" style="width:10%">الحالة</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php $isDone = in_array($row->status, $doneStatuses, true); @endphp
                <tr @class(['text-muted' => $row->status === 'cancelled'])>
                    <td class="stk-num">{{ $row->code ?: '—' }}</td>
                    <td>{{ $row->name ?: '—' }}</td>
                    <td>{{ $row->product_name ?: '—' }}</td>
                    <td class="text-center stk-num">{{ $row->from_date ?: '—' }}</td>
                    <td class="text-center stk-num">{{ $row->to_date ?: '—' }}</td>
                    <td class="text-end stk-num">{{ $num($row->quantity) }}</td>
                    <td class="text-end stk-num">{{ $isDone && $row->actual_quantity !== null ? $num($row->actual_quantity) : '—' }}</td>
                    <td class="text-end stk-num">
                        {{ $money($row->last_total_cost) }} {{ $row->currency_code }}
                        {{-- ما لم يُتَمّ فتكلفتُه من أسعار الأمر لا من الدفتر --}}
                        @if ($row->status === 'cancelled')
                            <span class="badge bg-light text-secondary border">خارج المجاميع</span>
                        @elseif (! $isDone)
                            <span class="badge bg-light text-secondary border">تقديري</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-{{ $tone[$row->status] ?? 'secondary' }}">
                            {{ $statuses[$row->status] ?? ($row->status ?: 'غير محدد') }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        {{ $cancelledOnly ? 'لا أوامر ملغاة في هذا المدى.' : 'لا أوامر تصنيع في هذا المدى.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>

        {{-- صفُّ إجمالي لكل عملة؛ ولا إجماليَّ لقائمة الملغي --}}
        @if ($ordersCount > 0)
            <tfoot>
                @foreach ($totals as $t)
                    <tr>
                        <td colspan="5" class="text-end fw-bold">
                            إجمالي الفعلي للمكتملة ({{ number_format($t->done_n) }})
                            @if ($totals->count() > 1) — {{ $t->currency_code }} @endif
                        </td>
                        <td class="text-end stk-num fw-bold">{{ $qtyTotals && $loop->first ? $num($qtyTotals['done_planned']) : '' }}</td>
                        <td class="text-end stk-num fw-bold">{{ $qtyTotals && $loop->first ? $num($qtyTotals['produced']) : '' }}</td>
                        <td class="text-end stk-num fw-bold">{{ $money($t->actual_cost) }} {{ $t->currency_code }}</td>
                        <td></td>
                    </tr>
                    @if ($t->open_n > 0)
                        <tr class="text-muted">
                            <td colspan="5" class="text-end">تقديري للمفتوحة ({{ number_format($t->open_n) }})</td>
                            <td class="text-end stk-num">{{ $qtyTotals && $loop->first ? $num($qtyTotals['planned'] - $qtyTotals['done_planned']) : '' }}</td>
                            <td></td>
                            <td class="text-end stk-num">{{ $money($t->open_cost) }} {{ $t->currency_code }}</td>
                            <td></td>
                        </tr>
                    @endif
                @endforeach
            </tfoot>
        @endif
    </table>
@endsection
