<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RecipientType;
use App\Models\Account;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Store\OnlineOrder;
use App\Models\Store\OnlineOrderStatusHistory;
use App\Models\Store\OnlineStoreSetting;
use App\Services\OnlineStoreSettingsService;
use App\Support\AccountingPeriodGuard;
use App\Support\CurrencySettings;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\TreasuryAccessGuard;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\OnlineStore\Http\Controllers\Concerns\PostsStoreOrderAccounting;
use Modules\OnlineStore\Http\Controllers\Concerns\RendersOnlineStoreDocumentTemplates;
use Modules\OnlineStore\Http\Controllers\Concerns\ResolvesStoreDefaults;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;
use Modules\OnlineStore\Http\Controllers\Concerns\ScopesStoreOrders;

/**
 * كنترولر إدارة الطلبات الإلكترونية
 * Online Order Management Controller
 */
class OnlineOrderController extends Controller
{
    use PostsStoreOrderAccounting, RendersOnlineStoreDocumentTemplates, ResolvesStoreDefaults, SanitizesFilters, ScopesStoreOrders;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.orders.view', ['only' => ['index']]);
        $this->middleware('permission.role:online_store.orders.statistics', ['only' => ['statistics']]);
        $this->middleware('permission.role:online_store.orders.show', ['only' => ['show']]);
        $this->middleware('permission.role:online_store.orders.update_status', ['only' => ['updateStatus', 'collectPayment']]);
        $this->middleware('permission.role:online_store.orders.update_shipping', ['only' => ['updateShipping']]);
        $this->middleware('permission.role:online_store.orders.add_note', ['only' => ['addNote']]);
        $this->middleware('permission.role:online_store.orders.cancel', ['only' => ['cancel']]);
        $this->middleware('permission.role:online_store.orders.refund', ['only' => ['refund']]);
        $this->middleware('permission.role:online_store.orders.print', ['only' => ['print', 'printShippingLabel', 'printInvoice']]);
        $this->middleware('permission.role:online_store.orders.export', ['only' => ['export']]);

