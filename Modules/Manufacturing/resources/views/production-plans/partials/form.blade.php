{{--
    نموذج خطة الإنتاج — يخدم الإنشاء والتعديل معاً.

    نسخُه ملفَّين كما في `workstations/{create,edit}` يُعيد العيبَ المكتوب
    في `BuildsOrderItems`: كتلٌ نُسخت بين `store` و`update` فبدأت تتباعد.
    نموذجٌ واحدٌ يمنع أن يُصلَح حقلٌ في إحدى الشاشتين دون الأخرى.

    المتغيّرات:
      $plan    خطّةٌ قائمة عند التعديل، أو null عند الإنشاء
      $action  عنوانُ الحفظ
      $method  POST للإنشاء، PUT للتعديل
--}}
@php
    $plan = $plan ?? null;

    /*
        البنودُ المعروضة: القديمةُ عند فشل التحقّق، وإلّا بنودُ الخطّة،
        وإلّا صفٌّ فارغ كي لا تُفتح الشاشةُ على جدولٍ بلا سطر.
    */
    $oldProducts = old('product_id');

    if (is_array($oldProducts)) {
        $rows = collect($oldProducts)->keys()->map(fn ($i) => [
            'id' => old("item_id.$i"),
            'product_id' => old("product_id.$i"),
            'production_material_id' => old("production_material_id.$i"),
            'production_path_id' => old("production_path_id.$i"),
            'planned_quantity' => old("planned_quantity.$i"),
            'notes' => old("item_notes.$i"),
            'order' => null,
        ]);

        // البنودُ المولَّدة لا تُرسَل مع النموذج — تُعاد من الخطّة كي لا تختفي
        if ($plan) {
            $rows = $plan->items->whereNotNull('manufactur_order_id')->map(fn ($item) => [
                'order' => $item->manufactur_order_id,
                'product_name' => $item->product->name ?? null,
                'material_name' => $item->productionMaterial->name ?? null,
                'path_name' => $item->productionPath->name ?? null,
                'planned_quantity' => rtrim(rtrim((string) $item->planned_quantity, '0'), '.'),
                'notes' => $item->notes,
            ])->concat($rows);
        }
    } elseif ($plan && $plan->items->isNotEmpty()) {
        $rows = $plan->items->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'production_material_id' => $item->production_material_id,
            'production_path_id' => $item->production_path_id,
            'planned_quantity' => rtrim(rtrim((string) $item->planned_quantity, '0'), '.'),
            'notes' => $item->notes,
            // البندُ المولَّد يُعرَض نصّاً لا حقولاً — انظر `item-row`
            'order' => $item->manufactur_order_id,
            'product_name' => $item->product->name ?? null,
            'material_name' => $item->productionMaterial->name ?? null,
            'path_name' => $item->productionPath->name ?? null,
        ]);
    } else {
        $rows = collect([null]);
    }

    /*
        قائمةُ المواد بمنتجها وكميّتها الأساسية — لاقتراح الوصفة عند اختيار
        المنتج وتصفية القائمة به، ولعرض معامل القياس قبل الحفظ. الكميّةُ الأساسية مقسومُ
        المعامل — انظر `GeneratesOrdersFromPlan::batchFactor`.
    */
    $materialsMeta = $materials->mapWithKeys(fn ($m) => [
        $m->id => ['product_id' => $m->product_id, 'quantity' => (float) $m->quantity, 'name' => $m->name],
    ]);

    // هل «حجز المواد» مُفعَّل؟ يُقال تحت حقل المستودع ما سيحدث عند التوليد
    $reservesMaterials = app(\Modules\Manufacturing\Services\ReservationService::class)->enabled();
@endphp

