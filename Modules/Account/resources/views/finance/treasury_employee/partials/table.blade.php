{{--
    جدول خزائن الموظفين.

    كان ملفوفاً بـ `dataTables_wrapper` بلا أي مكتبة DataTables على الصفحة،
    ويرسم لكل صفٍّ نافذةَ حذفٍ كاملة **داخل** `<tr>` — عنصر `<div>` شقيقٌ
    لخلايا الجدول، وهو ترميزٌ غير صالح ينقله المتصفّح خارج الصفّ من تلقائه.
    صارت نافذةً واحدة تُملأ من بيانات الزرّ المضغوط.

    والترقيم كان يُستدعى من `index` بعد هذا القالب لا من داخله، على خلاف
    بقيّة الجداول. صار هنا حيث يعيش الجدول.
--}}

@php
    // الصفوف بلا خزينة مرتبطة تُسقَط: السجلّ بلا خزينة لا معنى له في هذه الشاشة
    $rows = $treasury_employees->filter(fn($info) => !empty($info->treasury));
@endphp

@if ($rows->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-users"></i> خزائن الموظفين
            <span class="stk-count">{{ $rows->count() }}</span>
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>الخزينة الافتراضية</th>
                    <th class="text-center" style="width:90px">إجراءات</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $info)
                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-user"></i></span>
                                <span class="stk-media__body">
                                    <span class="stk-media__title">
                                        {{ $info->employee->full_name ?? 'غير محدد' }}
                                    </span>
                                </span>
                            </div>
                        </td>

                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-briefcase"></i></span>
                                <span class="stk-media__body">
                                    <span class="stk-media__title">{{ $info->treasury->name ?? 'غير محدد' }}</span>
                                </span>
                            </div>
                        </td>

                        <td class="text-center">
                            <div class="dropdown stk-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false" aria-label="إجراءات">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('treasury_employee.edit', $info->id) }}">
                                        <i class="feather icon-edit-2"></i> تعديل
                                    </a>

                                    <div class="dropdown-divider"></div>

                                    <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal"
                                        data-bs-target="#deleteTreasuryEmployeeModal"
                                        data-row-label="{{ $info->employee->full_name ?? 'غير محدد' }}"
                                        data-row-url="{{ route('treasury_employee.delete', $info->id) }}">
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

    @include('account::finance.treasury_employee.partials.pagination', [
        'treasury_employees' => $treasury_employees,
    ])

    {{-- نافذة حذفٍ واحدة لكل الصفوف — تُملأ من سمات الزرّ المضغوط --}}
    <div class="modal fade" id="deleteTreasuryEmployeeModal" tabindex="-1"
        aria-labelledby="deleteTreasuryEmployeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteTreasuryEmployeeModalLabel">حذف خزينة موظف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-0">
                        سيُلغى ربط <strong data-row-label>—</strong> بخزينته الافتراضية، فتعود سنداته تسأل عن الخزينة
                        في كل مرّة.
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
        document.getElementById('deleteTreasuryEmployeeModal').addEventListener('show.bs.modal', function(event) {
            var trigger = event.relatedTarget;
            if (!trigger) return;

            this.querySelector('[data-row-label]').textContent = trigger.dataset.rowLabel || '—';
            this.querySelector('[data-row-confirm]').action = trigger.dataset.rowUrl || '#';
        });
    </script>
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-users"></i></span>
        <h6 class="stk-empty__title">لا خزائن موظفين</h6>
        <p class="stk-empty__hint">عيّن لكل موظف خزينته الافتراضية ليُصرَف منها ويُقبَض إليها تلقائياً.</p>

        <a href="{{ route('treasury_employee.create') }}" class="btn btn-primary mt-1">
            <i class="feather icon-plus"></i> تعيين خزينة لموظف
        </a>
    </div>
@endif
