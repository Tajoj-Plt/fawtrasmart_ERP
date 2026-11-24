@extends('stock::master')

@section('title', 'إضافة صنف جديد')

@section('content')
    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'إضافة صنف جديد',
            'icon' => 'plus-circle',
            'sub' => 'عرِّف الصنف واربطه بمجموعته ووحدات قياسه',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'الأصناف' => route('category.index'),
                'إضافة' => null,
            ],
            'actions' => '<a href="' . route('category.index') . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-x"></i> إلغاء</a>'
                . '<button type="submit" form="category_form" class="btn btn-primary">'
                . '<i class="feather icon-check"></i> حفظ الصنف</button>',
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <form id="category_form" action="{{ route('category.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row">
                {{-- ══════════ العمود الرئيسي ══════════ --}}
                <div class="col-lg-8 col-12">

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-info"></i> البيانات الأساسية</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name" class="stk-req">اسم الصنف</label>
                                        <input type="text" id="name" name="name" required
                                               class="form-control @error('name') is-invalid @enderror"
                                               value="{{ old('name') }}" placeholder="مثال: مواد غذائية">
                                        @error('name')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="code">كود الصنف</label>
                                        {{-- حقلٌ تلقائي: الخادم يُصدره ويتجاهل ما يصل من النموذج، فالحقل هنا للعرض فقط --}}
                                        <input type="text" id="code" name="code" readonly tabindex="-1"
                                               class="form-control stk-locked"
                                               value="{{ $nextCode ?? '' }}">
                                        <span class="stk-help stk-help--locked">
                                            <i class="feather icon-lock"></i>
                                            معاينة من قسم «الأصناف» في «ترقيم المستندات» — يُحجَز عند الحفظ.
                                        </span>
                                        @error('code')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <span class="stk-help">يظهر في التقارير وقوائم الاختيار</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_group_id">مجموعة المنتجات</label>
                                        <select id="product_group_id" name="product_group_id"
                                                class="form-control select2 @error('product_group_id') is-invalid @enderror">
                                            <option value="">بدون مجموعة</option>
                                            @foreach ($productGroups ?? [] as $group)
                                                <option value="{{ $group->id }}"
                                                    {{ old('product_group_id') == $group->id ? 'selected' : '' }}>
                                                    {{ str_repeat('— ', max(0, ($group->level ?? 1) - 1)) }}{{ $group->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('product_group_id')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <x-create-first
                                            :count="count($productGroups ?? [])"
                                            route="product_groups.create"
                                            permission="stock.product_groups.create"
                                            :plan="\App\Constants\planFeatures\StockPlanFeatures::CATEGORIES"
                                            label="مجموعة منتجات"
                                            hint="لا مجموعات منتجات بعد — وهي اختيارية للصنف." />
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="main_category">الصنف الرئيسي</label>
                                        <select id="main_category" name="main_category"
                                                class="form-control select2 @error('main_category') is-invalid @enderror">
                                            <option value="">صنف رئيسي (بلا أب)</option>
                                            @foreach ($categories ?? [] as $parent)
                                                <option value="{{ $parent->id }}"
                                                    {{ old('main_category') == $parent->id ? 'selected' : '' }}>
                                                    {{ $parent->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('main_category')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label for="discretion">الوصف</label>
                                        <textarea id="discretion" name="discretion" rows="3"
                                                  class="form-control @error('discretion') is-invalid @enderror"
                                                  placeholder="وصف مختصر يوضّح ما يضمّه هذا الصنف">{{ old('discretion') }}</textarea>
                                        @error('discretion')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ══════════ الوحدات ══════════ --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-sliders"></i> وحدات القياس
                                <span class="stk-count" id="units_count">0</span>
                            </h4>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal"
                                    data-target="#addUnitModal">
                                <i class="feather icon-plus"></i> إضافة وحدة
                            </button>
                        </div>

                        <div class="card-body">
                            <p class="stk-help mb-3" style="margin-top:0">
                                الوحدة الأساسية هي التي تُخزَّن بها الكميات، ومعاملُ التحويل ينسب كل وحدة أخرى إليها.
                            </p>

                            <div id="units_container"></div>

                            <div class="stk-empty py-4" id="units_empty">
                                <div class="stk-empty__icon"><i class="feather icon-sliders"></i></div>
                                <h5 class="stk-empty__title">لم تُضَف وحدات بعد</h5>
                                <p class="stk-empty__hint">أضف وحدةً واحدة على الأقل وحدّدها كوحدة أساسية</p>
                                <button type="button" class="btn btn-outline-primary" data-toggle="modal"
                                        data-target="#addUnitModal">
                                    <i class="feather icon-plus"></i> إضافة وحدة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ══════════ العمود الجانبي ══════════ --}}
                <div class="col-lg-4 col-12">

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-image"></i> صورة الصنف</h4>
                        </div>
                        <div class="card-body">
                            <label for="attachments" class="stk-upload" id="upload_zone">
                                <img src="" alt="" class="stk-upload__preview d-none" id="preview_img">
                                <span class="stk-upload__placeholder" id="upload_placeholder">
                                    <i class="feather icon-upload-cloud"></i>
                                    <span class="stk-upload__title">اسحب الصورة أو اضغط للاختيار</span>
                                    <span class="stk-upload__hint">PNG أو JPG — بحدّ أقصى 2 ميغابايت</span>
                                </span>
                            </label>
                            <input type="file" id="attachments" name="attachments" accept="image/*" class="d-none">

                            <button type="button" class="btn btn-outline-danger btn-sm w-100 mt-2 d-none"
                                    id="remove_img">
                                <i class="feather icon-trash-2"></i> إزالة الصورة
                            </button>

                            @error('attachments')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><i class="feather icon-toggle-right"></i> الحالة</h4>
                        </div>
                        <div class="card-body">
                            <label class="stk-switch">
                                <input type="checkbox" name="is_active" value="1"
                                       {{ old('is_active', '1') ? 'checked' : '' }}>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">الصنف نشط</span>
                                    <span class="stk-switch__hint">الأصناف غير النشطة لا تظهر عند إضافة منتج</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="stk-form-foot">
                    <a href="{{ route('category.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> حفظ الصنف
                    </button>
                </div>
            </div>
        </form>

        {{-- ══════════ نافذة إضافة وحدة ══════════ --}}
        <div class="modal fade" id="addUnitModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة وحدة قياس</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-group">
                            <label for="modal_unit_id" class="stk-req">الوحدة</label>
                            <select id="modal_unit_id" class="form-control">
                                <option value="">اختر وحدة…</option>
                                @foreach ($units ?? [] as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="modal_conversion_factor" class="stk-req">معامل التحويل</label>
                            <input type="number" id="modal_conversion_factor" class="form-control"
                                   step="0.0001" min="0.0001" value="1">
                            <span class="stk-help">كم وحدةً أساسية تساوي وحدةٌ واحدة من هذه؟</span>
                        </div>

                        <label class="stk-switch mb-0">
                            <input type="checkbox" id="modal_is_base_unit">
                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                            <span class="stk-switch__label">
                                <span class="stk-switch__title">وحدة أساسية</span>
                                <span class="stk-switch__hint">تُخزَّن بها الكميات، ومعاملها 1 دائماً</span>
                            </span>
                        </label>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                        <button type="button" class="btn btn-primary" id="add_unit_btn">
                            <i class="feather icon-plus"></i> إضافة
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(function () {
            if ($.fn.select2) {
                $('.select2').select2({ placeholder: 'اختر…', allowClear: true, dir: 'rtl', width: '100%' });
            }

            /* ── الصورة ── */
            const $file = $('#attachments'), $prev = $('#preview_img'),
                  $ph = $('#upload_placeholder'), $rm = $('#remove_img');

            $file.on('change', function () {
                const f = this.files && this.files[0];
                if (!f) return;
                const r = new FileReader();
                r.onload = e => {
                    $prev.attr('src', e.target.result).removeClass('d-none');
                    $ph.addClass('d-none');
                    $rm.removeClass('d-none');
                };
                r.readAsDataURL(f);
            });

            $rm.on('click', function () {
                $file.val('');
                $prev.attr('src', '').addClass('d-none');
                $ph.removeClass('d-none');
                $(this).addClass('d-none');
            });

            /* ── الوحدات ── */
            let idx = 0;

            function refresh() {
                const n = $('#units_container .stk-unit').length;
                $('#units_count').text(n);
                $('#units_empty').toggle(n === 0);
            }

            $('#addUnitModal').on('show.bs.modal', function () {
                $('#modal_unit_id').val('');
                $('#modal_conversion_factor').val('1').prop('readonly', false);
                $('#modal_is_base_unit').prop('checked', false);
            });

            $('#modal_is_base_unit').on('change', function () {
                $('#modal_conversion_factor')
                    .val(this.checked ? '1' : '')
                    .prop('readonly', this.checked);
            });

            $('#add_unit_btn').on('click', function () {
                const $sel = $('#modal_unit_id'),
                      id = $sel.val(),
                      name = $sel.find('option:selected').text().trim(),
                      factor = $('#modal_conversion_factor').val(),
                      isBase = $('#modal_is_base_unit').is(':checked');

                if (!id) { $sel.focus(); return; }
                if (!factor || parseFloat(factor) <= 0) { $('#modal_conversion_factor').focus(); return; }

                if ($('#units_container').find('[data-unit="' + id + '"]').length) {
                    $sel.focus();
                    return;
                }

                // وحدة أساسية واحدة فقط
                if (isBase) {
                    $('#units_container .stk-unit').removeClass('is-base')
                        .find('.stk-unit__badge').remove();
                    $('#units_container input[name$="[is_base_unit]"]').val('0');
                }

                $('#units_container').append(`
                    <div class="stk-unit ${isBase ? 'is-base' : ''}" data-unit="${id}">
                        <span class="stk-unit__icon"><i class="feather icon-box"></i></span>
                        <span class="stk-unit__body">
                            <span class="stk-unit__name">${name}
                                ${isBase ? '<span class="stk-unit__badge">أساسية</span>' : ''}
                            </span>
                            <span class="stk-unit__meta">معامل التحويل: <b>${factor}</b></span>
                        </span>
                        <button type="button" class="btn btn-outline-danger stk-unit__remove" aria-label="إزالة">
                            <i class="feather icon-trash-2"></i>
                        </button>
                        <input type="hidden" name="units[${idx}][unit_id]" value="${id}">
                        <input type="hidden" name="units[${idx}][conversion_factor]" value="${factor}">
                        <input type="hidden" name="units[${idx}][is_base_unit]" value="${isBase ? 1 : 0}">
                    </div>
                `);

                idx++;
                refresh();
                $('#addUnitModal').modal('hide');
            });

            $(document).on('click', '.stk-unit__remove', function () {
                $(this).closest('.stk-unit').remove();
                refresh();
            });

            refresh();
        });
    </script>
@endsection
