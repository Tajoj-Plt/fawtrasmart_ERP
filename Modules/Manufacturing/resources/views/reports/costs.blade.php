@extends('manufacturing::reports.layout', [
    'reportTitle' => 'تقرير تكاليف الإنتاج',
    'reportIcon' => 'dollar-sign',
    'reportSub' => 'التكلفة الفعلية المسجَّلة عند إتمام الأمر، مفصولةً بمصدرها، مقابل المخطَّط ونصيب الوحدة',
])

@php
    $money = fn ($v) => number_format((float) $v, 2);
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    // تكلفةُ الوحدة بأربع خانات: ١٬٢١٠ على ٩٬٨٠٠ مل = ٠٫١٢٣٥ لا «٠٫١٢»
    $unit = fn ($v) => number_format((float) $v, 4);
    // الانحراف بإشارته: موجبٌ = فعليٌّ فوق المخطَّط
    $signed = fn ($v) => ((float) $v > 0 ? '+' : '') . number_format((float) $v, 2);

    $cancelledOnly = $f['status'] === 'cancelled';
    $doneCount = $totals->sum('done_n');
    $openCount = $totals->sum('open_n');
@endphp

@section('metrics')
    @foreach ($totals as $t)
        @if ($t->done_n > 0)
            <div class="stk-metrics">
                <div class="stk-metric">
                    <span class="stk-metric__icon text-primary"><i class="feather icon-box"></i></span>
                    <span class="stk-metric__value stk-num text-primary">{{ $money($t->raw_cost) }}</span>
                    <span class="stk-metric__label">المواد الخام — {{ $t->currency_code }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon text-warning"><i class="feather icon-file-text"></i></span>
                    <span class="stk-metric__value stk-num text-warning">{{ $money($t->expense_cost) }}</span>
                    <span class="stk-metric__label">المصروفات</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon text-info"><i class="feather icon-cpu"></i></span>
                    <span class="stk-metric__value stk-num text-info">{{ $money($t->labor_cost) }}</span>
                    <span class="stk-metric__label">التشغيل</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon text-danger"><i class="feather icon-dollar-sign"></i></span>
                    <span class="stk-metric__value stk-num text-danger">{{ $money($t->actual_total) }}</span>
                    <span class="stk-metric__label">التكلفة الفعلية ({{ number_format($t->done_n) }} مكتملة)</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__icon text-secondary"><i class="feather icon-edit-3"></i></span>
                    <span class="stk-metric__value stk-num text-secondary">{{ $money($t->planned_total) }}</span>
                    <span class="stk-metric__label">المخطَّط لها</span>
                </div>
                <div class="stk-metric">
                    @php $vTone = abs($t->variance) < 0.005 ? 'muted' : ($t->variance > 0 ? 'danger' : 'success'); @endphp
                    <span class="stk-metric__icon text-{{ $vTone }}"><i class="feather icon-trending-up"></i></span>
                    <span class="stk-metric__value stk-num text-{{ $vTone }}">{{ $signed($t->variance) }}</span>
                    <span class="stk-metric__label">الانحراف (فعلي − مخطَّط)</span>
                </div>
            </div>
        @endif
    @endforeach

    @if ($cancelledOnly)
        <div class="alert alert-warning py-1 px-2 mb-2" style="font-size:.85rem;">
            <i class="feather icon-info"></i>
            الأوامر الملغاة تُعرض هنا للاطّلاع فقط — لا تكلفةَ لها في أيّ مجموع.
        </div>
    @elseif ($openCount > 0)
        <div class="alert alert-info py-1 px-2 mb-2" style="font-size:.85rem;">
            <i class="feather icon-info"></i>
            {{ number_format($openCount) }} أمراً لم يكتمل بعد: تكلفتُه <strong>تقديرية</strong> من أسعار الأمر
            (وما صُرف من مواده فبتكلفته الفعلية)، وتُعرض في صفّها ولا تدخل مجاميعَ الفعلي.
        </div>
    @endif
@endsection

@section('table')
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th style="width:9%">الكود</th>
                <th>الأمر / الصنف</th>
                <th class="text-end" style="width:8%">الكمّية</th>
                <th class="text-end" style="width:9%">خام</th>
                <th class="text-end" style="width:8%">مصروفات</th>
                <th class="text-end" style="width:8%">تشغيل</th>
                <th class="text-end" style="width:11%">التكلفة</th>
                <th class="text-end" style="width:9%">المخطَّط</th>
                <th class="text-end" style="width:9%">الانحراف</th>
                <th class="text-end" style="width:9%">تكلفة الوحدة</th>
                <th class="text-center" style="width:8%">الأساس</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr @class(['text-muted' => $row->basis !== 'actual'])>
                    <td class="stk-num">{{ $row->code ?: '—' }}</td>
                    <td>
                        {{ $row->name ?: '—' }}
                        <div class="text-muted" style="font-size:.78rem;">{{ $row->product_name ?: '—' }}</div>
                    </td>
                    <td class="text-end stk-num">
                        {{ $num($row->base_qty) }}
                        @if ($row->basis === 'actual' && (float) $row->quantity != (float) $row->base_qty)
                            <div class="text-muted" style="font-size:.78rem;">من {{ $num($row->quantity) }}</div>
                        @endif
                    </td>
                    <td class="text-end stk-num">{{ $money($row->raw_cost) }}</td>
                    <td class="text-end stk-num">{{ $money($row->expense_cost) }}</td>
                    <td class="text-end stk-num">{{ $money($row->labor_cost) }}</td>
                    <td class="text-end stk-num fw-bold">{{ $money($row->cost_total) }} {{ $row->currency_code }}</td>
                    <td class="text-end stk-num">{{ $money($row->planned_total) }}</td>
                    <td class="text-end stk-num">
                        @if ($row->variance === null)
                            —
                        @else
                            <span class="text-{{ abs($row->variance) < 0.005 ? 'muted' : ($row->variance > 0 ? 'danger' : 'success') }}">
                                {{ $signed($row->variance) }}
                            </span>
                        @endif
                    </td>
                    <td class="text-end stk-num">{{ $row->unit_cost !== null ? $unit($row->unit_cost) : '—' }}</td>
                    <td class="text-center">
                        @if ($row->basis === 'actual')
                            <span class="badge bg-success">فعلي</span>
                        @elseif ($row->basis === 'cancelled')
                            <span class="badge bg-danger">ملغي</span>
                        @else
                            <span class="badge bg-light text-secondary border"
                                title="{{ $row->raw_unissued > 0 ? 'مواد لم تُصرف بعد — بأسعار الأمر' : 'صُرفت المواد، ولم يُتَمّ الأمر' }}">تقديري</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center text-muted py-4">
                        {{ $cancelledOnly ? 'لا أوامر ملغاة في هذا المدى.' : 'لا تكاليف مسجَّلة على أوامر هذا المدى.' }}
                    </td>
                </tr>
            @endforelse
        </tbody>

        {{-- المجاميعُ مالٌ فقط، ولكل عملةٍ صفُّها؛ والتقديريُّ في صفٍّ مستقلّ --}}
        @if ($doneCount + $openCount > 0)
            <tfoot>
                @foreach ($totals as $t)
                    @if ($t->done_n > 0)
                        <tr>
                            <td colspan="3" class="text-end fw-bold">
                                إجمالي الفعلي — {{ number_format($t->done_n) }} مكتملة
                            </td>
                            <td class="text-end stk-num fw-bold">{{ $money($t->raw_cost) }}</td>
                            <td class="text-end stk-num fw-bold">{{ $money($t->expense_cost) }}</td>
                            <td class="text-end stk-num fw-bold">{{ $money($t->labor_cost) }}</td>
                            <td class="text-end stk-num fw-bold">{{ $money($t->actual_total) }} {{ $t->currency_code }}</td>
                            <td class="text-end stk-num fw-bold">{{ $money($t->planned_total) }}</td>
                            <td class="text-end stk-num fw-bold">{{ $signed($t->variance) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    @endif
                    @if ($t->open_n > 0)
                        <tr class="text-muted">
                            <td colspan="6" class="text-end">تقديري — {{ number_format($t->open_n) }} لم تكتمل</td>
                            <td class="text-end stk-num">{{ $money($t->open_total) }} {{ $t->currency_code }}</td>
                            <td colspan="4"></td>
                        </tr>
                    @endif
                @endforeach
            </tfoot>
        @endif
    </table>
@endsection
