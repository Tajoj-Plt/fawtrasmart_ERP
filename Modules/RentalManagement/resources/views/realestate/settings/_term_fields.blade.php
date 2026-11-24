@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    $pfx = $tpl ? 't'.$tpl->id.'_' : 'tnew_';
    $useOld = $tpl === null;
@endphp
<div class="row">
    <div class="col-md-5">
        <div class="form-group">
            <label for="{{ $pfx }}title" class="stk-req">عنوان الشرط</label>
            <input type="text" id="{{ $pfx }}title" name="title" class="form-control" maxlength="191" required value="{{ $tpl ? $tpl->title : old('title') }}" placeholder="مثال: الصيانة">
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="{{ $pfx }}type">ينطبق على</label>
            <select id="{{ $pfx }}type" name="contract_type" class="form-control">
                <option value="">كل أنواع العقود</option>
                @foreach (V::CONTRACT_TYPES as $value => $meta)
                    <option value="{{ $value }}" @selected(($tpl ? $tpl->contract_type : old('contract_type')) === $value)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="{{ $pfx }}sort">الترتيب</label>
            <input type="number" id="{{ $pfx }}sort" name="sort" class="form-control" min="0" max="65535" value="{{ $tpl ? $tpl->sort : old('sort', 0) }}">
        </div>
    </div>
    <div class="col-12">
        <div class="form-group">
            <label for="{{ $pfx }}body" class="stk-req">نصّ الشرط</label>
            <textarea id="{{ $pfx }}body" name="body" rows="3" class="form-control" maxlength="5000" required>{{ $tpl ? $tpl->body : old('body') }}</textarea>
        </div>
    </div>
    <div class="col-12 mb-1">
        <input type="hidden" name="is_active" value="0">
        <label class="mr-2 me-2"><input type="checkbox" name="is_active" value="1" @checked($tpl ? $tpl->is_active : true)> فعّال</label>
        <input type="hidden" name="is_default" value="0">
        <label><input type="checkbox" name="is_default" value="1" @checked($tpl ? $tpl->is_default : false)> يُضاف تلقائياً لكل عقدٍ جديد</label>
    </div>
</div>
