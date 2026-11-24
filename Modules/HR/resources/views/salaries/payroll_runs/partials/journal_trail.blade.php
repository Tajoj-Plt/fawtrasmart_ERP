{{--
    «القيود المحاسبية» — كلُّ قيدٍ يخصّ الصرفة أو القسيمة مع نوعه ورابطه،
    وتحته الخصمياتُ المُقيَّدة التي تنتظر الاقتطاع. انظر `App\Services\PayrollJournalTrail`.

    المتغيّرات: $trail (Collection)، $pendingDeductions (Collection)، $trailMoney (callable)
--}}
@php
    $trailMoney = $trailMoney ?? fn ($v) => \App\Support\CurrencySettings::format((float) $v);
@endphp
<div class="card stk-table-card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-book"></i> القيود المحاسبية
            <span class="stk-count">{{ $trail->count() }}</span>
            <i class="feather icon-help-circle stk-tip"
               title="استحقاق: مدين مصروف الرواتب بالإجمالي / دائن مستحقات الموظف. صرف: مدين المستحقات / دائن الخزينة بالصافي والسلف والتأمينات. تأمينات: حصة المنشأة. خصمية: قُيِّدت عند اعتمادها (مدين مستحقات الموظف / دائن حساب الخصمية) وتُقتطع عند الصرف. عكس: قيدٌ معاكس لا يحذف الأصل."></i>
        </h6>
    </div>
    @if ($trail->count())
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>النوع</th><th>المرجع</th><th>التاريخ</th><th class="text-end">المبلغ</th></tr>
                </thead>
                <tbody>
                    @foreach ($trail as $row)
                        <tr>
                            <td>
                                <span class="stk-state stk-state--{{ $row['tone'] }}">{{ $row['label'] }}</span>
                                @if ($row['is_reversed'])
                                    <small class="d-block text-muted">معكوس</small>
                                @endif
                            </td>
                            <td>
                                @if ($row['url'])
                                    <a href="{{ $row['url'] }}" target="_blank">{{ $row['reference'] }}</a>
                                @else
                                    {{ $row['reference'] }}
                                @endif
                                <small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($row['description'], 90) }}</small>
                                @if (! $row['balanced'])
                                    <small class="d-block text-danger"><i class="feather icon-alert-triangle"></i> القيد غير متوازن</small>
                                @endif
                            </td>
                            <td class="stk-num"><small>{{ $row['date'] ?? '—' }}</small></td>
                            <td class="text-end stk-num">{{ $trailMoney($row['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="card-body">
            <p class="stk-empty__hint mb-0">لا قيود بعد — تُنشأ قيودُ الاستحقاق والصرف والتأمينات لحظةَ الصرف.</p>
        </div>
    @endif

    @if ($pendingDeductions->count())
        <div class="card-body border-top">
            <div class="alert alert-info small mb-1">
                <i class="feather icon-info"></i>
                <strong>خصميات مُقيَّدة بانتظار الاقتطاع من الراتب القادم:</strong>
                {{ $trailMoney($pendingDeductions->sum('remaining')) }}.
                قيدُها قائمٌ منذ اعتمادها (مدين مستحقات الموظف / دائن حساب الخصمية) — فالموظف مدينٌ بها حتى تُقتطع، وعكسُ صرف الراتب لا يُلغيها ولا يُنشئ لها قيداً جديداً.
            </div>
            <ul class="list-unstyled mb-0">
                @foreach ($pendingDeductions as $p)
                    <li class="d-flex justify-content-between">
                        <span>
                            @if (Route::has('hr.deductions.show'))
                                <a href="{{ route('hr.deductions.show', $p['deduction']->id) }}">{{ $p['deduction']->code ?: 'خصمية #'.$p['deduction']->id }}</a>
                            @else
                                {{ $p['deduction']->code ?: 'خصمية #'.$p['deduction']->id }}
                            @endif
                            — {{ $p['deduction']->type_label ?? $p['deduction']->deduction_type }}
                            @if ($p['journal_ref'])
                                <small class="text-muted">(قيد
                                    @if ($p['journal_url'])
                                        <a href="{{ $p['journal_url'] }}" target="_blank">{{ $p['journal_ref'] }}</a>
                                    @else
                                        {{ $p['journal_ref'] }}
                                    @endif)</small>
                            @endif
                        </span>
                        <span class="stk-num text-danger">{{ $trailMoney($p['remaining']) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