<form id="planForm" action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- ══════════ رأس الخطّة ══════════ --}}
    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="feather icon-info"></i> بيانات الخطّة</h4>
        </div>
        <div class="card-body">
            <div class="row">
                {{-- «تابع لـ» — تُختم مع الخطّة عند إنشائها --}}
                @include('manufacturing::partials.affiliation-field', ['value' => $plan->branch_id ?? null, 'locked' => isset($plan) && $plan])

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="name" class="stk-req">اسم الخطّة</label>
                        <input type="text" id="name" name="name" required class="form-control"
                               value="{{ old('name', $plan->name ?? '') }}"
                               placeholder="مثال: خطة إنتاج مارس">
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="code">الكود</label>
                        {{--
                            معروضٌ لا مُرسَل — بلا `name` أصلاً. الرقمُ يُصدَر في
                            الخادم عند الحفظ (`issueDocumentNumber`)، كما استقرّ
                            عليه أمرُ التصنيع بعد أن كان حقلاً يكسر التسلسل.
                        --}}
                        <input type="text" id="code" class="form-control text-center stk-num"
                               value="{{ $plan->code ?? $serial_number ?? '' }}" disabled>
                        <span class="stk-help">
                            {{ $plan ? 'لا يتغيّر بعد الحفظ' : 'يُصدَر من إعدادات الترقيم عند الحفظ' }}
                        </span>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="employee_id" class="stk-req">المسؤول</label>
                        <select id="employee_id" name="employee_id" required class="form-control select2">
                            <option value="">اختر المسؤول…</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}"
                                    {{ old('employee_id', $plan->employee_id ?? '') == $employee->id ? 'selected' : '' }}>
                                    {{ trim($employee->first_name . ' ' . ($employee->middle_name ?? '')) }}
                                </option>
                            @endforeach
                        </select>
                        <span class="stk-help">يُنسَخ إلى كل أمر تصنيع يُولَّد من الخطّة</span>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="from_date" class="stk-req">من تاريخ</label>
                        <input type="date" id="from_date" name="from_date" required class="form-control"
                               value="{{ old('from_date', optional($plan?->from_date)->format('Y-m-d')) }}">
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="to_date" class="stk-req">إلى تاريخ</label>
                        <input type="date" id="to_date" name="to_date" required class="form-control"
                               value="{{ old('to_date', optional($plan?->to_date)->format('Y-m-d')) }}">
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="storehouse_id">مستودع المواد</label>
                        <select id="storehouse_id" name="storehouse_id" class="form-control select2">
                            <option value="">— بلا مستودع —</option>
                            @foreach ($storehouses as $storehouse)
                                <option value="{{ $storehouse->id }}"
                                    {{ old('storehouse_id', $plan->storehouse_id ?? '') == $storehouse->id ? 'selected' : '' }}>
                                    {{ $storehouse->name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="stk-help">
                            @if ($reservesMaterials)
                                يُنسَخ إلى الأوامر المولَّدة وتُحجز موادُّها فيه عند التوليد — بلا مستودع لا تُحجز
                            @else
                                يُنسَخ إلى الأوامر المولَّدة
                            @endif
                        </span>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="client_id">العميل</label>
                        <select id="client_id" name="client_id" class="form-control select2">
                            <option value="">— إنتاجٌ للمخزون —</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}"
                                    {{ old('client_id', $plan->client_id ?? '') == $client->id ? 'selected' : '' }}>
                                    {{ $client->trade_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="account_id">الحساب</label>
                        <select id="account_id" name="account_id" class="form-control select2">
                            <option value="">— من إعدادات الحسابات —</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}"
                                    {{ old('account_id', $plan->account_id ?? '') == $account->id ? 'selected' : '' }}>
                                    {{ $account->code }} — {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-group mb-0">
                        <label for="notes">ملاحظات</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $plan->notes ?? '') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ البنود ══════════ --}}
    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-list"></i> بنود الخطّة
                <span class="stk-count" id="itemsCount">0</span>
            </h6>

            <button type="button" class="btn btn-sm btn-outline-primary" id="addItem">
                <i class="feather icon-plus"></i> إضافة بند
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:24%">المنتج</th>
                        <th style="width:22%">قائمة المواد</th>
                        <th style="width:18%">مسار الإنتاج</th>
                        <th class="text-center" style="width:14%">الكميّة المخطّطة</th>
                        <th>ملاحظة</th>
                        <th class="text-center" style="width:60px">حذف</th>
                    </tr>
                </thead>
                <tbody id="itemRows">
                    @foreach ($rows as $row)
                        @include('manufacturing::production-plans.partials.item-row', ['row' => $row])
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-body d-none" id="itemsEmpty">
            <div class="stk-empty">
                <div class="stk-empty__icon"><i class="feather icon-list"></i></div>
                <h5 class="stk-empty__title">لا بنود</h5>
                <p class="stk-empty__hint">اضغط «إضافة بند» — كلُّ بندٍ يصير أمرَ تصنيعٍ عند التوليد</p>
            </div>
        </div>
    </div>
