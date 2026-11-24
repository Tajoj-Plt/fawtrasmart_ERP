{{--
    جدول الموردين — يُعاد تصييره وحده في طلبات AJAX.

    التصميم منقول عن جدول «فواتير الشراء» بنيةً وأصنافاً: `table modern-table`،
    عمود `#`، عمود رئيسي بسطرين (اسم عريض فوق رقم رمادي)، شارة حالة، ثم زرّ
    `bg-gradient-info` بثلاث نقاط يفتح قائمة الإجراءات — والحالة الفارغة صفٌّ
    بأيقونة كبيرة باهتة لا كتلة مستقلّة.

    ما يأتي من الإعدادات:
      • العملة والكسور والفواصل ← «العملات»
      • صيغة التاريخ            ← إعدادات اللغة النشطة
      • الألوان والخط           ← «مظهر النظام» عبر متغيّرات CSS

    الدالّة `changeStatus()` التي تعتمدها شارة الحالة تبقى كما هي في الشاشة.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $suppliers
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;

    $unknown = __('purchase_cycle.empty.unknown');
@endphp

<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th class="text-center" style="width: 60px;">#</th>
                <th>{{ __('purchase_cycle.fields.supplier') }}</th>
                <th>{{ __('purchase_cycle.supplier.number') }}</th>
                <th>{{ __('purchase_cycle.supplier.mobile') }}</th>
                <th>{{ __('purchase_cycle.supplier.email') }}</th>
                <th class="text-center">{{ __('purchase_cycle.supplier.balance') }}</th>
                <th class="text-center">{{ __('purchase_cycle.fields.status') }}</th>
                <th class="text-center">{{ __('purchase_cycle.fields.datetime') }}</th>
                <th class="text-center" style="width: 80px;">{{ __('purchase_cycle.actions.options') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($suppliers as $supplier)
                @php
                    // بوابة الصلاحية الفعلية على المسار — نفس ما يحرس
                    // SupplierManagementController::edit()/update()/destroy()
                    $canEdit = hasPermission('purchases.suppliers.edit');
                    $canDelete = hasPermission('purchases.suppliers.delete');
                @endphp

                <tr class="supplier-row" data-supplier-id="{{ $supplier->id }}">
                    <td class="text-center">{{ $supplier->id }}</td>

                    <td>
                        <a href="{{ route('SupplierManagement.show', $supplier->id) }}">
                            <strong>{{ $supplier->trade_name ?: $unknown }}</strong>
                        </a>
                        @if ($supplier->full_address)
                            <br><small class="text-muted">
                                <i class="fas fa-map-marker-alt"></i>
                                {{ \Illuminate\Support\Str::limit($supplier->full_address, 40) }}
                            </small>
                        @endif
                    </td>

                    <td>{{ $supplier->number_suply ?: $unknown }}</td>
                    <td>{{ $supplier->mobile ?: ($supplier->phone ?: $unknown) }}</td>
                    <td>{{ $supplier->email ?: $unknown }}</td>

                    {{-- الرصيد بالعملة الافتراضية وكسورها كما تضبطها «العملات»، مع اتجاهه
                         بلغة غير محاسبية — الرقم وحده يقرؤه كثيرون بعكس اتجاهه --}}
                    <td class="text-center num">
                        {{ CurrencySettings::format(abs($supplier->account->balance ?? 0)) }}
                        <small class="text-muted d-block">
                            {{ ($supplier->account->balance ?? 0) >= 0 ? 'دائن' : 'مدين' }}
                        </small>
                    </td>

                    <td class="text-center">
                        @if ($supplier->status == 1)
                            <button type="button" class="po-status po-status--approved border-0"
                                onclick="changeStatus({{ $supplier->id }}, 0, @js($supplier->trade_name))"
                                title="{{ __('purchase_cycle.supplier.inactive') }}">
                                <i class="fas fa-check-circle"></i>{{ __('purchase_cycle.supplier.active') }}
                            </button>
                        @else
                            <button type="button" class="po-status po-status--rejected border-0"
                                onclick="changeStatus({{ $supplier->id }}, 1, @js($supplier->trade_name))"
                                title="{{ __('purchase_cycle.supplier.active') }}">
                                <i class="fas fa-ban"></i>{{ __('purchase_cycle.supplier.inactive') }}
                            </button>
                        @endif
                    </td>

                    <td class="text-center num">
                        <div>{{ LanguageSettings::formatDate($supplier->created_at) ?: '—' }}</div>
                        <small class="text-muted">
                            {{ $supplier->created_at?->timezone(LanguageSettings::timezone())?->format(LanguageSettings::timeFormat()) }}
                        </small>
                    </td>

                    <td class="text-center">
                        <div class="btn-group">
                            <div class="dropdown">
                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    aria-label="{{ __('purchase_cycle.actions.options') }}"></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('SupplierManagement.show', $supplier->id) }}">
                                        <i class="fas fa-eye me-2 text-primary"></i>{{ __('purchase_cycle.actions.show') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('SupplierManagement.statement', $supplier->id) }}">
                                        <i class="fas fa-file-invoice-dollar me-2 text-info"></i>{{ __('purchase_cycle.supplier.statement') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('PaymentSupplier.indexPurchase', ['supplier_id' => $supplier->id]) }}">
                                        <i class="fas fa-credit-card me-2 text-info"></i>{{ __('purchase_cycle.payment.section') }}
                                    </a>

                                    @if ($canEdit)
                                        <a class="dropdown-item" href="{{ route('SupplierManagement.edit', $supplier->id) }}">
                                            <i class="fas fa-edit me-2 text-success"></i>{{ __('purchase_cycle.actions.edit') }}
                                        </a>
                                    @endif
                                    @if ($canDelete)
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal"
                                            data-bs-target="#deleteModal{{ $supplier->id }}">
                                            <i class="fas fa-trash me-2"></i>{{ __('purchase_cycle.actions.delete') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($canDelete)
                            <div class="modal fade" id="deleteModal{{ $supplier->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title">{{ __('purchase_cycle.actions.delete') }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-start">
                                            <p>{{ __('purchase_orders.confirm.delete_text', ['code' => $supplier->trade_name]) }}</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                                {{ __('purchase_orders.confirm.no') }}
                                            </button>
                                            <form action="{{ route('SupplierManagement.destroy', $supplier->id) }}"
                                                method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">
                                                    {{ __('purchase_orders.confirm.yes_delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        <div class="sl-empty">
                            <span class="sl-empty__icon"><i class="fas fa-truck"></i></span>
                            <p class="sl-empty__title">
                                {{ request()->hasAny(['employee_search', 'supplier_number', 'email', 'mobile', 'phone', 'address', 'postal_code', 'currency', 'status', 'tax_number', 'commercial_registration', 'created_by']) ? __('purchase_cycle.empty.filtered') : __('purchase_cycle.supplier.empty_title') }}
                            </p>
                            <p class="sl-empty__hint">{{ __('purchase_cycle.supplier.empty_hint') }}</p>
                            @permission('purchases.suppliers.create')
                                <a href="{{ route('SupplierManagement.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>{{ __('purchase_cycle.actions.create') }}
                                </a>
                            @endpermission
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
