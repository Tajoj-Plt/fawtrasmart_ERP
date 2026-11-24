{{--
    جدول فواتير مشتريات المتجر — جزئية مستقلة كي يُعيد AJAX
    (StorePurchaseInvoiceController::getFilteredData) تصييرها عند البحث
    والتنقّل بين الصفحات دون إعادة تحميل الصفحة كاملة.

    الأعمدة يقرؤها `index.blade.php` بالترتيب للطباعة والتصدير
    (`getInvoicesTableData`) — أيّ عمودٍ يُضاف أو يُحذف هنا يُعدَّل هناك معه.
    والمعرّف `#invoicesTable` والصنف `.delete-invoice` يعتمد عليهما سكربت الصفحة.

    ما يُعرض ولماذا:
      • المورد باسمه ورقمه معاً — كما في بطاقة المورد الموحّدة، فموردان
        متشابها الاسم لا يلتبسان.
      • فرع الفاتورة — القائمة تجمع فواتير كل الفروع الواقعة في نطاق المستخدم.
      • التاريخ تاريخ الفاتورة (`date`) لا لحظة إدخالها.
      • المبلغ بعملة الفاتورة المحفوظة معها.
      • الإجراءات بصلاحيات المسارات نفسها وبأقفال المتحكّم: لا «تعديل» ولا
        «حذف» بعد اعتماد الإذن المخزني، ولا «دفعة» لفاتورة مسدَّدة أو مرتجع.
--}}
@php
    use App\Support\CurrencySettings;

    $defaultCurrency = CurrencySettings::code();
    $mayView = hasPermission('purchases.invoices.view');
    $mayEditAny = hasPermission('purchases.invoices.edit');
    $mayCreate = hasPermission('purchases.invoices.create');
    $mayDeleteAny = hasPermission('purchases.invoices.delete');
    $mayPayAny = hasPermission('purchases.payments.create');
@endphp