</form>

{{-- قالبُ الصفّ الجديد — يُنسخ في JS بدل بنائه سلسلةً في السكربت --}}
<template id="itemRowTemplate">
    @include('manufacturing::production-plans.partials.item-row', ['row' => null])
</template>

@push('scripts')
    <script>
        $(function () {
            const materials = @json($materialsMeta);
            const $rows = $('#itemRows');
            const $empty = $('#itemsEmpty');

            function refresh() {
                const count = $rows.children('tr').length;
                $('#itemsCount').text(count);
                $empty.toggleClass('d-none', count > 0);
            }

            /* معامل القياس = الكميّة المخطّطة ÷ الكميّة الأساسية للوصفة */
            function showFactor($row) {
                const meta = materials[$row.find('.js-material').val()];
                const planned = parseFloat($row.find('.js-quantity').val());
                const $hint = $row.find('.js-factor-hint');

                if (! meta || ! meta.quantity || ! planned) {
                    $hint.text('');
                    return;
                }

                const factor = Math.round((planned / meta.quantity) * 10000) / 10000;
                $hint.text('الوصفة لـ' + meta.quantity + ' — المعامل ×' + factor);
            }

            /*
                قائمةُ المواد تُصفّى بالمنتج المختار: لا تُعرَض إلّا وصفاتُ
                المنتج نفسِه، فلا يُختار لبند «خبز» وصفةُ «كعك». والخادمُ
                يتحقّق من ذلك أيضاً (`assertBomsBelongToProducts`).

                الاختيارُ القائم يبقى إن كان من وصفات المنتج، وإلّا يُفرَّغ.
                وللمنتج ذي الوصفة الواحدة تُختار وحدَها.
            */
            function fillMaterials($row) {
                const $select = $row.find('.js-material');
                if (! $select.length) return;

                const productId = String($row.find('.js-product').val() || '');
                const current = String($select.val() || '');
                const ids = productId
                    ? Object.keys(materials).filter(id => String(materials[id].product_id) === productId)
                    : [];

                const placeholder = ! productId
                    ? 'اختر المنتج أولاً…'
                    : (ids.length ? 'اختر قائمة المواد…' : 'لا قائمة مواد لهذا المنتج');

                $select.empty().append(new Option(placeholder, ''));
                ids.forEach(id => $select.append(new Option(materials[id].name, id)));

                if (ids.includes(current)) {
                    $select.val(current);
                } else if (ids.length === 1) {
                    $select.val(ids[0]);
                } else {
                    $select.val('');
                }

                showFactor($row);
            }

            $rows.on('change', '.js-product', function () {
                fillMaterials($(this).closest('tr'));
            });

            $rows.on('change input', '.js-material, .js-quantity', function () {
                showFactor($(this).closest('tr'));
            });

            $('#addItem').on('click', function () {
                $rows.append($('#itemRowTemplate').html());
                fillMaterials($rows.children('tr').last());
                refresh();
            });

            $rows.on('click', '.js-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });

            $rows.children('tr').each((_, tr) => fillMaterials($(tr)));
            refresh();
        });
    </script>
@endpush
