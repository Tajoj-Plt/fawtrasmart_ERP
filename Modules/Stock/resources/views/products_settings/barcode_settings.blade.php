@extends('stock::master')

@section('title')
إعدادات الباركود
@endsection

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">إعدادات الباركود</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="">الرئيسية</a></li>
                        <li class="breadcrumb-item active">إعدادات الباركود</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">إعدادات نظام الباركود</h5>
            <div>
                <a href="{{ route('product_settings.index') }}" class="btn btn-outline-danger btn-sm">
                    <i class="fa fa-ban"></i> إلغاء
                </a>
                <button type="submit" form="barcode_settings_form" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-save"></i> حفظ الإعدادات
                </button>
            </div>
        </div>

        <div class="card-body">
            @include('layouts.alerts.error')
            @include('layouts.alerts.success')

            <form id="barcode_settings_form" action="{{ route('barcode_settings.store') }}" method="POST">
                @csrf

                <div class="form-body">
                    {{-- القسم الأول: نوع الباركود والعرض --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="barcode_type">نوع الباركود</label>
                                <select name="barcode_type" id="barcode_type"
                                    class="form-control @error('barcode_type') is-invalid @enderror"
                                    onchange="updateBarcodePreview()">
                                    <option value="CODE128"
                                        {{ (old('barcode_type', $settings->barcode_type ?? 'CODE128')) == 'CODE128' ? 'selected' : '' }}>
                                        Code 128 (الأكثر استخداماً)
                                    </option>
                                    <option value="EAN13"
                                        {{ (old('barcode_type', $settings->barcode_type ?? '')) == 'EAN13' ? 'selected' : '' }}>
                                        EAN 13 (المتاجر)
                                    </option>
                                </select>
                                @error('barcode_type')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="barcode_width">عرض الباركود (بكسل)</label>
                                <input type="number" class="form-control @error('barcode_width') is-invalid @enderror"
                                    name="barcode_width" id="barcode_width"
                                    value="{{ old('barcode_width', $settings->barcode_width ?? 2) }}"
                                    min="1" max="10" required onchange="updateBarcodePreview()">
                                @error('barcode_width')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- القسم الثاني: ارتفاع الباركود وحجم الخط --}}
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="barcode_height">ارتفاع الباركود (بكسل)</label>
                                <input type="number" class="form-control @error('barcode_height') is-invalid @enderror"
                                    name="barcode_height" id="barcode_height"
                                    value="{{ old('barcode_height', $settings->barcode_height ?? 50) }}"
                                    min="20" max="200" required onchange="updateBarcodePreview()">
                                @error('barcode_height')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="font_size">حجم الخط</label>
                                <input type="number" class="form-control @error('font_size') is-invalid @enderror"
                                    name="font_size" id="font_size"
                                    value="{{ old('font_size', $settings->font_size ?? 12) }}"
                                    min="8" max="24" required onchange="updateBarcodePreview()">
                                @error('font_size')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- القسم الثالث: الهامش وعرض القيمة --}}
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="margin">الهامش حول الباركود (بكسل)</label>
                                <input type="number" class="form-control @error('margin') is-invalid @enderror"
                                    name="margin" id="margin"
                                    value="{{ old('margin', $settings->margin ?? 10) }}"
                                    min="0" max="50" required onchange="updateBarcodePreview()">
                                @error('margin')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="vs-checkbox-con vs-checkbox-primary">
                                    <input type="hidden" name="display_value" value="0">
                                    <input type="checkbox" id="display_value" name="display_value" value="1"
                                        {{ (old('display_value', $settings->display_value ?? true)) ? 'checked' : '' }}
                                        onchange="updateBarcodePreview()">
                                    <span class="vs-checkbox">
                                        <span class="vs-checkbox--check">
                                            <i class="vs-icon feather icon-check"></i>
                                        </span>
                                    </span>
                                    <span>عرض قيمة الباركود تحته</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    {{-- القسم الرابع: الباركود المتضمن للوزن --}}
                    <div class="row">
                        <div class="col-12">
                            <h5 class="mb-3">الباركود المتضمن الوزن</h5>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="vs-checkbox-con vs-checkbox-primary">
                                <input type="hidden" name="include_weight" value="0">
                                <input type="checkbox" id="include_weight" name="include_weight" value="1"
                                    {{ (old('include_weight', $settings->include_weight ?? false)) ? 'checked' : '' }}
                                    onchange="toggleWeightInputs()">
                                <span class="vs-checkbox">
                                    <span class="vs-checkbox--check">
                                        <i class="vs-icon feather icon-check"></i>
                                    </span>
                                </span>
                                <span>تفعيل الباركود المتضمن الوزن</span>
                            </div>
                            <small class="text-muted d-block mt-2">يسمح بتضمين معلومات الوزن والسعر في الباركود</small>
                        </div>
                    </div>

                    {{-- حقول الوزن المتقدمة --}}
                    <div class="row mt-3 weight-inputs"
                        style="{{ (old('include_weight', $settings->include_weight ?? false)) ? '' : 'display: none;' }}">

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="barcode_format">صيغة الباركود</label>
                                <input type="text" class="form-control @error('barcode_format') is-invalid @enderror"
                                    name="barcode_format" id="barcode_format"
                                    value="{{ old('barcode_format', $settings->barcode_format ?? '') }}"
                                    placeholder="مثال: XXXXXXXXWWWWWPPPPN">
                                <small class="text-muted d-block mt-1">X=معرف، W=وزن، P=سعر، N=فحص</small>
                                @error('barcode_format')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="weight_unit_division">تقسيم وحدة الوزن</label>
                                <input type="number" class="form-control @error('weight_unit_division') is-invalid @enderror"
                                    name="weight_unit_division" id="weight_unit_division"
                                    value="{{ old('weight_unit_division', $settings->weight_unit_division ?? '') }}"
                                    step="0.01" placeholder="0.001">
                                <small class="text-muted">كم غرام = 1 وحدة</small>
                                @error('weight_unit_division')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="currency_division">تقسيم العملة</label>
                                <input type="number" class="form-control @error('currency_division') is-invalid @enderror"
                                    name="currency_division" id="currency_division"
                                    value="{{ old('currency_division', $settings->currency_division ?? '') }}"
                                    step="0.01" placeholder="0.01">
                                <small class="text-muted">كم قرش = 1 وحدة</small>
                                @error('currency_division')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    {{-- معاينة الباركود --}}
                    <div class="row">
                        <div class="col-12">
                            <h5 class="mb-3">معاينة الباركود</h5>
                            <div class="card bg-light" style="padding: 20px; text-align: center;">
                                <svg id="preview-barcode" style="max-width: 100%;"></svg>
                                <small class="text-muted d-block mt-2">معاينة حية للإعدادات</small>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
    function toggleWeightInputs() {
        const checkbox = document.getElementById("include_weight");
        const weightInputs = document.querySelectorAll(".weight-inputs");

        weightInputs.forEach(element => {
            element.style.display = checkbox.checked ? "block" : "none";
        });
    }

    function updateBarcodePreview() {
        const testBarcode = '123456789012';
        const barcodeType = document.getElementById("barcode_type").value;

        try {
            JsBarcode("#preview-barcode", testBarcode, {
                format: barcodeType,
                width: parseInt(document.getElementById("barcode_width").value),
                height: parseInt(document.getElementById("barcode_height").value),
                displayValue: document.getElementById("display_value").checked,
                fontSize: parseInt(document.getElementById("font_size").value),
                margin: parseInt(document.getElementById("margin").value)
            });
        } catch(e) {
            console.log('خطأ في المعاينة:', e);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(updateBarcodePreview, 500);

        document.getElementById("barcode_type").addEventListener("change", updateBarcodePreview);
        document.getElementById("barcode_width").addEventListener("change", updateBarcodePreview);
        document.getElementById("barcode_height").addEventListener("change", updateBarcodePreview);
        document.getElementById("display_value").addEventListener("change", updateBarcodePreview);
        document.getElementById("font_size").addEventListener("change", updateBarcodePreview);
        document.getElementById("margin").addEventListener("change", updateBarcodePreview);
    });
</script>
@endsection