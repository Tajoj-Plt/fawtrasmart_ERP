{{-- حقولُ المورد في شبكة فلترة تقرير المحافظ — أصحابُ المحافظ وحدَهم، على فرع الشريط العلويّ --}}
<div class="rpt-field">
    <label for="supplier_id">المورد</label>
    <select name="supplier_id" id="supplier_id" class="form-control rpt-select2" data-placeholder="كل الموردين">
        <option value="">كل الموردين</option>
        @foreach ($supplierOptions ?? [] as $supplierOption)
            <option value="{{ $supplierOption->id }}" @selected((string) request('supplier_id') === (string) $supplierOption->id)>
                {{ $supplierOption->trade_name ?: trim($supplierOption->first_name.' '.$supplierOption->last_name) }}{{ $supplierOption->number_suply ? ' — '.$supplierOption->number_suply : '' }}
            </option>
        @endforeach
    </select>
</div>

<div class="rpt-field">
    <label for="status">حالة المورد</label>
    <select name="status" id="status" class="form-control rpt-select2" data-placeholder="كل الحالات">
        <option value="">كل الحالات</option>
        <option value="active" @selected(request('status') === 'active')>نشط</option>
        <option value="inactive" @selected(request('status') === 'inactive')>موقوف</option>
    </select>
</div>