<div class="table-responsive">
    <table class="table table-hover mb-0" id="invoicesTable">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>رقم الفاتورة</th>
                <th>المورد</th>
                <th>الفرع</th>
                <th>تاريخ الفاتورة</th>
                <th class="text-center">المبلغ الإجمالي</th>
                <th class="text-center">المتبقّي</th>
                <th class="text-center">حالة الدفع</th>
                <th class="text-center">حالة الاستلام</th>
                <th class="text-center" style="width: 100px;">الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($purchaseData as $invoice)
                @php
                    $isReturn = $invoice->isPurchaseReturn();
                    $currency = $invoice->currency ?: $defaultCurrency;
                    $permitApproved = $invoice->warehousePermit && $invoice->warehousePermit->status === 'apporod';
                    $due = $isReturn ? 0.0 : max(0.0, (float) ($invoice->due_value ?? 0));
                    $canPayRow = ! $isReturn && $invoice->payment_status !== 'paid' && $due > 0.004;
                @endphp
                <tr>
                    <td class="stk-num">{{ $loop->iteration + ($purchaseData->currentPage() - 1) * $purchaseData->perPage() }}</td>
                    <td>
                        <div class="stk-media">
                            <span class="stk-media__thumb"><i class="feather icon-{{ $isReturn ? 'rotate-ccw' : 'file-text' }}"></i></span>
                            <span class="stk-media__body">
                                <span class="stk-media__title">
                                    @if ($mayView)
                                        <a href="{{ route('store_purchase_invoices.show', $invoice->id) }}">{{ $invoice->code }}</a>
                                    @else
                                        {{ $invoice->code }}
                                    @endif
                                    @if ($isReturn)
                                        <span class="badge badge-light-danger">مرتجع</span>
                                    @endif
                                </span>
                                @if (filled($invoice->reference_number))
                                    <span class="stk-media__sub">مرجع: {{ $invoice->reference_number }}</span>
                                @endif
                            </span>
                        </div>
                    </td>
                    <td>
                        {{ $invoice->supplier->name ?? 'غير محدد' }}
                        @if (filled($invoice->supplier->number_suply ?? null))
                            <small class="text-muted d-block" dir="ltr">#{{ $invoice->supplier->number_suply }}</small>
                        @endif
                    </td>
                    <td>{{ $invoice->branch->name ?? '—' }}</td>
                    <td class="stk-num">{{ \Carbon\Carbon::parse($invoice->date ?? $invoice->created_at)->format('Y-m-d') }}</td>
                    <td class="text-center">
                        <span class="badge badge-light-primary">{{ CurrencySettings::number($invoice->grand_total) }} {{ $currency }}</span>
                    </td>
                    <td class="text-center stk-num">
                        {{ $isReturn ? '—' : CurrencySettings::number($due).' '.$currency }}
                    </td>
                    <td class="text-center">
                        @if ($isReturn)
                            <span class="stk-state">مرتجع</span>
                        @elseif ($invoice->payment_status == 'paid')
                            <span class="stk-state stk-state--on">مدفوع</span>
                        @elseif ($invoice->payment_status == 'partially_paid')
                            <span class="stk-state">مدفوع جزئياً</span>
                        @else
                            <span class="stk-state stk-state--off">غير مدفوع</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if ($invoice->receiving_status == 'received')
                            <span class="stk-state stk-state--on">تم الاستلام</span>
                        @elseif (in_array($invoice->receiving_status, ['partially_receiv', 'partial_received', 'partially_received'], true))
                            <span class="stk-state">استلام جزئي</span>
                        @else
                            <span class="stk-state stk-state--off">لم يتم الاستلام</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="feather icon-more-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                @if ($mayView)
                                    <a class="dropdown-item" href="{{ route('store_purchase_invoices.show', $invoice->id) }}">
                                        <i class="feather icon-eye"></i> عرض التفاصيل
                                    </a>
                                @endif
                                @if (! $isReturn && ! $permitApproved && $mayEditAny)
                                    <a class="dropdown-item" href="{{ route('store_purchase_invoices.edit', $invoice->id) }}">
                                        <i class="feather icon-edit-2"></i> تعديل
                                    </a>
                                @endif
                                {{-- الدفع نافذةٌ في صفحة الفاتورة (تعرض المتبقّي وخزائن المتجر)
                                     — `#pay` يفتحها عند الوصول --}}
                                @if ($canPayRow && $mayPayAny && $mayView)
                                    <a class="dropdown-item" href="{{ route('store_purchase_invoices.show', $invoice->id) }}#pay">
                                        <i class="feather icon-credit-card"></i> تسجيل دفعة
                                    </a>
                                @endif
                                @if (! $isReturn && $invoice->type === 'invoice' && $mayCreate)
                                    <a class="dropdown-item" href="{{ route('store_purchase_invoices.create') }}?return_from={{ $invoice->id }}">
                                        <i class="feather icon-rotate-ccw"></i> إنشاء مرتجع
                                    </a>
                                @endif
                                @if ($mayView)
                                    <a class="dropdown-item" href="{{ route('store_purchase_invoices.print', $invoice->id) }}" target="_blank" rel="noopener">
                                        <i class="feather icon-printer"></i> طباعة
                                    </a>
                                @endif
                                {{-- بعد اعتماد الإذن تحرّكت البضاعة في المخزون: المتحكّم يرفض
                                     الحذف، فلا يُعرض زرٌّ نهايته رسالة رفض --}}
                                @if (! $permitApproved && $mayDeleteAny)
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger delete-invoice" href="#" data-id="{{ $invoice->id }}" data-code="{{ $invoice->code }}">
                                        <i class="feather icon-trash-2"></i> حذف
                                    </a>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">
                        <div class="stk-empty">
                            <span class="stk-empty__icon"><i class="feather icon-file-text"></i></span>
                            <h6 class="stk-empty__title">لا توجد فواتير شراء</h6>
                            <p class="stk-empty__hint">لا فواتير مطابقة في الفرع والنطاق الحاليين — عدّل الفلاتر أو أضف فاتورة.</p>
                            @if ($mayCreate)
                                <a href="{{ route('store_purchase_invoices.create') }}" class="btn btn-primary btn-sm">
                                    <i class="feather icon-plus"></i> إضافة فاتورة شراء جديدة
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('onlinestore::partials._smart-pagination', ['paginator' => $purchaseData, 'label' => 'فاتورة'])
