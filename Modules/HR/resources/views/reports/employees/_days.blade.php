{{--
    خليّةُ «الأيام المتبقية»: سالبةٌ = انتهى، وحتى مدّة الإنذار = تحذير.
    @param int|null $days, int $warnWithin, string $endedWord («انتهى»/«انتهت»)
--}}
@if ($days === null)
    <span class="rpt-pill rpt-pill-secondary">بلا نهاية</span>
@elseif ($days < 0)
    <span class="rpt-pill rpt-pill-danger">{{ $endedWord ?? 'انتهى' }} منذ {{ abs($days) }} يوماً</span>
@elseif ($days === 0)
    <span class="rpt-pill rpt-pill-danger">اليوم</span>
@elseif ($days <= $warnWithin)
    <span class="rpt-pill rpt-pill-warning">{{ $days }} يوماً</span>
@else
    <span class="rpt-pill rpt-pill-success">{{ $days }} يوماً</span>
@endif
