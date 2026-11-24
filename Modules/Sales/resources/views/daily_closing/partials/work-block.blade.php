{{--
    دوامُ اليوم في الإقفال: كم داوم، وكم عند العملاء، وكم بينهم، وهل طابق المطلوب.
    $work = DailyClosingLedger::work() — null لمن لا ملفَّ وظيفيّاً له.
--}}
@if (! empty($work))
    @php
        $workPill = [
            'matched' => 'rpt-pill-success',
            'short' => 'rpt-pill-danger',
            'open' => 'rpt-pill-warning',
            'no_attendance' => 'rpt-pill-danger',
        ][$work['state']] ?? 'rpt-pill-secondary';
    @endphp
    <div class="rpt-block">
        <header>
            <h5><i class="feather icon-clock"></i> دوام اليوم</h5>
            <span class="rpt-pill {{ $workPill }}">{{ $work['state_label'] }}</span>
        </header>

        <div class="rpt-scroll">
            <table class="rpt-table">
                <tbody>
                    <tr>
                        <th style="width:24%">الدوام المطلوب</th>
                        <td>{{ $work['expected'] ?: '—' }} <small>({{ $work['required'] }})</small></td>
                        <th style="width:24%">الحضور / الانصراف</th>
                        <td class="rpt-num">{{ $work['check_in'] ?? '—' }} / {{ $work['check_out'] ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>صافي الدوام (بدون الاستراحة)</th>
                        <td><strong>{{ $work['net'] }}</strong></td>
                        <th>الاستراحة</th>
                        <td>
                            {{ $work['breaks'] }}
                            <small>(المسموح {{ $work['break_allowed'] }} د)</small>
                            @if ($work['break_minutes'] > $work['break_allowed'])
                                <span class="rpt-neg">زيادة {{ $work['break_minutes'] - $work['break_allowed'] }} د</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>الوقت عند العملاء</th>
                        <td>{{ $work['clients'] }} <small>({{ $work['visits_count'] }} زيارة)</small></td>
                        <th>بين عميل وآخر <small>(تنقّل أو توقّف — بدون الاستراحة)</small></th>
                        <td>{{ $work['travel'] }}</td>
                    </tr>
                    <tr>
                        <th>من الحضور إلى أول زيارة</th>
                        <td>{{ $work['before_first'] }}</td>
                        <th>من آخر زيارة إلى الانصراف</th>
                        <td>{{ $work['after_last'] }}</td>
                    </tr>
                    <tr>
                        <th>النقص عن المطلوب</th>
                        <td colspan="3" class="{{ $work['short_minutes'] > 0 ? 'rpt-neg' : '' }}">{{ $work['short_minutes'] > 0 ? $work['short'] : 'لا نقص' }}</td>
                    </tr>
                    @if ($work['late_minutes'] > 0 || $work['outside_visits'] > 0)
                        <tr>
                            <th>التأخير</th>
                            <td class="{{ $work['late_minutes'] > 0 ? 'rpt-neg' : '' }}">{{ $work['late_minutes'] > 0 ? $work['late_minutes'].' د' : '—' }}</td>
                            <th>زيارات خارج الدوام المسجَّل</th>
                            <td class="{{ $work['outside_visits'] > 0 ? 'rpt-neg' : '' }}">{{ $work['outside_visits'] ?: '—' }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endif
