@php
    use Modules\RentalManagement\Support\RealEstateMoney;
    // عملة القسط عملة عقده
    $money = fn ($v, $schedule = null) => RealEstateMoney::format($v, $schedule?->contract?->currency);
@endphp
@if ($schedules->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">@include('stock::components.per-page', ['onchange' => 'loadData(1)', 'sizes' => [25, 50, 100]])</div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead><tr><th>الاستحقاق</th><th>العقد</th><th>المستأجر</th><th>الوحدة</th><th>النوع</th><th class="text-end">المستحق</th><th class="text-end">المدفوع</th><th class="text-end">المتبقّي</th><th class="text-center">الحالة</th><th></th></tr></thead>
            <tbody>
                @foreach ($schedules as $schedule)
                    @php $age = $schedule->live_status === 'overdue' ? (int) $schedule->due_date->diffInDays(now()) : 0; @endphp
                    <tr>
                        <td class="stk-num">{{ $schedule->due_date->format('Y-m-d') }}@if ($age) <small class="d-block text-danger">متأخر {{ $age }} يوماً</small> @endif</td>
                        <td>
                            @if (hasPermission('realestate.contracts.show')) <a href="{{ route('realestate.contracts.show', $schedule->contract_id) }}">{{ $schedule->contract->code ?? '—' }}</a> @else {{ $schedule->contract->code ?? '—' }} @endif
                        </td>
                        <td>{{ $schedule->contract->tenant->display_name ?? '—' }}</td>
                        <td>{{ $schedule->contract->unit->full_label ?? '—' }}</td>
                        <td>{{ $schedule->kind_label }}@if ($schedule->installment_no) <small class="text-muted stk-num">#{{ $schedule->installment_no }}</small> @endif
                            @if ($schedule->description) <small class="text-muted d-block">{{ $schedule->description }}</small> @endif</td>
                        <td class="text-end re-num">{{ $money($schedule->amount_due, $schedule) }}</td>
                        <td class="text-end re-num">{{ $money($schedule->amount_paid, $schedule) }}</td>
                        <td class="text-end re-num"><strong>{{ $money($schedule->balance, $schedule) }}</strong></td>
                        <td class="text-center"><span class="badge badge-light-{{ $schedule->status_meta['tone'] }}">{{ $schedule->status_meta['label'] }}</span></td>
                        <td class="text-end">
                            @if ($schedule->balance > 0 && $schedule->status !== 'cancelled')
                                @permission('realestate.payments.create')
                                    <a href="{{ route('realestate.payments.create', ['contract_id' => $schedule->contract_id, 'schedule_id' => $schedule->id]) }}" class="btn btn-sm btn-outline-primary">سداد</a>
                                @endpermission
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="stk-total-row"><td colspan="5">إجمالي هذه الصفحة</td>
                    <td class="text-end re-num">{{ $money($schedules->sum('amount_due')) }}</td>
                    <td class="text-end re-num">{{ $money($schedules->sum('amount_paid')) }}</td>
                    <td class="text-end re-num">{{ $money($schedules->sum(fn ($s) => $s->balance)) }}</td><td colspan="2"></td></tr>
            </tfoot>
        </table>
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $schedules, 'unit' => 'استحقاقاً'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'calendar', 'title' => 'لا استحقاقات في هذه الفئة', 'hint' => 'غيّر الفئة أو معايير البحث'])
@endif
