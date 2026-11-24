@php
    $isParent = $center->children->isNotEmpty();
    $childCount = $center->children_count ?? $center->children->count();
@endphp

{{--
    صفٌّ في الجدول الشجريّ.

    المستوى يُرسَم إزاحةً محسوبة لا بفئة CSS لكلّ عمق: شجرةُ مراكز التكلفة
    تعمق بلا حدّ في القاعدة، فأيُّ عددٍ من الفئات المُعدّة سلفاً ينفد.

    والأبناء يبدأون مطويّين (`hidden` لما فوق الجذر): شجرةٌ تُفتح كاملةً
    ليست شجرةً بل قائمةٌ طويلة.
--}}
<tr class="stk-tree__row {{ $isParent ? 'is-parent' : '' }}"
    data-node-id="{{ $center->id }}"
    data-id="{{ $center->id }}"
    data-parent="{{ $center->parent_id ?? '' }}"
    data-level="{{ $level }}"
    data-expanded="false"
    data-has-children="{{ $isParent ? 'true' : 'false' }}"
    data-name="{{ $center->name }}"
    data-code="{{ $center->code }}"
    @if ($level > 0) hidden @endif>

    <td>
        <div class="stk-tree__cell" style="padding-inline-start: {{ $level * 22 }}px">
            @if ($isParent)
                <button type="button" class="stk-tree__toggle" data-id="{{ $center->id }}"
                    aria-expanded="false" aria-label="طيّ أو بسط">
                    <i class="feather icon-chevron-down"></i>
                </button>
            @else
                <span class="stk-tree__toggle is-empty" aria-hidden="true"></span>
            @endif

            <span class="stk-tree__icon {{ $isParent ? 'is-parent' : '' }}">
                <i class="feather icon-{{ $isParent ? 'folder' : 'git-branch' }}"></i>
            </span>

            <span class="stk-tree__name {{ $isParent ? 'is-parent' : '' }}">{{ $center->name }}</span>
        </div>
    </td>

    <td class="text-center">
        @if ($center->code)
            <span class="badge badge-secondary stk-num">{{ $center->code }}</span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>

    <td class="text-center">
        {{-- «رئيسيٌّ» يعني بلا أب — وهي الحقيقةُ نفسُها التي يشتقّها الخادم --}}
        @if ($center->parent_id)
            <span class="stk-state stk-state--neutral">فرعي</span>
        @else
            <span class="stk-state stk-state--on">رئيسي</span>
        @endif
    </td>

    <td class="text-center">
        @if ($childCount)
            <span class="badge badge-light-primary stk-num">{{ $childCount }}</span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>

    <td class="text-center">
        <div class="stk-actions">
            {{--
                الصلاحياتُ تصل رايةً محسوبةً مرّةً واحدة للصفحة (`buttonPermissions`).

                و`@permission` يُطلق استعلاماً في كلّ استدعاء، وهو هنا على أربعة
                أزرارٍ في كلّ صفّ: شجرةٌ من مئتَي مركزٍ ثمانمئةُ استعلام — والجوابُ
                هو هو في كلّ صفّ، إذ الصلاحيةُ للمستخدم لا للمركز.
            --}}
            @if ($can['view'])
                <a href="{{ route('cost_centers.view', $center->id) }}"
                    class="btn btn-outline-secondary" title="عرض" aria-label="عرض">
                    <i class="feather icon-eye"></i>
                </a>
            @endif

            @if ($can['edit'])
                <button type="button" class="btn btn-outline-primary edit-button"
                    data-id="{{ $center->id }}" title="تعديل" aria-label="تعديل">
                    <i class="feather icon-edit-2"></i>
                </button>
            @endif

            @if ($can['create'])
                {{--
                    إضافةُ فرعٍ من موضعه في الشجرة: كان الأبُ يُلتقط من العقدة
                    المختارة في شجرةٍ جانبية، فمن لم ينقرها أضاف جذراً وهو
                    يظنّ أنه يضيف فرعاً.
                --}}
                <button type="button" class="btn btn-outline-success add-child"
                    data-id="{{ $center->id }}" data-name="{{ $center->name }}"
                    title="إضافة فرع تحته" aria-label="إضافة فرع تحته">
                    <i class="feather icon-plus"></i>
                </button>
            @endif

            @if ($can['delete'])
                <button type="button" class="btn btn-outline-danger delete-center"
                    data-id="{{ $center->id }}" data-name="{{ $center->name }}"
                    data-children="{{ $childCount }}" title="حذف" aria-label="حذف">
                    <i class="feather icon-trash-2"></i>
                </button>
            @endif
        </div>
    </td>
</tr>

@foreach ($center->children as $child)
    @include('account::cost_centers.partials.tree-row', [
        'center' => $child,
        'level' => $level + 1,
        'can' => $can,
    ])
@endforeach
