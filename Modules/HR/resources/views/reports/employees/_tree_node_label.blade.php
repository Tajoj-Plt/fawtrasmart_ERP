{{-- بطاقةُ الموظف داخل عقدة الشجرة — يستدعيها `_tree_node` بمتغيّراته. --}}
@if ($nodeCanShow)
    <a href="{{ route('employee.show', $node->id) }}" class="org-node__name" target="_blank" rel="noopener">{{ $node->full_name }}</a>
@else
    <span class="org-node__name">{{ $node->full_name }}</span>
@endif
@if ($node->employee_code)
    <span class="org-node__meta" dir="ltr">{{ $node->employee_code }}</span>
@endif
<span class="org-node__meta">
    {{ optional($node->job_title)->name ?? 'بدون مسمّى' }}@if ($node->department) · {{ $node->department->name }}@endif
</span>
<span class="rpt-pill {{ $nodeTone }}">{{ $nodeStatus->label() }}</span>
