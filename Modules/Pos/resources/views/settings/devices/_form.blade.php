{{--
    نموذجُ جهاز الكاشير — مشتركٌ بين الإضافة والتعديل.

    ═══════════════ لماذا قالبٌ واحد ═══════════════

    كانت `create` و`edit` نسختين متطابقتين تقريباً — ٣٧١ و٣٨٣ سطراً يفترقان
    في ٧١ سطراً فقط، أكثرُها العنوانُ ومسارُ الحفظ. والنسختان تتباعدان بأوّل
    حقلٍ يُضاف لإحداهما: يمرّ في الإنشاء ويُرفض في التعديل، أو يُضبط ثم
    يختفي عند أوّل تحرير.

    والحقولُ الأربعةُ الأخيرة (`serial_number`، `ip_address`،
    `max_cash_in_drawer`، `default_printer_id`) مثالٌ حيّ: يتحقّق منها
    `StoreDeviceRequest` و`UpdateDeviceRequest`، وكانت في نموذجٍ دون الآخر.

    الوسائط:
      $device  سجلُّ الجهاز عند التعديل، أو null عند الإضافة
--}}
@php
    $device = $device ?? null;

    $statusHints = [
        'active' => 'يُستعمل في البيع وتُفتح عليه الجلسات',
        'inactive' => 'موقوفٌ مؤقّتاً — لا تُفتح عليه جلسة',
        'maintenance' => 'تحت الصيانة',
        'damaged' => 'تالفٌ خارج الخدمة',
    ];
@endphp

