@php
    $locked = $type && $type->isLocked();
@endphp

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="form-group col-md-6">
                <label>الاسم <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required
                    value="{{ old('name', $type->name ?? '') }}" placeholder="بدل سكن">
            </div>
            <div class="form-group col-md-6">
                <label>الرمز اللاتيني</label>
                <input type="text" name="code" class="form-control" @if ($locked) readonly @endif
                    value="{{ old('code', $type->code ?? '') }}" placeholder="housing">
                <small class="stk-help">إن تُرك فارغاً يُولَّد تلقائياً. الأنواع المعيارية لا يُغيَّر رمزها.</small>
            </div>
            <div class="form-group col-md-4">
                <label>الاتجاه <span class="text-danger">*</span></label>
                <select name="kind" class="form-control hr-s2" @if ($locked) disabled @endif required>
                    @php $kind = (int) old('kind', $type->kind ?? \App\Models\SalaryItem::TYPE_ADDITION); @endphp
                    <option value="{{ \App\Models\SalaryItem::TYPE_ADDITION }}" {{ $kind === \App\Models\SalaryItem::TYPE_ADDITION ? 'selected' : '' }}>مستحق (بدل)</option>
                    <option value="{{ \App\Models\SalaryItem::TYPE_DEDUCTION }}" {{ $kind === \App\Models\SalaryItem::TYPE_DEDUCTION ? 'selected' : '' }}>مستقطع</option>
                </select>
                @if ($locked)
                    <input type="hidden" name="kind" value="{{ $type->kind }}">
                @endif
            </div>
            <div class="form-group col-md-4">
                <label>الحالة <span class="text-danger">*</span></label>
                <select name="status" class="form-control hr-s2" required>
                    @php $status = (int) old('status', $type->status ?? \App\Models\SalaryComponentType::STATUS_ACTIVE); @endphp
                    <option value="{{ \App\Models\SalaryComponentType::STATUS_ACTIVE }}" {{ $status === \App\Models\SalaryComponentType::STATUS_ACTIVE ? 'selected' : '' }}>نشط</option>
                    <option value="{{ \App\Models\SalaryComponentType::STATUS_INACTIVE }}" {{ $status === \App\Models\SalaryComponentType::STATUS_INACTIVE ? 'selected' : '' }}>متوقف</option>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label>ترتيب العرض</label>
                <input type="number" name="sort_order" class="form-control" min="0" max="9999"
                    value="{{ old('sort_order', $type->sort_order ?? 100) }}">
            </div>
            <div class="form-group col-md-6">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="is_default" name="is_default" value="1"
                        {{ old('is_default', $type->is_default ?? false) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_default">بدل افتراضي على العقود الجديدة</label>
                </div>
            </div>
            <div class="form-group col-md-6">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="is_insurable" name="is_insurable" value="1"
                        {{ old('is_insurable', $type->is_insurable ?? false) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_insurable">خاضع للتأمينات الاجتماعية (يُحسب في الأجر الخاضع عند الصرف)</label>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end" style="gap: 8px">
            <a href="{{ route('SalaryComponentTypes.index') }}" class="btn btn-outline-secondary">إلغاء</a>
            <button type="submit" class="btn btn-primary"><i class="feather icon-save"></i> حفظ</button>
        </div>
    </div>
</div>
