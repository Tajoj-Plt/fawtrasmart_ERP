{{--
    جدول تصنيفات سندات القبض.

    كان ملفوفاً بـ `dataTables_wrapper` بلا أي مكتبة DataTables على الصفحة،
    ويرسم نافذتَي تعديلٍ وحذفٍ لكل صفٍّ **داخل** `<tr>` — عنصر `<div>` شقيقٌ
    لخلايا الجدول، وهو ترميزٌ غير صالح ينقله المتصفّح خارج الصفّ من تلقائه.
    وكان كل نافذةٍ تحمل `id="myModalLabel1"` نفسه، فتتكرّر معرّفاتٌ بعدد
    الصفوف. صارت النوافذ بعد الجدول، والحذفُ نافذةً واحدة تُملأ من الزرّ.
--}}

@php
    $rows = collect($receipt_categories ?? []);
@endphp

@if ($rows->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-tag"></i> تصنيفات سندات القبض
            <span class="stk-count">{{ $rows->count() }}</span>
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>الاسم</th>
                    <th>الوصف</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:90px">إجراءات</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $index => $category)
                    @php $isDisabled = $category->status == 1; @endphp

                    <tr>
                        <td class="stk-num">{{ $index + 1 }}</td>

                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-tag"></i></span>
                                <span class="stk-media__body">
                                    <span class="stk-media__title">{{ $category->name }}</span>
                                </span>
                            </div>
                        </td>

                        <td>{{ $category->description ?: '—' }}</td>

                        <td class="text-center">
                            <span class="stk-state {{ $isDisabled ? 'stk-state--off' : 'stk-state--on' }}">
                                {{ $isDisabled ? 'معطَّل' : 'نشط' }}
                            </span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown stk-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false" aria-label="إجراءات">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <button type="button" class="dropdown-item" data-bs-toggle="modal"
                                        data-bs-target="#modal_Update{{ $category->id }}">
                                        <i class="feather icon-edit-2"></i> تعديل
                                    </button>

                                    <div class="dropdown-divider"></div>

                                    <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal"
                                        data-bs-target="#deleteReceiptCategoryModal"
                                        data-row-label="{{ $category->name }}"
                                        data-row-url="{{ route('finance_settings.receipt_category_delete', $category->id) }}">
                                        <i class="feather icon-trash-2"></i> حذف
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- نوافذ التعديل: خارج الجدول، واحدةٌ لكل تصنيف لأنها تحمل حقوله --}}
    @foreach ($rows as $category)
        <div class="modal fade" id="modal_Update{{ $category->id }}" tabindex="-1"
            aria-labelledby="modal_UpdateLabel{{ $category->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modal_UpdateLabel{{ $category->id }}">تعديل تصنيف المقبوضات</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>

                    <form class="form" action="{{ route('finance_settings.receipt_category_update', $category->id) }}"
                        method="POST">
                        @csrf

                        <div class="modal-body">
                            <div class="form-group">
                                <label for="name_{{ $category->id }}" class="stk-req">الاسم</label>
                                <input type="text" id="name_{{ $category->id }}" class="form-control" name="name"
                                    placeholder="اسم التصنيف" value="{{ old('name', $category->name) }}" required>
                            </div>

                            <div class="form-group">
                                <label for="description_{{ $category->id }}">الوصف</label>
                                <textarea id="description_{{ $category->id }}" name="description" class="form-control" rows="3"
                                    placeholder="وصف التصنيف">{{ old('description', $category->description) }}</textarea>
                            </div>

                            <label class="stk-switch" for="statusSwitch_{{ $category->id }}">
                                <input type="checkbox" id="statusSwitch_{{ $category->id }}" name="status"
                                    @checked($category->status == 1)>
                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                <span class="stk-switch__label">
                                    <span class="stk-switch__title">تعطيل التصنيف</span>
                                    <span class="stk-switch__hint">لا يظهر في قوائم اختيار التصنيف عند إنشاء السندات.</span>
                                </span>
                            </label>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> حفظ التعديل
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    {{-- نافذة حذفٍ واحدة لكل الصفوف — تُملأ من سمات الزرّ المضغوط --}}
    <div class="modal fade" id="deleteReceiptCategoryModal" tabindex="-1"
        aria-labelledby="deleteReceiptCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteReceiptCategoryModalLabel">حذف تصنيف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-0">
                        سيُحذف تصنيف <strong data-row-label>—</strong>. السندات المرتبطة به تبقى بلا تصنيف.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <form method="POST" action="#" class="d-inline" data-row-confirm
                        onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="feather icon-trash-2"></i> تأكيد الحذف
                        </button>
                        </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('deleteReceiptCategoryModal').addEventListener('show.bs.modal', function(event) {
            var trigger = event.relatedTarget;
            if (!trigger) return;

            this.querySelector('[data-row-label]').textContent = trigger.dataset.rowLabel || '—';
            this.querySelector('[data-row-confirm]').action = trigger.dataset.rowUrl || '#';
        });
    </script>
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-tag"></i></span>
        <h6 class="stk-empty__title">لا تصنيفات مقبوضات</h6>
        <p class="stk-empty__hint">عرّف تصنيفاتك لتُبنى عليها تقارير المقبوضات وتوجيه حساباتها.</p>

        <button type="button" class="btn btn-primary mt-1" data-bs-toggle="modal" data-bs-target="#modal_Create">
            <i class="feather icon-plus"></i> تصنيف جديد
        </button>
    </div>
@endif