<div class="row">

    {{-- ═══════════ التعريف ═══════════ --}}
    <div class="col-12">
        <h6 class="stk-section">التعريف</h6>
    </div>

    <div class="col-md-6 col-sm-12">
        <div class="form-group">
            <label for="device_name" class="stk-req">اسم الجهاز</label>
            <input type="text" name="device_name" id="device_name" required minlength="3" maxlength="255"
                   class="form-control @error('device_name') is-invalid @enderror"
                   value="{{ old('device_name', $device->device_name ?? '') }}"
                   placeholder="كاشير الصالة الأمامي">
            @error('device_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-6 col-sm-12">
        <div class="form-group">
            <label for="device_status" class="stk-req">الحالة</label>
            <select name="device_status" id="device_status"
                    class="form-control @error('device_status') is-invalid @enderror" required>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}"
                        @selected(old('device_status', $device->device_status ?? 'active') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('device_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">{{ $statusHints['active'] }} — وغيرُ النشط لا تُفتح عليه جلسة.</p>
        </div>
    </div>

    <div class="col-12">
        <div class="form-group">
            <label for="description">الوصف</label>
            <textarea name="description" id="description" rows="2" maxlength="1000"
                      class="form-control @error('description') is-invalid @enderror"
                      placeholder="موقعُ الجهاز أو ما يميّزه">{{ old('description', $device->description ?? '') }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    {{-- ═══════════ الارتباط ═══════════ --}}
    <div class="col-12">
        <h6 class="stk-section">الفرع والمستودع والمسؤول</h6>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label for="branch_id" class="stk-req">الفرع</label>

            @if ($canSelectBranch)
                <select name="branch_id" id="branch_id"
                        class="form-control select2 @error('branch_id') is-invalid @enderror" required>
                    <option value="">اختر الفرع…</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}"
                            @selected(old('branch_id', $device->branch_id ?? $currentBranchId) == $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            @else
                {{--
                    من لا يختار الفرع يُثبَّت على فرعه: عرضُ قائمةٍ لا يملك
                    إلا خياراً واحداً فيها يوحي بخيارٍ ليس له.
                --}}
                <input type="text" class="form-control" readonly
                       value="{{ $branches->firstWhere('id', $device->branch_id ?? $currentBranchId)->name ?? '—' }}">
                <input type="hidden" name="branch_id" id="branch_id" value="{{ $device->branch_id ?? $currentBranchId }}">
                <p class="stk-help mb-0">الفرع يُحدَّد من سجلّك الوظيفي.</p>
            @endif

            @error('branch_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            <x-create-first :count="$canSelectBranch ? $branches->count() : 1" route="branches.create"
                permission="branches.create" label="فرع"
                hint="الجهاز يتبع فرعاً، ولا فروع متاحة لك." />
        </div>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label for="store_id" class="stk-req">المستودع</label>
            <select name="store_id" id="store_id"
                    class="form-control select2 @error('store_id') is-invalid @enderror" required>
                <option value="">اختر المستودع…</option>
                @foreach ($storehouses as $storehouse)
                    {{-- `data-branch` هو ما تُصفّى به القائمة — انظر [_branch_employees] --}}
                    <option value="{{ $storehouse->id }}"
                        data-branch="{{ $storehouse->branch_id ?? '' }}"
                        @selected(old('store_id', $device->store_id ?? '') == $storehouse->id)>
                        {{ $storehouse->name }}
                    </option>
                @endforeach
            </select>
            @error('store_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">منه يُخصَم المبيعُ ومنه تُقرأ الأرصدة.</p>

            {{-- بلا مستودعٍ لا يُباع من الجهاز: لا يُعرف من أين تُخصم البضاعة --}}
            <x-create-first :count="$storehouses->count()" route="storehouse.create"
                permission="stock.warehouses.create"
                :plan="\App\Constants\planFeatures\StockPlanFeatures::STOREHOUSES"
                label="مستودع"
                hint="الجهاز يبيع من مستودع، ولا مستودعات مسجَّلة بعد." />
        </div>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label for="user_id" class="stk-req">الموظف المسؤول</label>
            <select name="user_id" id="user_id"
                    class="form-control select2 @error('user_id') is-invalid @enderror" required>
                <option value="">اختر الموظف…</option>
                @foreach ($users as $user)
                    {{-- `data-branch` هو ما تُصفّى به القائمة — انظر [_branch_employees] --}}
                    <option value="{{ $user->id }}"
                        data-branch="{{ $user->employee->branch_id ?? '' }}"
                        @selected(old('user_id', $device->user_id ?? '') == $user->id)>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
            @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror

            <x-create-first :count="$users->count()" route="employee.create" label="موظّف"
                hint="لا موظفين لهم حسابُ دخول بعد." />
        </div>
    </div>

    {{-- ═══════════ التشغيل ═══════════ --}}
    <div class="col-12">
        <h6 class="stk-section">التشغيل</h6>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label for="serial_number">الرقم التسلسلي</label>
            <input type="text" name="serial_number" id="serial_number" maxlength="100"
                   class="form-control @error('serial_number') is-invalid @enderror"
                   value="{{ old('serial_number', $device->serial_number ?? '') }}">
            @error('serial_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">يُميّز الجهازَ عند التدقيق والجرد.</p>
        </div>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label for="ip_address">العنوان الشبكي</label>
            <input type="text" name="ip_address" id="ip_address" dir="ltr"
                   class="form-control @error('ip_address') is-invalid @enderror"
                   value="{{ old('ip_address', $device->ip_address ?? '') }}"
                   placeholder="192.168.1.20">
            @error('ip_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-4 col-sm-6">
        <div class="form-group">
            <label for="max_cash_in_drawer">حدّ النقد في الدرج</label>
            <input type="number" step="0.01" min="0" name="max_cash_in_drawer" id="max_cash_in_drawer"
                   class="form-control @error('max_cash_in_drawer') is-invalid @enderror"
                   value="{{ old('max_cash_in_drawer', $device->max_cash_in_drawer ?? '') }}"
                   placeholder="اتركه فارغاً لبلا حدّ">
            @error('max_cash_in_drawer')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">تجاوزُه ينبّه إلى الترحيل للخزينة.</p>
        </div>
    </div>

    <div class="col-md-6 col-sm-12">
        <div class="form-group">
            <label for="default_printer_id">الطابعة الافتراضية</label>
            <select name="default_printer_id" id="default_printer_id"
                    class="form-control select2 @error('default_printer_id') is-invalid @enderror">
                <option value="">الطابعة العامّة</option>
                @foreach ($printers as $printer)
                    <option value="{{ $printer->id }}"
                        @selected(old('default_printer_id', $device->default_printer_id ?? '') == $printer->id)>
                        {{ $printer->name }}
                    </option>
                @endforeach
            </select>
            @error('default_printer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror

            {{--
                كان هنا ترميزٌ يدويّ لا يعرف الباقة ولا يفرّق بين «لا صلاحية»
                و«خارج الباقة» — يعرض زراً يقود إلى 402 أو 403. والمكوّن
                المشترك يحسم الثلاث حالات.
            --}}
            <x-create-first :count="$printers->count()" route="pos.settings.printers.create"
                permission="pos.printers.manage"
                :plan="\App\Constants\planFeatures\PosPlanFeatures::PRINTERS"
                label="طابعة"
                hint="بلا طابعةٍ مسجَّلة يُطبع الإيصال من حوار المتصفّح." />
        </div>
    </div>

    <div class="col-md-6 col-sm-12">
        <div class="form-group">
            <label for="device_image">صورة الجهاز</label>
            <input type="file" name="device_image" id="device_image" accept="image/*"
                   class="form-control @error('device_image') is-invalid @enderror">
            @error('device_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <p class="stk-help mb-0">jpeg أو png أو webp، بحدّ 5 ميغابايت.</p>

            @if ($device?->device_image)
                <div class="stk-preview mt-1">
                    <img src="{{ $device->image_url }}" alt="{{ $device->device_name }}">
                    <span class="stk-help">الصورةُ الحالية — رفعُ غيرِها يستبدلها.</span>
                </div>
            @endif
        </div>
    </div>
</div>

{{--
    تصفيةُ الموظفين بالفرع المختار. تُوضع بعد الحقلين معاً — السكربت يقرأ
    العنصرين بمعرّفيهما، ولا يجدهما إن سبقهما.
--}}
@include('pos::settings._branch_employees', ['selectId' => 'user_id', 'branchId' => 'branch_id'])

{{--
    والمستودعُ كذلك: جهازُ الفرع يبيع من مستودع الفرع، وعرضُ مستودعات
    الفروع الأخرى يتيح ربطاً يُخصم به بيعُ فرعٍ من مخزن آخر.
--}}
@include('pos::settings._branch_employees', [
    'selectId' => 'store_id',
    'branchId' => 'branch_id',
    'emptyText' => 'لا مستودعات في هذا الفرع',
])
