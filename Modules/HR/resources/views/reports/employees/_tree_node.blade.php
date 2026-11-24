{{--
    عقدةٌ في شجرة المدير المباشر — تستدعي نفسها لأبنائها.
    `$seen` يحمي من الحلقات في `direct_manager_id` (بيانات قديمة).
    من له تابعون يُعرض داخل `<details>` فيُطوى ويُفتح بلا JavaScript.
--}}
@php
    $seen = $seen ?? [];
    $already = in_array((int) $node->id, $seen, true);
    $seen[] = (int) $node->id;
    $kids = collect($childrenOf->get($node->id) ?? $childrenOf->get((string) $node->id) ?? []);
    $nodeStatus = $node->employmentStatus();
    $nodeTone = match ($nodeStatus) {
        \App\Enums\EmploymentStatus::ACTIVE, \App\Enums\EmploymentStatus::PROBATION => 'rpt-pill-success',
        \App\Enums\EmploymentStatus::SUSPENDED => 'rpt-pill-warning',
        \App\Enums\EmploymentStatus::INACTIVE => 'rpt-pill-secondary',
        default => 'rpt-pill-danger',
    };
    $nodeCanShow = $nodeCanShow ?? hasPermission('employees.show');
@endphp
@if (! $already)
<li>
    @if ($kids->isNotEmpty())
        <details open>
            <summary>
                <span class="org-node">
                    <i class="fas fa-chevron-down org-node__caret" aria-hidden="true"></i>
                    @include('hr::reports.employees._tree_node_label')
                    <span class="org-node__kids" title="تابعون مباشرون">{{ $kids->count() }}</span>
                </span>
            </summary>
            <ul>
                @foreach ($kids as $child)
                    @include('hr::reports.employees._tree_node', ['node' => $child, 'childrenOf' => $childrenOf, 'seen' => $seen, 'nodeCanShow' => $nodeCanShow])
                @endforeach
            </ul>
        </details>
    @else
        <span class="org-node">
            <i class="fas fa-user text-muted" aria-hidden="true"></i>
            @include('hr::reports.employees._tree_node_label')
        </span>
    @endif
</li>
@endif
