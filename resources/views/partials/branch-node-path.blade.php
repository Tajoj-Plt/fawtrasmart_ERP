{{--
    خليّةُ «تابع لـ» في صفّ جدول — مسارُ العقدة من الجذر.

    الاسمُ المجرَّد لا يكفي في شجرةٍ بهذا العمق: «منفذ صبيا» تحت «فرع بيش
    وصبيا والعيدابي» تحت «منطقة جيزان». فيُعرض المسارُ كاملاً، ويُبرز آخرُه
    لأنّه العقدةُ التي يخصّها المستند فعلاً.

    والمستندُ بلا عقدة يقول ذلك صراحةً بدل خانةٍ فارغة تُقرأ خطأً.

    @param int|null $branchId  عقدة المستند (`branch_id`)
--}}

@php
    $nodePathValue = \App\Models\Branch::pathLabelFor($branchId ?? null);
    $nodePathParts = $nodePathValue ? explode(' ← ', $nodePathValue) : [];
    $nodePathLeaf = array_pop($nodePathParts);
@endphp

@if ($nodePathValue)
    <span class="branch-node-path" title="{{ $nodePathValue }}">
        @foreach ($nodePathParts as $ancestor)
            <span class="branch-node-path__ancestor text-muted">{{ $ancestor }}</span>
            <span class="branch-node-path__arrow text-muted">←</span>
        @endforeach
        <span class="branch-node-path__leaf font-weight-bold">{{ $nodePathLeaf }}</span>
    </span>
@else
    <span class="text-muted">{{ __('branches.node_filter.unassigned') }}</span>
@endif
