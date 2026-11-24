{{--
    حقولُ نموذج الماكينة — مشتركةٌ بين «إضافة» و«تعديل».
    يستقبل: $machine (null عند الإضافة). المعرّفات (name / machine_type /
    host_name / port_number) يقرؤها سكربت التحقّق في الصفحتين، فلا تُغيَّر.
--}}
@php $machine = $machine ?? null; @endphp

@if ($errors->any())
    <div class="stk-form-errors alert alert-danger" role="alert">
        <div class="stk-form-errors__head">
            <i class="feather icon-alert-circle"></i> لم تُحفظ الماكينة — راجع الحقول المعلَّمة:
        </div>
        <ul class="stk-form-errors__list mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-header"><h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4></div>
    <div class="card-body">
        <div class="row">
            <!-- اسم الماكينة -->
            <div class="col-md-6 col-xl-4 mb-2">
                <label for="name" class="form-label stk-req">اسم الماكينة</label>
                <input
                    type="text"
                    class="form-control @error('name') is-invalid @enderror"
                    id="name"
                    name="name"
                    value="{{ old('name', $machine->name ?? '') }}"
                    placeholder="أدخل اسم الماكينة"
                    required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- نوع الماكينة -->
            <div class="col-md-6 col-xl-4 mb-2">
                <label for="machine_type" class="form-label stk-req">نوع الماكينة</label>
                @php $machineTypeValue = old('machine_type', $machine->machine_type ?? null); @endphp
                <select class="form-control select2 @error('machine_type') is-invalid @enderror" id="machine_type" name="machine_type" required>
                    <option value="">اختر نوع الماكينة</option>
                    <option value="zkteco" {{ $machineTypeValue == 'zkteco' ? 'selected' : '' }}>ZkTeco</option>
                    <option value="hikvision" {{ $machineTypeValue == 'hikvision' ? 'selected' : '' }}>Hikvision</option>
                    <option value="suprema" {{ $machineTypeValue == 'suprema' ? 'selected' : '' }}>Suprema</option>
                    <option value="other" {{ $machineTypeValue == 'other' ? 'selected' : '' }}>أخرى</option>
                </select>
                @error('machine_type')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- الرقم التسلسلي -->
            <div class="col-md-6 col-xl-4 mb-2">
                <label for="serial_number" class="form-label">الرقم التسلسلي</label>
                <input
                    type="text"
                    class="form-control @error('serial_number') is-invalid @enderror"
                    id="serial_number"
                    name="serial_number"
                    value="{{ old('serial_number', $machine->serial_number ?? '') }}"
                    placeholder="أدخل الرقم التسلسلي">
                @error('serial_number')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- الحالة -->
            <div class="col-12">
                <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="status"
                        name="status"
                        value="1"
                        {{ old('status', $machine ? $machine->status : true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="status">
                        تفعيل الماكينة
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h4 class="card-title"><i class="feather icon-wifi"></i> بيانات الاتصال</h4></div>
    <div class="card-body">
        <div class="row">
            <!-- المضيف -->
            <div class="col-md-6 col-xl-4 mb-2">
                <label for="host_name" class="form-label stk-req">المضيف</label>
                <input
                    type="text"
                    class="form-control @error('host_name') is-invalid @enderror"
                    id="host_name"
                    name="host_name"
                    value="{{ old('host_name', $machine->host_name ?? '') }}"
                    placeholder="مثال: 192.168.1.100 أو dns.website.com"
                    required>
                @error('host_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- رقم المنفذ -->
            <div class="col-md-6 col-xl-3 mb-2">
                <label for="port_number" class="form-label stk-req">رقم المنفذ</label>
                <input
                    type="number"
                    class="form-control @error('port_number') is-invalid @enderror"
                    id="port_number"
                    name="port_number"
                    value="{{ old('port_number', $machine ? $machine->port_number : '4022') }}"
                    placeholder="4022"
                    min="1"
                    max="65535"
                    @if ($machine) required @endif>
                @error('port_number')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- مفتاح الاتصال -->
            <div class="col-md-6 col-xl-4 mb-2">
                <label for="connection_key" class="form-label">مفتاح الاتصال</label>
                <input
                    type="text"
                    class="form-control @error('connection_key') is-invalid @enderror"
                    id="connection_key"
                    name="connection_key"
                    value="{{ old('connection_key', $machine->connection_key ?? '') }}"
                    placeholder="أدخل مفتاح الاتصال (اختياري)">
                @error('connection_key')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>
