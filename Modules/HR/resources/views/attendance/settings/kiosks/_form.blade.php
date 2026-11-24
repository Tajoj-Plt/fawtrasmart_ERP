{{--
    نموذجُ جهاز التسجيل — للإضافة والتعديل.
    رمزُ الربط لا يُكتب هنا: يُولَّد عند الحفظ، ويُجدَّد من شاشة العرض.
--}}
@php
    $kiosk = $kiosk ?? null;
    $formKey = $kiosk->id ?? 'new';
    $selectedGeofence = old('geofence_id', $kiosk->geofence_id ?? request('geofence_id'));
@endphp
@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم يُحفظ الجهاز — راجع ما يلي:
        </div>
        <ul class="stk-form-errors__list mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="card">
    <div class="card-header"><h4 class="card-title"><i class="feather icon-tablet"></i> بيانات الجهاز</h4></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label stk-req">اسم الجهاز</label>
                <input type="text" name="name" class="form-control" required maxlength="255"
                       value="{{ old('name', $kiosk->name ?? '') }}" placeholder="مثال: تابلت الاستقبال — فرع الرياض">
                <small class="stk-help">اسمٌ يعرف به المسؤول مكان الجهاز.</small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">المكان الموجود فيه</label>
                <select name="geofence_id" class="form-control hr-s2">
                    <option value="">— بدون مكان —</option>
                    @foreach ($geofences as $geofence)
                        <option value="{{ $geofence->id }}" @selected((string) $selectedGeofence === (string) $geofence->id)>{{ $geofence->name }}</option>
                    @endforeach
                </select>
                <small class="stk-help">التسجيل على هذا الجهاز يُحسب في هذا المكان.</small>
            </div>
            <div class="col-md-6 mb-3">
                @include('reports.partials.affiliation-tree', [
                    'treeNodes' => \Modules\HR\Support\BranchTree::treeNodes([$kiosk->branch_id ?? null]),
                    'name' => 'branch_id',
                    'fieldId' => 'kiosk_branch_'.$formKey,
                    'value' => old('branch_id', $kiosk->branch_id ?? ''),
                    'showExact' => false,
                    'label' => 'تابع لفرع',
                ])
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">تسجيل الموظف بمسح «رمز المكتب» من جواله</label>
                <select name="office_qr" class="form-control">
                    @foreach (\App\Services\Attendance\OfficeCode::MODES as $mode => $label)
                        <option value="{{ $mode }}" @selected(old('office_qr', $kiosk->office_qr ?? 'off') === $mode)>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="stk-help">المتغيّر: يظهر على شاشة الجهاز ويتغيّر كل 30 ثانية، فيمسحه الموظف من «سجّل حضوري». المطبوع: ملصق ثابت يُقبل فقط وموقع الجوال داخل المكان.</small>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">ملاحظات</label>
                <input type="text" name="notes" class="form-control" maxlength="1000" value="{{ old('notes', $kiosk->notes ?? '') }}">
            </div>
        </div>

        {{--
            الجهازُ المربوطُ بمكانٍ يُقبل عليه المسحُ بلا إحداثيات: مكانُه
            معلومٌ بالربط نفسه، وأغلبُه شاشةٌ ثابتةٌ بلا GPS. ومن كان جهازُه
            جوّالاً أو لوحاً متنقّلاً يشترط الموقعَ من هنا، فيُفحَص نصفُ قطر
            المكان ودقّةُ القراءة كما يُفحَص للجوال.
        --}}
        <label class="m-0 d-block mb-2">
            <input type="checkbox" name="require_location" value="1" @checked(old('require_location', $kiosk->require_location ?? false))>
            يُشترَط الموقع على هذا الجهاز — لا حضورَ ولا انصرافَ إلا والإحداثيات داخل «المكان الموجود فيه»
        </label>
        <small class="stk-help d-block mb-3">
            مطفأً (الافتراض): يكفي أن يكون المسحُ من هذا الجهاز المقترن — وهو الصحيح للشاشة الثابتة بلا GPS.
            مشغَّلاً: يلزم الموقع، ويحتاج اختيارَ «المكان الموجود فيه» أعلاه.
        </small>

        <label class="m-0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $kiosk->is_active ?? true))>
            مفعّل — الجهاز الموقوف لا يقبل أي تسجيل
        </label>
    </div>
</div>