        // فلاتر الرابط نصوصٌ لا مصفوفات — وإلا سقطت القوالب التي تطبعها
        $this->middleware(function ($request, $next) {
            $this->dropNonScalarFilters($request, ['search', 'status', 'payment_status', 'payment_method', 'date_from', 'date_to', 'delayed', 'period', 'page']);

            return $next($request);
        });
    }

    /** أسماء الحقول بالعربية لرسائل التحقق */
    private const ATTRIBUTES = [
        'status' => 'حالة الطلب',
        'notes' => 'الملاحظات',
        'location' => 'الموقع',
        'tracking_number' => 'رقم التتبع',
        'shipping_company' => 'شركة الشحن',
        'shipping_company_phone' => 'هاتف شركة الشحن',
        'driver_name' => 'اسم السائق',
        'driver_phone' => 'هاتف السائق',
        'estimated_delivery_date' => 'تاريخ التسليم المتوقع',
        'admin_notes' => 'الملاحظة',
        'cancel_reason' => 'سبب الإلغاء',
        'refund_reason' => 'سبب الاسترداد',
        'refund_amount' => 'مبلغ الاسترداد',
        'treasury_id' => 'الخزينة',
        'restock' => 'إعادة البضاعة للمستودع',
        'search' => 'البحث',
        'payment_status' => 'حالة الدفع',
        'payment_method' => 'طريقة الدفع',
        'date_from' => 'من تاريخ',
        'date_to' => 'إلى تاريخ',
    ];

    /**
     * عرض قائمة جميع الطلبات
     */
    public function index(Request $request)
    {
        // فلاتر مشوَّهة (حالة غير معروفة، تاريخ ليس تاريخاً) تُهمَل ولا تُسقط الصفحة
        $filters = validator($request->all(), [
            'search' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ])->valid();

        $query = $this->scopedOrders()->with(['client', 'items']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('shipping_name', 'like', "%{$search}%")
                    ->orWhere('shipping_phone', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('trade_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        foreach (['status', 'payment_status', 'payment_method'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // فلترة الطلبات المتأخرة
        if ($request->input('delayed') == '1') {
            $query->where('estimated_delivery_date', '<', now())
                ->whereNotIn('status', [OrderStatus::DELIVERED, OrderStatus::CANCELLED, OrderStatus::REFUNDED]);
        }

        $orders = $query->withCount('items')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // إحصائيات سريعة — بنفس نطاق الجدول أعلاه، وإلا اختلفت البطاقات عن محتواه
        $stats = [
            'total' => $this->scopedOrders()->count(),
            'pending' => $this->scopedOrders()->where('status', OrderStatus::PENDING)->count(),
            'processing' => $this->scopedOrders()->where('status', OrderStatus::PROCESSING)->count(),
            'shipped' => $this->scopedOrders()->where('status', OrderStatus::SHIPPED)->count(),
            'completed' => $this->scopedOrders()->where('status', OrderStatus::DELIVERED)->count(),
            'cancelled' => $this->scopedOrders()->where('status', OrderStatus::CANCELLED)->count(),
            'today_orders' => $this->scopedOrders()->whereDate('created_at', today())->count(),
            // مبيعات اليوم = غيرُ الملغي، صافياً من المردود — لا «المدفوع» وحده:
            // طلبُ الدفع عند الاستلام مبيعٌ مقيَّد وإن لم يُحصَّل بعد
            'today_revenue' => (float) $this->scopedOrders()->whereDate('created_at', today())
                ->where('status', '!=', OrderStatus::CANCELLED)
                ->sum(DB::raw('total_amount - COALESCE(refund_amount, 0)')),
        ];

        $orderStatuses = OrderStatus::cases();
        $paymentStatuses = PaymentStatus::cases();
        $paymentMethods = PaymentMethod::cases();

        return view('onlinestore::orders.index', compact(
            'orders',
            'stats',
            'orderStatuses',
            'paymentStatuses',
            'paymentMethods'
        ));
    }

    /**
     * عرض تفاصيل طلب محدد
     */
    public function show(OnlineOrder $order)
    {
        $this->ensureOrderInScope($order);

        $order->load([
            'client',
            'items.product',
            'statusHistory.updatedBy',
            'extraFees',
            'shippingMethod',
            'paymentMethod',
            'coupon',
            'address',
            'invoice',
        ]);

        $orderStatuses = OrderStatus::cases();

        // «مسترد» لا يُختار من القائمة: الاسترداد له نموذجُه (مبلغ + خزينة)
        $nextStatuses = array_values(array_filter(
            $order->status->allowedNextStatuses(),
            fn (OrderStatus $s) => $s !== OrderStatus::REFUNDED
        ));

        // خزائن المتجر التي يحقّ للمستخدم السحب منها (للردّ) والإيداع فيها (للتحصيل)
        $refundTreasuries = $this->storeTreasuryOptions('withdraw');
        $collectTreasuries = $this->storeTreasuryOptions('deposit');
        $defaultTreasuryId = $order->payment_account_id ?: $this->getDefaultTreasuryAccount()?->id;

        // قيود الطلب: البيع والتكلفة والتحصيل والمرتجعات — للعرض في صفحة الطلب
        $journalEntries = $this->orderJournalEntries($order);

        return view('onlinestore::orders.show', compact(
            'order',
            'orderStatuses',
            'nextStatuses',
            'refundTreasuries',
            'collectTreasuries',
            'defaultTreasuryId',
            'journalEntries'
        ));
    }

    /**
     * تحديث حالة الطلب — الانتقالات المسموحة فقط ({@see OrderStatus::allowedNextStatuses()}).
     *
     * كان يقبل أيَّ حالة من أيِّ حالة: ملغي ← تم التسليم، و«ملغي»/«مسترد» من
     * القائمة بلا أيّ قيدٍ عكسي (الإيراد والمخزون يبقيان والطلب «ملغي»).
     */
    public function updateStatus(Request $request, OnlineOrder $order)
    {
        $this->ensureOrderInScope($order);

        $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'notes' => 'nullable|string|max:500',
            'location' => 'nullable|string|max:255',
            'notify_customer' => 'nullable|boolean',
        ], ['status.enum' => 'حالة الطلب المختارة غير صحيحة.', 'status.Illuminate\Validation\Rules\Enum' => 'حالة الطلب المختارة غير صحيحة.'], self::ATTRIBUTES);

        $newStatus = OrderStatus::from($request->status);

        // الإلغاء من القائمة يمرّ بمسار الإلغاء نفسه (قيود عكسية + مخزون)
        if ($newStatus === OrderStatus::CANCELLED) {
            return $this->performCancel($order, $request->input('notes') ?: 'إلغاء من شاشة الطلب');
        }

        if ($newStatus === OrderStatus::REFUNDED) {
            return redirect()->back()->with('error', 'الاسترداد يتم من زر «استرداد المبلغ» لتحديد المبلغ والخزينة.');
        }

        // الفترة المحاسبية — تغيير الحالة قد يُنشئ قيد تسديد شركة الشحن
        if ($blocked = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            return redirect()->back()->with('error', $blocked);
        }

        $note = '';

        DB::beginTransaction();

        try {
            // قفلُ الصفّ: ضغطتان متتاليتان لا تنفّذان الانتقال مرّتين
            $order = OnlineOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $order->status;

            if (! in_array($newStatus, $oldStatus->allowedNextStatuses(), true)) {
                throw new \DomainException(
                    'لا يمكن تغيير حالة الطلب من «'.$oldStatus->label().'» إلى «'.$newStatus->label().'».'
                );
            }

            $order->update(['status' => $newStatus]);

            OnlineOrderStatusHistory::createRecord(
                $order->id,
                $newStatus->value,
                $request->notes,
                $request->location,
                auth()->id()
            );

            if ($newStatus === OrderStatus::CONFIRMED) {
                $order->forceFill(['confirmed_at' => now(), 'confirmed_by' => auth()->id()])->save();
            } elseif ($newStatus === OrderStatus::SHIPPED) {
                $order->update(['shipped_at' => $order->shipped_at ?? now()]);
                $this->createShippingPaymentEntry($order);
            } elseif ($newStatus === OrderStatus::DELIVERED) {
                $order->update(['delivered_at' => $order->delivered_at ?? now()]);

                if (! $order->isPaid()) {
                    $note = ' — تنبيه: قيمة الطلب لم تُحصَّل بعد، سجّل التحصيل من زر «تسجيل التحصيل».';
                }
            }

            OperationLog::record(
                'updated',
                'order',
                $order,
                'تم تغيير حالة الطلب **'.$order->order_number.'** من '.$oldStatus->label().' إلى '.$newStatus->label(),
                ['old' => ['status' => $oldStatus->value], 'new' => ['status' => $newStatus->value]]
            );

            DB::commit();
        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — التسجيل يكتب عبر نفس الاتصال
            DB::rollBack();

            if (! $e instanceof \DomainException) {
                OperationLog::failure($e, 'OnlineStore.OnlineOrder', 'updateStatus');
            }

            return redirect()->back()->with('error', $this->userMessage($e, 'حدث خطأ أثناء تحديث الحالة'));
        }

        // البريد بعد الاعتماد: فشلُ الإرسال لا يُلغي تغيير الحالة
        if ($request->boolean('notify_customer', true)) {
            $this->sendStatusChangeEmail($order, $newStatus, $oldStatus);
        }

        return redirect()->back()->with('success', 'تم تحديث حالة الطلب بنجاح'.$note);
    }

    /**
     * تحديث معلومات الشحن
     */
    public function updateShipping(Request $request, OnlineOrder $order)
    {
        $this->ensureOrderInScope($order);

        $request->validate([
            'tracking_number' => 'nullable|string|max:100',
            'shipping_company' => 'nullable|string|max:100',
            'shipping_company_phone' => 'nullable|string|max:20',
            'driver_name' => 'nullable|string|max:100',
            'driver_phone' => 'nullable|string|max:20',
            'estimated_delivery_date' => 'nullable|date',
            'update_status' => 'nullable|string|in:shipped',
        ], [], self::ATTRIBUTES);

        if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
            return redirect()->back()->with('error', 'لا يمكن تعديل شحن طلبٍ ملغي أو مسترد.');
        }

        // الفترة المحاسبية — قد يُنشئ قيد تسديد شركة الشحن عند التحويل لحالة "تم الشحن"
        if ($blocked = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            return redirect()->back()->with('error', $blocked);
        }

        $markShipped = false;

        DB::beginTransaction();

        try {
            $order = OnlineOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $order->update($request->only([
                'tracking_number',
                'shipping_company',
                'shipping_company_phone',
                'driver_name',
                'driver_phone',
                'estimated_delivery_date',
            ]));

            // التحويل إلى «تم الشحن» من هنا يخضع لنفس قاعدة الانتقالات
            if ($request->update_status === 'shipped' && $order->status !== OrderStatus::SHIPPED) {
                if (! in_array(OrderStatus::SHIPPED, $order->status->allowedNextStatuses(), true)) {
                    throw new \DomainException(
                        'لا يمكن تحويل الطلب إلى «تم الشحن» وهو في حالة «'.$order->status->label().'».'
                    );
                }

                $order->update(['status' => OrderStatus::SHIPPED, 'shipped_at' => now()]);

                OnlineOrderStatusHistory::createRecord(
                    $order->id,
                    OrderStatus::SHIPPED->value,
                    'تم الشحن - رقم التتبع: '.($request->tracking_number ?? 'غير محدد'),
                    null,
                    auth()->id()
                );

                $this->createShippingPaymentEntry($order);
                $markShipped = true;
            }

            OperationLog::record(
                'updated',
                'order',
                $order,
                'تم تحديث معلومات الشحن للطلب **'.$order->order_number.'**'.
                    ($request->tracking_number ? ' — رقم التتبع: '.$request->tracking_number : '')
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if (! $e instanceof \DomainException) {
                OperationLog::failure($e, 'OnlineStore.OnlineOrder', 'updateShipping');
            }

            return redirect()->back()->with('error', $this->userMessage($e, 'حدث خطأ أثناء التحديث'));
        }

        if ($markShipped) {
            $this->sendShippedEmail($order);
        }

        return redirect()->back()->with('success', $markShipped ? 'تم تحديث معلومات الشحن وحالة الطلب بنجاح' : 'تم تحديث معلومات الشحن بنجاح');
    }

    /**
     * إضافة ملاحظات إدارية
     */
    public function addNote(Request $request, OnlineOrder $order)
    {
        $this->ensureOrderInScope($order);

        $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ], [], self::ATTRIBUTES);

        $currentNotes = $order->admin_notes ?? '';
        $newNote = '['.now()->format('Y-m-d H:i').' - '.auth()->user()->name.'] '.$request->admin_notes;

        $order->update([
            'admin_notes' => $currentNotes ? $currentNotes."\n".$newNote : $newNote,
        ]);

        OperationLog::record('updated', 'order', $order, 'تمت إضافة ملاحظة إدارية للطلب **'.$order->order_number.'**');

        return redirect()->back()->with('success', 'تمت إضافة الملاحظة بنجاح');
    }

    /**
     * إلغاء الطلب (قبل الشحن): فاتورة مرتجع + عكس القيود + إعادة المخزون،
     * وردُّ ما قُبض فعلاً فقط.
     */
    public function cancel(Request $request, OnlineOrder $order)
    {
        $this->ensureOrderInScope($order);

        $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], [], self::ATTRIBUTES);

        return $this->performCancel($order, $request->cancel_reason);
    }

    /** مسارُ الإلغاء الواحد — من زرّ الإلغاء ومن قائمة الحالات. */
    private function performCancel(OnlineOrder $order, string $reason)
    {
        // الفترة المحاسبية — الإلغاء يُنشئ فاتورة مرتجع وقيوداً عكسية بتاريخ اليوم
        if ($blocked = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            return redirect()->back()->with('error', $blocked);
        }

        DB::beginTransaction();

        try {
            $order = OnlineOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatus::CANCELLED) {
                throw new \DomainException('الطلب ملغي مسبقاً');
            }

            if (! in_array(OrderStatus::CANCELLED, $order->status->allowedNextStatuses(), true)) {
                throw new \DomainException('لا يمكن إلغاء طلبٍ في حالة «'.$order->status->label().'» — استخدم «استرداد المبلغ» بعد الشحن.');
            }

            // طلبٌ محصَّل: الردّ من الخزينة التي قُبض فيها، ويلزم حقُّ السحب منها
            $treasury = $order->isPaid() ? $this->resolveRefundTreasury($order, null, $order->refundableAmount()) : null;

            $result = $this->reverseStoreOrder($order, $reason, 'cancel', null, $treasury);

            OperationLog::record(
                'cancelled',
                'order',
                $order,
                'تم إلغاء الطلب **'.$order->order_number.'** — السبب: '.$reason
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if (! $e instanceof \DomainException) {
                OperationLog::failure($e, 'OnlineStore.OnlineOrder', 'cancel');
            }

            return redirect()->back()->with('error', $this->userMessage($e, 'حدث خطأ أثناء إلغاء الطلب'));
        }

        $this->sendCancelledEmail($order, $reason);

        return redirect()->back()->with(
            'success',
            'تم إلغاء الطلب وعكس قيوده'.($result['refunded_cash'] > 0 ? ' وردّ '.$order->formatMoney($result['refunded_cash']).' من الخزينة' : '')
        );
    }

    /**
     * استرداد: كاملٌ (البضاعة تعود للمستودع وتُعكَس تكلفتُها) أو جزئي (مبلغٌ
     * يُخصم للعميل بلا بضاعة). المجموع التراكمي لا يتجاوز قيمة الطلب.
     *
     * كان «الاسترداد الجزئي» يعكس تكلفة المبيعات بكمياتٍ مقرَّبة دون أن يعيد
     * أيَّ مخزون، ويقبل استرداداً ثانياً فوق الأول بلا سقف، ويصرف من الخزينة
     * ولو لم يُحصَّل من العميل شيء.
     */
    public function refund(Request $request, OnlineOrder $order)
    {
        $this->ensureOrderInScope($order);

        $request->validate([
            'refund_reason' => 'required|string|max:500',
            'refund_amount' => 'required|numeric|gt:0|max:999999999',
            'treasury_id' => 'nullable|integer',
            'restock' => 'nullable|boolean',
        ], [], self::ATTRIBUTES);

        // الفترة المحاسبية — الاسترداد يُنشئ فاتورة مرتجع وقيوداً عكسية بتاريخ اليوم
        if ($blocked = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            return redirect()->back()->with('error', $blocked);
        }

        $amount = round((float) $request->refund_amount, 2);

        DB::beginTransaction();

        try {
            $order = OnlineOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
                throw new \DomainException('الطلب '.$order->status->label().' مسبقاً — لا يُستردّ مرّتين.');
            }

            if ($amount - $order->refundableAmount() > 0.005) {
                throw new \DomainException('أقصى مبلغ يمكن استرداده الآن: '.$order->formatMoney($order->refundableAmount()).'.');
            }

            $treasury = $order->isPaid()
                ? $this->resolveRefundTreasury($order, $request->integer('treasury_id') ?: null, $amount)
                : null;

            $result = $this->reverseStoreOrder(
                $order,
                $request->refund_reason,
                'refund',
                $amount,
                $treasury,
                $request->has('restock') ? $request->boolean('restock') : null
            );

            OperationLog::record(
                'refunded',
                'order',
                $order,
                'تم استرداد **'.number_format($amount, 2).'** من الطلب **'.$order->order_number.'** — السبب: '.$request->refund_reason
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if (! $e instanceof \DomainException) {
                OperationLog::failure($e, 'OnlineStore.OnlineOrder', 'refund');
            }

            return redirect()->back()->with('error', $this->userMessage($e, 'حدث خطأ أثناء الاسترداد'));
        }

        $this->sendRefundEmail($order, $amount, $request->refund_reason);

        $message = 'تم تسجيل الاسترداد وإنشاء فاتورة المرتجع';
        $message .= $result['restocked'] ? ' وإعادة البضاعة للمستودع' : '';
        $message .= $result['refunded_cash'] > 0 ? '' : ' — الطلب لم يكن محصَّلاً فخُفِّضت ذمّة العميل دون صرفٍ من الخزينة';

        return redirect()->back()->with('success', $message);
    }

    /**
     * تسجيل تحصيل قيمة الطلب (الدفع عند الاستلام / التحويل البنكي):
     * مدين الخزينة — دائن ذمّة العميل.
     */
    public function collectPayment(Request $request, OnlineOrder $order)
    {
        $this->ensureOrderInScope($order);

        $request->validate([
            'treasury_id' => 'nullable|integer',
        ], [], self::ATTRIBUTES);

        if ($blocked = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            return redirect()->back()->with('error', $blocked);
        }

        DB::beginTransaction();

        try {
            $order = OnlineOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isPaid()) {
                throw new \DomainException('قيمة الطلب محصَّلة مسبقاً.');
            }

            if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
                throw new \DomainException('لا يُحصَّل طلبٌ '.$order->status->label().'.');
            }

            $treasury = $this->resolveStoreTreasury($request->integer('treasury_id') ?: null, 'deposit');
            $amount = $order->collectibleAmount();

            $this->postOrderCollection($order, $treasury);

            OnlineOrderStatusHistory::createRecord(
                $order->id,
                $order->status->value,
                'تم تحصيل '.number_format($amount, 2).' في «'.$treasury->name.'»',
                null,
                auth()->id()
            );

            OperationLog::record(
                'updated',
                'order',
                $order,
                'تم تحصيل **'.number_format($amount, 2).'** للطلب **'.$order->order_number.'** في '.$treasury->name
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if (! $e instanceof \DomainException) {
                OperationLog::failure($e, 'OnlineStore.OnlineOrder', 'collectPayment');
            }

            return redirect()->back()->with('error', $this->userMessage($e, 'حدث خطأ أثناء تسجيل التحصيل'));
        }

        return redirect()->back()->with('success', 'تم تسجيل تحصيل '.$order->formatMoney($amount).' في «'.$treasury->name.'»');
    }

    /**
     * خزائنُ المتجر التي يحقّ للمستخدم السحبُ منها / الإيداعُ فيها.
     *
     * @return Collection<int, Account>
     */
    private function storeTreasuryOptions(string $action): Collection
    {
        return Account::onlineStore()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'balance'])
            ->filter(fn (Account $account) => TreasuryAccessGuard::blockReason($account->id, $action) === null)
            ->values();
    }

    /**
     * خزينةٌ من خزائن المتجر حصراً — المختارة، وإلا الافتراضية من إعداداته —
     * وبشرط أن يملك المستخدم حقَّ الحركة عليها. لا خزينةَ «أيّاً كانت».
     */
    private function resolveStoreTreasury(?int $requestedId, string $action): Account
    {
        $account = $requestedId
            ? Account::onlineStore()->find($requestedId)
            : $this->getDefaultTreasuryAccount();

        if (! $account) {
            throw new \DomainException($requestedId
                ? 'الخزينة المختارة ليست من خزائن المتجر.'
                : 'لم تُحدَّد الخزينة/البنك الافتراضي للمتجر. الرجاء ضبطه من إعدادات المتجر.');
        }

        if ($reason = TreasuryAccessGuard::blockReason($account->id, $action)) {
            throw new \DomainException($reason);
        }

        return $account;
    }

    /**
     * خزينةُ الردّ: المختارة، وإلا التي قُبض فيها الطلب، وإلا افتراضيةُ المتجر —
     * وبرصيدٍ يكفي المبلغ: لا تُصرف نقودٌ ليست في الصندوق.
     */
    private function resolveRefundTreasury(OnlineOrder $order, ?int $requestedId, float $amount): Account
    {
        if (! $requestedId && $order->payment_account_id) {
            $received = Account::find($order->payment_account_id);

            // قُبض في حساب تسوية بوّابة (ليس خزينةَ متجر): يُردّ من حيث قُبض
            if ($received && ! $received->is_store_treasury) {
                return $received;
            }

            $requestedId = $received?->id;
        }

        $treasury = $this->resolveStoreTreasury($requestedId, 'withdraw');

        if ((float) $treasury->balance + 0.005 < $amount) {
            throw new \DomainException(
                'رصيد «'.$treasury->name.'» ('.number_format((float) $treasury->balance, 2).') لا يكفي لردّ '.number_format($amount, 2).'.'
            );
        }

        return $treasury;
    }

    /** قيودُ الطلب كلُّها: فاتورته ومرتجعاتها. */
    private function orderJournalEntries(OnlineOrder $order): Collection
    {
        if (! $order->invoice_id) {
            return collect();
        }

        $invoiceIds = Invoice::where('type', 'returned')
            ->where('reference_number', (string) $order->invoice_id)
            ->pluck('id')
            ->push($order->invoice_id);

        return JournalEntry::with('details.account:id,name,code')
            ->whereIn('invoice_id', $invoiceIds)
            ->orderBy('id')
            ->get();
    }

    /** رسالةُ المستخدم: نصُّ خطأ القاعدة كما هو، وغيرُه بعنوانٍ عامّ. */
    private function userMessage(\Throwable $e, string $fallback): string
    {
        return $e instanceof \DomainException || $e instanceof \RuntimeException
            ? $e->getMessage()
            : $fallback.'. حاول مرة أخرى أو راجع سجل الأخطاء.';
    }

    /**
     * طباعة الطلب
     */
    /**
     * `$template` معرّفُ قالبٍ من «قوالب المستندات» (نوع `online_order`)،
     * أو لا شيء فيُعرَض القالب المدمج كما كان — القالب المدمج احتياطٌ
     * لا يُنزَع، لا بديلٌ يُنافسه.
     */
    public function print(OnlineOrder $order, $template = null)
    {
        $this->ensureOrderInScope($order);

        if ($html = $this->renderOnlineStoreTemplate('online_order', $order->id, $template)) {
            OperationLog::record('printed', 'OnlineStore.OnlineOrder', $order, 'طباعة طلب متجر رقم '.$order->order_number);

            return response(DocumentPrintPage::wrap($html));
        }

        $order->load(['client', 'items.product', 'extraFees']);

        return view('onlinestore::orders.print', compact('order'));
    }

    /**
     * طباعة بوليصة الشحن
     */
    public function printShippingLabel(OnlineOrder $order)
    {
        $this->ensureOrderInScope($order);

        return view('onlinestore::orders.shipping-label', compact('order'));
    }

    /**
     * طباعة فاتورة الطلب
     * Print order invoice
     */
    public function printInvoice(OnlineOrder $order)
    {
        $this->ensureOrderInScope($order);

        $order->load(['client', 'items.product', 'invoice.items', 'shippingMethod']);

        if (! $order->invoice) {
            return redirect()->back()->with('error', 'لا توجد فاتورة مرتبطة بهذا الطلب');
        }

        // جلب إعدادات المتجر
        $settings = OnlineStoreSetting::all()->mapWithKeys(function ($item) {
            return [$item->key => OnlineStoreSetting::castValue($item->value, $item->type)];
        })->toArray();

        // عملةُ الطلب المخزَّنة عليه — لا عملة النظام الحالية: تغييرُها لاحقاً
        // لا يغيّر رمزَ فاتورةٍ صدرت
        $settings['currency_symbol'] = trim(str_replace(CurrencySettings::number(0), '', $order->formatMoney(0)));

        $invoice = $order->invoice;

        return view('onlinestore::orders.print-invoice', compact('order', 'invoice', 'settings'));
    }

    /**
     * تصدير الطلبات
     */
    public function export(Request $request)
    {
        // TODO: تصدير الطلبات إلى Excel
        return redirect()->back()->with('info', 'جاري تطوير خاصية التصدير');
    }

    /**
     * الحصول على الحالات التالية المتاحة
     */
    private function getNextAvailableStatuses(OrderStatus $currentStatus): array
    {
        // استخدام الدالة المدمجة في الـ Enum
        return $currentStatus->allowedNextStatuses();
    }

    /**
     * الحصول على إحصائيات الطلبات
     */
    public function statistics(Request $request)
    {
        $period = in_array($request->get('period'), ['today', 'week', 'month', 'year'], true) ? $request->get('period') : 'month';

        $startDate = match ($period) {
            'today' => today(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        // نفس تصفية الفرع النشط (forBranch) المطبَّقة في index() — وإلا
        // اختلفت هذه الإحصائيات عن قائمة الطلبات المعروضة فعلياً للمستخدم
        $branchId = $this->storeBranchNode();

        $stats = [
            'total_orders' => OnlineOrder::forBranch($branchId)->where('created_at', '>=', $startDate)->count(),
            'total_revenue' => OnlineOrder::forBranch($branchId)->where('created_at', '>=', $startDate)
                ->where('status', '!=', OrderStatus::CANCELLED)
                ->sum(DB::raw('total_amount - COALESCE(refund_amount, 0)')),
            'average_order_value' => OnlineOrder::forBranch($branchId)->where('created_at', '>=', $startDate)
                ->where('status', '!=', OrderStatus::CANCELLED)
                ->avg(DB::raw('total_amount - COALESCE(refund_amount, 0)')),
            'completion_rate' => $this->calculateCompletionRate($startDate, $branchId),
            'cancellation_rate' => $this->calculateCancellationRate($startDate, $branchId),
            'orders_by_status' => OnlineOrder::forBranch($branchId)->where('created_at', '>=', $startDate)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
            'orders_by_payment' => OnlineOrder::forBranch($branchId)->where('created_at', '>=', $startDate)
                ->select('payment_method', DB::raw('count(*) as count'))
                ->groupBy('payment_method')
                ->pluck('count', 'payment_method'),
        ];

        return view('onlinestore::orders.statistics', compact('stats', 'period'));
    }

    /**
     * حساب نسبة الإكمال
     */
    private function calculateCompletionRate($startDate, ?int $branchId = null): float
    {
        $total = OnlineOrder::forBranch($branchId)->where('created_at', '>=', $startDate)->count();
        if ($total === 0) {
            return 0;
        }

        $completed = OnlineOrder::forBranch($branchId)->where('created_at', '>=', $startDate)
            ->where('status', OrderStatus::DELIVERED)
            ->count();

        return round(($completed / $total) * 100, 2);
    }

    /**
     * حساب نسبة الإلغاء
     */
    private function calculateCancellationRate($startDate, ?int $branchId = null): float
    {
        $total = OnlineOrder::forBranch($branchId)->where('created_at', '>=', $startDate)->count();
        if ($total === 0) {
            return 0;
        }

        $cancelled = OnlineOrder::forBranch($branchId)->where('created_at', '>=', $startDate)
            ->where('status', OrderStatus::CANCELLED)
            ->count();

        return round(($cancelled / $total) * 100, 2);
    }

    /**
     * تسويةُ شركة الشحن عند الشحن — الشحنُ مدفوعٌ للعميل أو مجانيٌّ له: القيود
     * في {@see PostsStoreOrderAccounting::postOrderCarrierSettlement()}.
     */
    private function createShippingPaymentEntry(OnlineOrder $order): void
    {
        $order->loadMissing('shippingMethod');

        $this->postOrderCarrierSettlement($order);
    }

    // ==================== دوال البريد الإلكتروني ====================

    /**
     * إرسال بريد تغيير الحالة
     */
    private function sendStatusChangeEmail(OnlineOrder $order, OrderStatus $newStatus, OrderStatus $oldStatus): void
    {
        try {
            $order->load('client');

            if (! $order->client || ! $order->client->email) {
                return;
            }

            // تحديد القالب المناسب حسب الحالة
            // أكواد القوالب الفعلية في جدول email_templates (البادئة المفردة
            // `order.` لا `orders.` — كانت مكتوبة خطأً فيُسقط الإرسال بصمت)
            $templateCode = match ($newStatus) {
                OrderStatus::CONFIRMED => 'order.confirmed',
                OrderStatus::PROCESSING => 'order.processing',
                OrderStatus::READY_FOR_SHIPPING => 'order.processing',
                OrderStatus::SHIPPED => 'order.shipped',
                OrderStatus::OUT_FOR_DELIVERY => 'order.out_for_delivery',
                OrderStatus::DELIVERED => 'order.delivered',
                OrderStatus::CANCELLED => 'order.cancelled',
                OrderStatus::RETURNED => 'order.return_requested',
                OrderStatus::REFUNDED => 'order.refunded',
                default => null,
            };

            if (! $templateCode) {
                return;
            }

            $storeInfo = OnlineStoreSettingsService::getStoreInfo();
            $currencySettings = OnlineStoreSettingsService::getCurrencySettings();

            $variables = [
                'customer_first_name' => $order->client->first_name ?? $order->shipping_name ?? 'عزيزي العميل',
                'order_number' => $order->order_number,
                'merchant_name' => $storeInfo['store_name'] ?? 'المتجر',
                'total_amount' => number_format($order->total_amount, 2),
                'currency' => $order->documentCurrency(),
                'estimated_ready_time' => $order->estimated_delivery_date ? $order->estimated_delivery_date->format('Y-m-d') : 'سيتم إعلامك قريباً',
                'tracking_number' => $order->tracking_number ?? 'غير متوفر',
                'estimated_delivery' => $order->estimated_delivery_date ? $order->estimated_delivery_date->format('Y-m-d') : 'سيتم إعلامك قريباً',
                'review_link' => url('/store/orders/'.$order->id.'/review'),
                'branch_name' => $storeInfo['store_name'] ?? 'المتجر',
                'branch_address' => $this->formatAddress($order),
            ];

            EmailTemplate::sendTemplatedEmail(
                to: $order->client->email,
                templateCode: $templateCode,
                variables: $variables,
                recipientType: RecipientType::Customer,
                recipientId: $order->client_id
            );

        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.OnlineOrder', 'sendStatusChangeEmail', $order);
        }
    }

    /**
     * إرسال بريد الشحن
     */
    private function sendShippedEmail(OnlineOrder $order): void
    {
        try {
            $order->load('client');

            if (! $order->client || ! $order->client->email) {
                return;
            }

            $variables = [
                'customer_first_name' => $order->client->first_name ?? $order->shipping_name ?? 'عزيزي العميل',
                'order_number' => $order->order_number,
                'tracking_number' => $order->tracking_number ?? 'غير متوفر',
                'estimated_delivery' => $order->estimated_delivery_date ? $order->estimated_delivery_date->format('Y-m-d') : 'سيتم إعلامك قريباً',
            ];

            EmailTemplate::sendTemplatedEmail(
                to: $order->client->email,
                templateCode: 'order.shipped',
                variables: $variables,
                recipientType: RecipientType::Customer,
                recipientId: $order->client_id
            );

        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.OnlineOrder', 'sendShippedEmail', $order);
        }
    }

    /**
     * إرسال بريد الإلغاء
     */
    private function sendCancelledEmail(OnlineOrder $order, string $reason): void
    {
        try {
            $order->load('client');

            if (! $order->client || ! $order->client->email) {
                return;
            }

            $variables = [
                'order_number' => $order->order_number,
                'cancellation_reason' => $reason,
            ];

            EmailTemplate::sendTemplatedEmail(
                to: $order->client->email,
                templateCode: 'order.cancelled',
                variables: $variables,
                recipientType: RecipientType::Customer,
                recipientId: $order->client_id
            );

        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.OnlineOrder', 'sendCancelledEmail', $order);
        }
    }

    /**
     * إرسال بريد الاسترداد
     */
    private function sendRefundEmail(OnlineOrder $order, float $amount, string $reason): void
    {
        try {
            $order->load('client');

            if (! $order->client || ! $order->client->email) {
                return;
            }

            $currencySettings = OnlineStoreSettingsService::getCurrencySettings();

            $variables = [
                'order_number' => $order->order_number,
                'amount' => number_format($amount, 2),
                'currency' => $order->documentCurrency(),
                'refund_reason' => $reason,
            ];

            EmailTemplate::sendTemplatedEmail(
                to: $order->client->email,
                templateCode: 'order.refunded',
                variables: $variables,
                recipientType: RecipientType::Customer,
                recipientId: $order->client_id
            );

        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.OnlineOrder', 'sendRefundEmail', $order);
        }
    }

    /**
     * تنسيق عنوان الشحن
     */
    private function formatAddress(OnlineOrder $order): string
    {
        $parts = array_filter([
            $order->shipping_address,
            $order->shipping_city,
            $order->shipping_region,
        ]);

        return implode(', ', $parts) ?: 'غير محدد';
    }
}
