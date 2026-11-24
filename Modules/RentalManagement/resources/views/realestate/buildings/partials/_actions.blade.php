<div class="dropdown">
    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات {{ $building->name }}">
        <i class="feather icon-more-vertical"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-end">
        @permission('realestate.buildings.show')
            <a class="dropdown-item" href="{{ route('realestate.buildings.show', $building->id) }}"><i class="feather icon-eye"></i> عرض</a>
        @endpermission
        @permission('realestate.buildings.edit')
            <a class="dropdown-item" href="{{ route('realestate.buildings.edit', $building->id) }}"><i class="feather icon-edit"></i> تعديل</a>
        @endpermission
        @permission('realestate.units.create')
            <a class="dropdown-item" href="{{ route('realestate.units.create', ['building_id' => $building->id]) }}"><i class="feather icon-plus"></i> إضافة وحدة</a>
        @endpermission
        @permission('realestate.buildings.delete')
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-danger re-action" href="#" data-url="{{ route('realestate.buildings.destroy', $building->id) }}"
               data-method="DELETE" data-tone="danger" data-confirm="حذف العقار «{{ $building->name }}» ووحداته؟">
                <i class="feather icon-trash-2"></i> حذف
            </a>
        @endpermission
    </div>
</div>
