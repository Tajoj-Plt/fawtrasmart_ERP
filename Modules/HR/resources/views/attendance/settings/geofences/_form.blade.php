{{--
    نموذجُ مكان التسجيل — للإنشاء والتعديل معاً.

    المكانُ يُختار من الخريطة (`map-picker`) لا بكتابة خطّ العرض والطول، ونصفُ
    القطر يرسم دائرته عليها. وشروطُ الشبكة (عناوين IP وشبكات Wi‑Fi) في «إعدادات
    متقدمة» — لا يحتاجها أغلبُ المستخدمين، وكانت تتصدّر النموذج.
--}}
@php $geofence = $geofence ?? null; $formKey = $geofence->id ?? 'new'; @endphp
@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم يُحفظ المكان — راجع ما يلي:
        </div>
        <ul class="stk-form-errors__list mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="card">
<div class="card-header"><h4 class="card-title"><i class="feather icon-map-pin"></i> بيانات المكان</h4></div>
<div class="card-body">
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label stk-req">اسم المكان</label>
            <input type="text" name="name" class="form-control" required
                   value="{{ old('name', $geofence->name ?? '') }}" placeholder="مثال: فرع الرياض — المقر الرئيسي">
        </div>
        <div class="col-md-6 mb-3">
            @include('reports.partials.affiliation-tree', [
                'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes([$geofence->branch_id ?? null]),
                'name' => 'branch_id',
                'fieldId' => 'gf_branch_'.$formKey,
                'value' => old('branch_id', $geofence->branch_id ?? ''),
                'showExact' => false,
                'label' => 'تابع لفرع',
            ])
        </div>
    </div>

    <label class="form-label stk-req">المكان على الخريطة</label>
    @include('hr::attendance._partials.map-picker', [
        'id' => 'gf_map_'.$formKey,
        'lat' => $geofence->latitude ?? null,
        'lng' => $geofence->longitude ?? null,
        'radiusInput' => '#gf_radius_'.$formKey,
    ])

    <div class="row mt-3">
        <div class="col-md-6 mb-3">
            <label class="form-label stk-req">نصف قطر المكان (بالمتر)</label>
            <input type="number" id="gf_radius_{{ $formKey }}" name="radius_meters" class="form-control" min="20" max="50000" required
                   value="{{ old('radius_meters', $geofence->radius_meters ?? 100) }}">
            <small class="stk-help">من يسجّل من الجوال خارج هذه الدائرة يُرفض تسجيله. ابدأ بـ 100 متر.</small>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label stk-req">أقل دقة مقبولة لموقع الجوال (بالمتر)</label>
            <input type="number" name="max_accuracy_meters" class="form-control" min="10" max="5000" required
                   value="{{ old('max_accuracy_meters', $geofence->max_accuracy_meters ?? 100) }}">
            <small class="stk-help">إن كان موقع الجوال غير دقيق بأكثر من هذا، يُطلب من الموظف إعادة المحاولة.</small>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-4 mb-3">
        <label class="m-0">
            <input type="checkbox" name="require_geo" value="1"
                   @checked(old('require_geo', $geofence->require_geo ?? true))>
            اشترط أن يكون الجوال داخل الدائرة
        </label>
        <label class="m-0">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $geofence->is_active ?? true))>
            مفعّل
        </label>
    </div>

    <details class="mb-3" @if ($errors->hasAny(['allowed_ip_ranges', 'allowed_wifi_ssids'])) open @endif>
        <summary class="stk-help" style="cursor:pointer">إعدادات متقدمة — شبكة المكتب</summary>
        <div class="row mt-2">
            <div class="col-md-6 mb-3">
                <label class="form-label">عناوين شبكة المكتب (IP)</label>
                <textarea name="allowed_ip_ranges" class="form-control" rows="3"
                          placeholder="192.168.1.0/24">{{ old('allowed_ip_ranges', $geofence ? implode("\n", (array) $geofence->allowed_ip_ranges) : '') }}</textarea>
                <small class="stk-help">سطر لكل عنوان أو نطاق. يطلبه منك مسؤول الشبكة.</small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">أسماء شبكات Wi‑Fi</label>
                <textarea name="allowed_wifi_ssids" class="form-control" rows="3"
                          placeholder="Office-WiFi">{{ old('allowed_wifi_ssids', $geofence ? implode("\n", (array) $geofence->allowed_wifi_ssids) : '') }}</textarea>
            </div>
            <div class="col-12">
                <label class="m-0">
                    <input type="checkbox" name="require_network" value="1"
                           @checked(old('require_network', $geofence->require_network ?? false))>
                    اشترط أن يكون التسجيل من شبكة المكتب
                </label>
            </div>
        </div>
    </details>

    <div class="mb-3">
        <label class="form-label">ملاحظات</label>
        <input type="text" name="notes" class="form-control" maxlength="1000"
               value="{{ old('notes', $geofence->notes ?? '') }}">
    </div>

</div>
</div>
