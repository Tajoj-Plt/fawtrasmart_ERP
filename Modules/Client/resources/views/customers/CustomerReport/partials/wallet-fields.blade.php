{{-- حقولُ العميل في شبكة فلترة تقرير المحافظ — بأسماء فلاتر تقارير العملاء (`FiltersCustomerReports`) --}}
<div class="rpt-field">
    @include('reports.partials.client-select', [
        'name' => 'customer',
        'selectedClient' => $selectedClient ?? null,
        'label' => 'العميل',
    ])
</div>

<div class="rpt-field">
    <label for="status">حالة العميل</label>
    <select name="status" id="status" class="form-control rpt-select2" data-placeholder="كل الحالات">
        <option value="">كل الحالات</option>
        @foreach ($statuses ?? [] as $status)
            <option value="{{ $status->id }}" @selected((string) request('status') === (string) $status->id)>{{ $status->name }}</option>
        @endforeach
    </select>
</div>

<div class="rpt-field">
    <label for="category_client">تصنيف العميل</label>
    <select name="category_client" id="category_client" class="form-control rpt-select2" data-placeholder="جميع التصنيفات">
        <option value="">جميع التصنيفات</option>
        @foreach ($categories ?? [] as $category)
            <option value="{{ $category->id }}" @selected((string) request('category_client') === (string) $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
</div>
