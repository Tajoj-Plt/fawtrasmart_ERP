@php
    $isParent = $group->children->count() > 0;
@endphp

<tr class="stk-tree__row {{ $isParent ? 'is-parent' : '' }}"
    data-id="{{ $group->id }}"
    data-parent="{{ $group->parent_id ?? '' }}"
    data-level="{{ $level }}"
    data-expanded="false"
    data-has-children="{{ $isParent ? 'true' : 'false' }}"
    @if ($level > 0) hidden @endif>

    <td>
        {{-- المستوى يُرسَم إزاحةً لا بفئة CSS لكل عمق: الشجرة قد تعمق بلا حدّ --}}
        <div class="stk-tree__cell" style="padding-inline-start: {{ $level * 22 }}px">
            @if ($isParent)
                <button type="button" class="stk-tree__toggle" data-id="{{ $group->id }}"
                        aria-expanded="false" aria-label="طيّ أو بسط">
                    <i class="feather icon-chevron-down"></i>
                </button>
            @else
                <span class="stk-tree__toggle is-empty" aria-hidden="true"></span>
            @endif

            <span class="stk-tree__icon {{ $isParent ? 'is-parent' : '' }}">
                <i class="feather icon-{{ $isParent ? 'folder' : 'file' }}"></i>
            </span>

            <span class="stk-tree__name {{ $isParent ? 'is-parent' : '' }}">{{ $group->name }}</span>
        </div>
    </td>

    <td class="text-center">
        @if ($group->code)
            <span class="badge badge-secondary stk-num">{{ $group->code }}</span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>

    <td class="text-center stk-num">{{ $group->level }}</td>

    <td class="text-center">
        @if ($group->classifications->count())
            <span class="badge badge-light-primary stk-num">{{ $group->classifications->count() }}</span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>

    <td class="text-center">
        <span class="stk-state stk-state--{{ $group->is_active ? 'on' : 'off' }}">
            {{ $group->is_active ? 'نشط' : 'غير نشط' }}
        </span>
    </td>

    <td class="text-center">
        <div class="stk-actions">
            @permission('stock.product_groups.edit')
            <a href="{{ route('product_groups.edit', $group->id) }}"
               class="btn btn-outline-primary" title="تعديل" aria-label="تعديل">
                <i class="feather icon-edit-2"></i>
            </a>
            @endpermission
            @permission('stock.product_groups.toggle_status')
            <button type="button" class="btn btn-outline-warning toggle-status"
                    data-id="{{ $group->id }}"
                    title="{{ $group->is_active ? 'تعطيل' : 'تفعيل' }}"
                    aria-label="{{ $group->is_active ? 'تعطيل' : 'تفعيل' }}">
                <i class="feather icon-{{ $group->is_active ? 'slash' : 'check' }}"></i>
            </button>
            @endpermission
            @permission('stock.product_groups.delete')
            <button type="button" class="btn btn-outline-danger delete-group"
                    data-id="{{ $group->id }}"
                    data-name="{{ $group->name }}"
                    data-children="{{ $group->children->count() }}"
                    title="حذف" aria-label="حذف">
                <i class="feather icon-trash-2"></i>
            </button>
            @endpermission
        </div>
    </td>
</tr>

@if ($isParent)
    @foreach ($group->children as $child)
        @include('stock::product_groups.partials.tree-row', ['group' => $child, 'level' => $level + 1])
    @endforeach
@endif
