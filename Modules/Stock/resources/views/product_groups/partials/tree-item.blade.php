<div class="tree-item level-{{ $level }}">
    <div class="group-card">
        <!-- زر التوسيع -->
        @if($group->children->count() > 0)
            <span class="expand-btn" data-id="{{ $group->id }}">
                <i class="bx bx-plus"></i>
            </span>
        @endif

        <div class="d-flex justify-content-between align-items-center">
            <div class="group-info">
                <!-- أيقونة المجلد -->
                <div class="folder-icon">
                    <i class="bx bx-folder{{ $group->children->count() > 0 ? '-open' : '' }}"></i>
                </div>

                <div>
                    <!-- اسم المجموعة -->
                    <h5 class="group-name">
                        {{ $group->name }}
                        @if($group->code)
                            <span class="group-code">({{ $group->code }})</span>
                        @endif
                    </h5>

                    <!-- الشارات -->
                    <div class="group-badges">
                        <span class="badge-custom badge-level">
                            <i class="bx bx-layer"></i> مستوى {{ $group->level }}
                        </span>
                        
                        @if($group->parent)
                            <span class="badge-custom badge-parent">
                                <i class="bx bx-link"></i> {{ $group->parent->name }}
                            </span>
                        @endif
                        
                        @if($group->classifications->count() > 0)
                            <span class="badge-custom badge-classifications">
                                <i class="bx bx-category"></i> {{ $group->classifications->count() }} تصنيف
                            </span>
                        @endif

                        <span class="badge-custom badge-{{ $group->is_active ? 'active' : 'inactive' }}">
                            <i class="bx bx-{{ $group->is_active ? 'check-circle' : 'x-circle' }}"></i>
                            {{ $group->is_active ? 'نشط' : 'غير نشط' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- أزرار العمليات -->
            <div class="group-actions">
                <a href="{{ route('product_groups.edit', $group->id) }}" 
                   class="btn-action btn-edit" 
                   title="تعديل">
                    <i class="bx bx-edit"></i>
                </a>
                
                <button type="button" 
                        class="btn-action btn-toggle {{ $group->is_active ? '' : 'inactive' }} toggle-status" 
                        data-id="{{ $group->id }}"
                        title="تغيير الحالة">
                    <i class="bx bx-{{ $group->is_active ? 'check' : 'x' }}"></i>
                </button>
                
                <form action="{{ route('product_groups.delete', $group->id) }}" 
                      method="POST" 
                      class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="button" 
                            class="btn-action btn-delete delete-group" 
                            data-id="{{ $group->id }}"
                            title="حذف">
                        <i class="bx bx-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- المجموعات الفرعية -->
    @if($group->children->count() > 0)
        <div class="children-container">
            @foreach($group->children as $child)
                @include('stock::product_groups.partials.tree-item', ['group' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
