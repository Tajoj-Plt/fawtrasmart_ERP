{{--
    حقول سؤال المنتج — مشتركة بين الإضافة والتعديل.
    السائل: عميل متجر مسجَّل، أو زائر باسمٍ حرّ (يُترك العميل فارغاً).
    الحالة لا تُختار: تُكتب إجابة ⇒ «تم الرد»، تُترك فارغة ⇒ «قيد الانتظار».
--}}
@php
    $selectedProduct = old('product_id', $productQuestion->product_id);
    $selectedClient = old('client_id', $productQuestion->client_id);
@endphp

<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-help-circle"></i> السؤال</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="form-group col-md-4">
                <label for="product_id" class="stk-req">المنتج</label>
                <select name="product_id" id="product_id" class="form-control select2" required>
                    <option value="">اختر المنتج…</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) $selectedProduct === (string) $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
                @error('product_id')<small class="text-danger">{{ $message }}</small>@enderror
            </div>

            <div class="form-group col-md-4">
                <label for="client_id">عميل المتجر (اختياري)</label>
                <select name="client_id" id="client_id" class="form-control select2">
                    <option value="">زائر — بلا حساب</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) $selectedClient === (string) $client->id)>
                            {{ $client->display_name }}@if ($client->phone) — {{ $client->phone }}@endif
                        </option>
                    @endforeach
                </select>
                <small class="text-muted">إن اخترت عميلاً له بريد، تصله الإجابة بالبريد.</small>
                @error('client_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
            </div>

            <div class="form-group col-md-4" id="askerNameGroup">
                <label for="asker_name">اسم السائل (للزائر)</label>
                <input type="text" name="asker_name" id="asker_name" class="form-control" maxlength="150"
                    value="{{ old('asker_name', $productQuestion->asker_name) }}" placeholder="مثال: أبو محمد">
                <small class="text-muted">يظهر في المتجر بجانب السؤال. اتركه فارغاً ليظهر «زائر».</small>
                @error('asker_name')<small class="text-danger d-block">{{ $message }}</small>@enderror
            </div>
        </div>

        <div class="row">
            <div class="form-group col-12">
                <label for="question" class="stk-req">نص السؤال</label>
                <textarea name="question" id="question" class="form-control" rows="3" maxlength="1000" required
                    placeholder="مثال: هل المنتج يشمل الضمان؟">{{ old('question', $productQuestion->question) }}</textarea>
                @error('question')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="stk-table-head">
        <h6 class="stk-table-head__title"><i class="feather icon-message-circle"></i> الإجابة والنشر</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="form-group col-12">
                <label for="answer">الإجابة (اختيارية)</label>
                <textarea name="answer" id="answer" class="form-control" rows="4" maxlength="2000"
                    placeholder="اكتب الإجابة الآن، أو اتركها فارغة ليبقى السؤال «قيد الانتظار» وتجيب لاحقاً.">{{ old('answer', $productQuestion->answer) }}</textarea>
                @error('answer')<small class="text-danger">{{ $message }}</small>@enderror
            </div>
        </div>

        <input type="hidden" name="is_public" value="0">
        <label class="d-inline-flex align-items-center gap-1" for="is_public" style="cursor:pointer">
            <input type="checkbox" class="stk-check" id="is_public" name="is_public" value="1"
                @checked(old('is_public', $productQuestion->is_public ?? true))>
            <span>منشور — يظهر السؤال وإجابته في صفحة المنتج بالمتجر</span>
        </label>
        <small class="text-muted d-block">لا يظهر في المتجر إلا السؤال المنشور الذي له إجابة.</small>
        @error('is_public')<small class="text-danger d-block">{{ $message }}</small>@enderror
    </div>
</div>

@push('scripts')
<script>
    // اسم السائل الحرّ للزائر فقط — يختفي الحقل عند اختيار عميل متجر
    (function () {
        function syncAsker() {
            var hasClient = !!$('#client_id').val();
            $('#askerNameGroup').toggle(!hasClient);
        }
        $(function () {
            $('#client_id').on('change', syncAsker);
            syncAsker();
        });
    })();
</script>
@endpush
