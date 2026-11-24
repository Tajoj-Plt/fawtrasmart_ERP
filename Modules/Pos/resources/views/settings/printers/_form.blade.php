{{--
    نموذجُ الطابعة — مشتركٌ بين الإضافة والتعديل.

    قالبٌ واحد لا نسختان: النسختان تتباعدان بأول حقلٍ يُضاف لإحداهما،
    فيمرّ في الإنشاء ويُرفض في التعديل. وهي العلّةُ التي كانت في نماذج
    الأجهزة والورديات قبل نقل تحقّقها إلى `Http/Requests`.
--}}
@php $printer = $printer ?? null; @endphp

<div class="row g-1">

    <div class="col-md-6 col-12">
        <label class="form-label stk-req" for="name">اسم الطابعة</label>
        <input type="text" name="name" id="name" required maxlength="120"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $printer->name ?? '') }}"
               placeholder="طابعة الكاشير الأمامي">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 col-12">
        <label class="form-label stk-req" for="type">النوع</label>
        <select name="type" id="type" class="form-control @error('type') is-invalid @enderror" required>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected(old('type', $printer->type ?? 'receipt') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <p class="stk-help mb-0">طابعةُ المطبخ تطبع نسخةَ التحضير لا نسخةَ العميل.</p>
    </div>

    <div class="col-md-6 col-12">
        <label class="form-label stk-req" for="connection">الاتصال</label>
        <select name="connection" id="connection" class="form-control" required>
            @foreach ($connections as $key => $label)
                <option value="{{ $key }}" @selected(old('connection', $printer->connection ?? 'usb') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 col-12">
        <label class="form-label" for="address">العنوان</label>
        <input type="text" name="address" id="address" maxlength="255"
               class="form-control" value="{{ old('address', $printer->address ?? '') }}"
               placeholder="192.168.1.50:9100 أو اسم الطابعة في النظام">
        <p class="stk-help mb-0">لطابعات الشبكة: العنوان والمنفذ. ولغيرها: اسمها كما يعرفه النظام.</p>
    </div>

    <div class="col-md-6 col-12">
        <label class="form-label" for="device_id">الجهاز</label>
        <select name="device_id" id="device_id" class="form-control">
            <option value="">كل الأجهزة (طابعة عامّة للفرع)</option>
            @foreach ($devices as $device)
                <option value="{{ $device->id }}" @selected(old('device_id', $printer->device_id ?? null) == $device->id)>
                    {{ $device->device_name }}
                </option>
            @endforeach
        </select>
        <p class="stk-help mb-0">الطابعةُ المربوطة بجهازٍ تتقدّم على العامّة عند الطبع منه.</p>

        <x-create-first :count="$devices->count()" route="pos.settings.devices.create"
            permission="pos.devices.manage"
            :plan="\App\Constants\planFeatures\PosPlanFeatures::DEVICES"
            label="جهاز كاشير"
            hint="لا أجهزة مسجَّلة — اترك الطابعة عامّةً أو أنشئ جهازاً." />
    </div>

    <div class="col-md-6 col-12">
        <label class="form-label" for="branch_id">الفرع</label>
        @if ($canSelectBranch)
            <select name="branch_id" id="branch_id" class="form-control">
                <option value="">—</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $printer->branch_id ?? null) == $branch->id)>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        @else
            {{-- الموظفُ مقفلٌ على فرع سجلّه الوظيفي — الحقلُ للعرض لا للاختيار --}}
            <input type="text" class="form-control" disabled
                   value="{{ $branches->firstWhere('id', $currentBranchId)->name ?? 'فرعك' }}">
            <p class="stk-help mb-0">الفرع يُحدَّد من سجلّك الوظيفي.</p>
        @endif
    </div>

    <div class="col-md-3 col-6">
        <label class="form-label stk-req" for="paper_width">عرض الورق (مم)</label>
        <input type="number" name="paper_width" id="paper_width" min="38" max="250" required
               class="form-control @error('paper_width') is-invalid @enderror"
               value="{{ old('paper_width', $printer->paper_width ?? 80) }}">
        @error('paper_width')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <p class="stk-help mb-0">الحراري: 58 أو 80.</p>
    </div>

    <div class="col-md-3 col-6">
        <label class="form-label stk-req" for="copies">عدد النسخ</label>
        <input type="number" name="copies" id="copies" min="1" max="5" required
               class="form-control @error('copies') is-invalid @enderror"
               value="{{ old('copies', $printer->copies ?? 1) }}">
        @error('copies')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 col-12 d-flex align-items-end">
        <div class="w-100">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="auto_cut" id="auto_cut" value="1"
                       @checked(old('auto_cut', $printer->auto_cut ?? true))>
                <label class="form-check-label" for="auto_cut">قصّ تلقائي</label>
            </div>

            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="open_drawer" id="open_drawer" value="1"
                       @checked(old('open_drawer', $printer->open_drawer ?? false))>
                <label class="form-check-label" for="open_drawer">فتح الدرج</label>
            </div>

            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="is_default" id="is_default" value="1"
                       @checked(old('is_default', $printer->is_default ?? false))>
                <label class="form-check-label" for="is_default">افتراضية</label>
            </div>

            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                       @checked(old('is_active', $printer->is_active ?? true))>
                <label class="form-check-label" for="is_active">مفعَّلة</label>
            </div>
        </div>
    </div>

</div>
