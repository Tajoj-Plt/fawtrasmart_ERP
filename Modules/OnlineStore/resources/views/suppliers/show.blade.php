@extends('onlinestore::master')

@section('title', 'تفاصيل المورد')

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('purchases.suppliers.edit'))
                <a href="{{ route('store_suppliers.edit', $supplier->id) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('store_suppliers.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'تفاصيل المورد',
            'icon' => 'truck',
            'sub' => $supplier->trade_name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'الموردين' => route('store_suppliers.index'),
                $supplier->trade_name => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- بطاقة المورد الموحَّدة — نفسها في نموذج فاتورة الشراء وصفحتها وطباعتها --}}
        @include('onlinestore::suppliers.partials.supplier-card', ['supplier' => $supplier])

        <div class="stk-metrics">
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-dollar-sign"></i></span>
                <span class="stk-metric__label">الرصيد الحالي</span>
                <span class="stk-metric__value">{{ \App\Support\CurrencySettings::number(abs($ledgerAccount->balance ?? 0)) }} {{ $supplier->currency ?: \App\Support\CurrencySettings::code() }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-credit-card"></i></span>
                <span class="stk-metric__label">نوع الرصيد</span>
                <span class="stk-metric__value">{{ ($ledgerAccount->balance ?? 0) > 0.004 ? 'دائن — له علينا' : (($ledgerAccount->balance ?? 0) < -0.004 ? 'مدين — لنا عنده' : 'لا رصيد') }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-hash"></i></span>
                <span class="stk-metric__label">كود الحساب</span>
                <span class="stk-metric__value">{{ $ledgerAccount->code ?? '-' }}</span>
            </div>
            <div class="stk-metric">
                <span class="stk-metric__icon"><i class="feather icon-{{ $supplier->status == 1 ? 'check-circle' : 'x-circle' }}"></i></span>
                <span class="stk-metric__label">حالة المورد</span>
                <span class="stk-metric__value">{{ $supplier->status == 1 ? 'نشط' : 'موقوف' }}</span>
            </div>
        </div>

        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات المورد</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>اسم المورد</dt>
                                <dd><strong>{{ $supplier->trade_name }}</strong></dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>رقم المورد</dt>
                                <dd><span class="badge badge-light-info">{{ $supplier->number_suply ?: $supplier->id }}</span></dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>البريد الإلكتروني</dt>
                                <dd>
                                    @if ($supplier->email)
                                        <a href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a>
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>رقم الجوال</dt>
                                <dd class="stk-num">
                                    @if ($supplier->mobile)
                                        <a href="tel:{{ $supplier->mobile }}">{{ $supplier->mobile }}</a>
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>رقم الهاتف</dt>
                                <dd class="stk-num">
                                    @if ($supplier->phone)
                                        <a href="tel:{{ $supplier->phone }}">{{ $supplier->phone }}</a>
                                    @else
                                        <span class="text-muted">غير محدد</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="col-md-6">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الحالة</dt>
                                <dd>
                                    <span class="stk-state {{ $supplier->status == 1 ? 'stk-state--on' : 'stk-state--off' }}">
                                        {{ $supplier->status == 1 ? 'نشط' : 'موقوف' }}
                                    </span>
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>المسؤول عن المورد</dt>
                                <dd>
                                    @if ($supplier->employee)
                                        <span class="badge badge-light-primary">{{ $supplier->employee->full_name }}</span>
                                    @else
                                        <span class="text-muted">غير مسند — يراه من يرى كل الموردين فقط</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الرقم الضريبي</dt>
                                <dd>{{ $supplier->tax_number ?: 'غير محدد' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>السجل التجاري</dt>
                                <dd>{{ $supplier->commercial_registration ?: 'غير محدد' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإضافة</dt>
                                <dd class="stk-num">{{ $supplier->created_at?->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>آخر تحديث</dt>
                                <dd class="stk-num">{{ $supplier->updated_at?->format('Y-m-d H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-map-pin"></i> العنوان</h6>
                    </div>
                    <div class="card-body">
                        @if ($supplier->full_address)
                            <p class="mb-0">{{ $supplier->full_address }}</p>
                        @else
                            <p class="text-muted mb-0">لم يتم تحديد العنوان</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> ملاحظات</h6>
                    </div>
                    <div class="card-body">
                        @if ($supplier->notes)
                            <p class="mb-0">{{ $supplier->notes }}</p>
                        @else
                            <p class="text-muted mb-0">لا توجد ملاحظات</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- آخر فواتير ومرتجعات المورد — بعملة كل مستند --}}
        <div class="card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> آخر فواتير المورد</h6>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>رقم المستند</th>
                            <th class="text-center">النوع</th>
                            <th class="text-center">التاريخ</th>
                            <th class="text-center">الإجمالي</th>
                            <th class="text-center">المتبقي</th>
                            <th class="text-center">حالة الدفع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentInvoices as $doc)
                            @php
                                $docCurrency = $doc->currency ?: \App\Support\CurrencySettings::code();
                                $docIsReturn = $doc->isPurchaseReturn();
                            @endphp
                            <tr>
                                <td class="stk-num">{{ $doc->code }}</td>
                                <td class="text-center">
                                    <span class="badge badge-light-{{ $docIsReturn ? 'warning' : 'primary' }}">{{ $docIsReturn ? 'مرتجع' : 'فاتورة شراء' }}</span>
                                </td>
                                <td class="text-center stk-num">{{ \Illuminate\Support\Carbon::parse($doc->date)->format('Y-m-d') }}</td>
                                <td class="text-center stk-num">{{ \App\Support\CurrencySettings::number($doc->grand_total) }} {{ $docCurrency }}</td>
                                <td class="text-center stk-num">{{ $docIsReturn ? '—' : \App\Support\CurrencySettings::number($doc->due_value).' '.$docCurrency }}</td>
                                <td class="text-center">
                                    @if ($docIsReturn)
                                        —
                                    @else
                                        <span class="badge badge-light-{{ ['paid' => 'success', 'partially_paid' => 'warning'][$doc->payment_status] ?? 'danger' }}">
                                            {{ ['paid' => 'مدفوعة', 'partially_paid' => 'مدفوعة جزئياً'][$doc->payment_status] ?? 'آجلة / غير مدفوعة' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">لا فواتير على هذا المورد بعد.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (hasPermission(['purchases.suppliers.edit', 'purchases.suppliers.manage_opening_balance', 'purchases.suppliers.delete']))
            <div class="card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title"><i class="feather icon-settings"></i> الإجراءات</h6>
                </div>
                <div class="card-body">
                    <div class="btn-group" role="group">
                        @if (hasPermission('purchases.suppliers.edit'))
                            <a href="{{ route('store_suppliers.edit', $supplier->id) }}" class="btn btn-outline-primary">
                                <i class="feather icon-edit-2"></i> تعديل البيانات
                            </a>
                        @endif
                        @if (hasPermission('purchases.suppliers.manage_opening_balance') && hasPermission('purchases.suppliers.edit'))
                            <button type="button" class="btn btn-outline-info" id="openOpeningBalanceModal">
                                <i class="feather icon-plus-circle"></i> رصيد افتتاحي
                            </button>
                        @endif
                        @if (hasPermission('purchases.suppliers.delete'))
                            <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                                <i class="feather icon-trash-2"></i> حذف المورد
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if (hasPermission('purchases.suppliers.delete'))
        <!-- نموذج الحذف -->
        <form id="delete-form" action="{{ route('store_suppliers.destroy', $supplier->id) }}" method="POST" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    @endif

    @if (hasPermission('purchases.suppliers.manage_opening_balance') && hasPermission('purchases.suppliers.edit'))
        <!-- Modal Opening Balance -->
        <div class="modal fade" id="storeSupplierOpeningBalanceModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة / تعديل الرصيد الافتتاحي للمورد</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <form id="openingBalanceForm" method="POST" action="{{ route('store_suppliers.update-opening-balance', $supplier->id) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="opening_balance">المبلغ ({{ $supplier->currency ?: \App\Support\CurrencySettings::code() }})</label>
                                <input type="number" step="0.01" class="form-control" id="opening_balance"
                                    name="opening_balance" value="{{ old('opening_balance', (float) $supplier->opening_balance) }}" min="0" required placeholder="أدخل المبلغ الافتتاحي">
                            </div>
                            <div class="form-group">
                                <label for="balance_type">نوع الرصيد</label>
                                <select class="form-control" id="balance_type" name="balance_type" required>
                                    <option value="credit" @selected(optional($ledgerAccount)->opening_balance_type !== 'debit')>دائن (مستحق للمورد)</option>
                                    <option value="debit" @selected(optional($ledgerAccount)->opening_balance_type === 'debit')>مدين (مستحق عليه)</option>
                                </select>
                                <small class="text-muted">
                                    <i class="feather icon-info"></i>
                                    <strong>دائن:</strong> المورد له رصيد عندك |
                                    <strong>مدين:</strong> المورد مديون لك
                                </small>
                            </div>
                            <div class="alert alert-info">
                                <i class="feather icon-alert-circle"></i>
                                الرصيد الحالي: <strong>{{ \App\Support\CurrencySettings::number($ledgerAccount->balance ?? 0) }} {{ $supplier->currency ?: \App\Support\CurrencySettings::code() }}</strong>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> حفظ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function confirmDelete() {
            Swal.fire({
                title: 'تأكيد الحذف',
                text: 'هل أنت متأكد من حذف المورد ' + @json($supplier->trade_name) + '؟ هذا الإجراء لا يمكن التراجع عنه.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form').submit();
                }
            });
        }

        // فتح/إغلاق مودال الرصيد الافتتاحي بأيّ محرّكٍ حيّ: لوحة المتجر تحمّل
        // Bootstrap 4 (vendors.min.js) و5 معاً، وسمة data-bs-toggle وحدها قد لا تعمل
        (function () {
            var modalEl = document.getElementById('storeSupplierOpeningBalanceModal');
            var trigger = document.getElementById('openOpeningBalanceModal');

            if (!modalEl || !trigger) { return; }

            function toggle(show) {
                var jq = window.jQuery;
                if (jq && jq.fn && jq.fn.modal) { jq(modalEl).modal(show ? 'show' : 'hide'); return; }
                var B = window.bootstrap;
                if (B && B.Modal) {
                    var instance = B.Modal.getOrCreateInstance ? B.Modal.getOrCreateInstance(modalEl) : new B.Modal(modalEl);
                    show ? instance.show() : instance.hide();
                    return;
                }
                modalEl.classList.toggle('show', show);
                modalEl.style.display = show ? 'block' : 'none';
            }

            trigger.addEventListener('click', function () { toggle(true); });
            modalEl.addEventListener('click', function (e) {
                if (e.target.closest('[data-bs-dismiss="modal"]')) { toggle(false); }
            });
        })();

        // Handle opening balance form submission        var openingBalanceForm = document.getElementById('openingBalanceForm');
        if (openingBalanceForm) {
            openingBalanceForm.addEventListener('submit', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: 'تأكيد',
                    text: 'هل أنت متأكد من تحديث الرصيد الافتتاحي؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، حدّث',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'جاري الحفظ...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        this.submit();
                    }
                });
            });
        }

        @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح!',
                text: @json(session('success')),
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if (session('error'))
            Swal.fire({
                icon: 'error',
                title: 'حدث خطأ!',
                text: @json(session('error'))
            });
        @endif
    </script>
@endsection
