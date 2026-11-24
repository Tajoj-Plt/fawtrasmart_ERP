{{--
    معاينة الإذن داخل تبويب «معاينة الإذن» في شاشة العرض.

    كان هذا الملف مستنداً كاملاً (<html>/<body>) يُضمَّن داخل صفحة، وفيه:
    اسمُ منشأةٍ مكتوبٌ باليد، ورقمُ إذنٍ يُقرأ من عمودٍ لا وجود له (`code`)
    فيظهر «WP-001» دائماً، وفرعٌ يُقرأ من علاقةٍ لا عمود لها فيظهر «الفرع
    الرئيسي» لكل إذنٍ مهما كان فرعُ مخزنه، وحالةٌ تُقارَن بـ`approved` و
    `rejected` (والمخزَّن `apporod` و`rejec`) فتظهر «قيد المعالجة» دائماً،
    وصفّان وهميّان («منتج تجريبي») حين يخلو الإذن من البنود، وعملةٌ ثابتة.

    صار جزءاً من الصفحة يقرأ بيانات الإذن نفسها: الرقم، التاريخ، المخزن
    وفرعه (أو طرفا التحويل)، الحالة، وعملة المستند.
--}}
@php
    $previewType = (int) $permit->permission_type;
    $previewTypeLabel = [1 => 'إذن إضافة', 2 => 'إذن صرف', 3 => 'إذن تحويل', 4 => 'رصيد افتتاحي'][$previewType] ?? 'إذن مخزني';
    $previewStatus = match ((string) $permit->status) {
        'pending' => 'قيد الانتظار',
        'apporod' => 'موافق عليه',
        'rejec', 'rejected' => 'مرفوض',
        default => 'غير محدد',
    };
    $previewCurrency = $permit->currency ?: \App\Support\CurrencySettings::code();
    $previewDate = $permit->permission_date
        ? \Illuminate\Support\Carbon::parse($permit->permission_date)->format('Y-m-d H:i')
        : optional($permit->created_at)->format('Y-m-d H:i');
    $previewBranch = $previewType === 3
        ? (($permit->fromStoreHouse->branch->name ?? '—').' ← '.($permit->toStoreHouse->branch->name ?? '—'))
        : ($permit->storeHouse->branch->name ?? 'غير محدد');
    $previewWarehouse = $previewType === 3
        ? (($permit->fromStoreHouse->name ?? '—').' ← '.($permit->toStoreHouse->name ?? '—'))
        : ($permit->storeHouse->name ?? 'غير محدد');
@endphp

<style>
    .permit-preview { direction: rtl; color: #222; }
    .permit-preview__header { text-align: center; margin-bottom: 16px; border: 1px solid #000; padding: 10px; }
    .permit-preview__header h2 { margin: 0 0 6px; font-size: 20px; }
    .permit-preview__header p { margin: 2px 0; }
    .permit-preview table { width: 100%; margin: 14px 0; border-collapse: collapse; }
    .permit-preview .info-table td { padding: 5px; border-bottom: 1px dotted #999; }
    .permit-preview .products-table th,
    .permit-preview .products-table td { border: 1px solid #000; padding: 6px; text-align: center; }
    .permit-preview .totals-table td { padding: 5px; border: 1px solid #000; }
    .permit-preview__signatures { margin-top: 40px; display: flex; justify-content: space-between; text-align: center; }
    .permit-preview__signatures span { display: block; margin-top: 36px; border-top: 1px solid #000; width: 150px; }
</style>

<div class="permit-preview">
    <div class="permit-preview__header">
        <h2>{{ $previewTypeLabel }}</h2>
        <p>رقم الإذن: {{ $permit->number }}</p>
        <p>{{ $previewDate }}</p>
        <p>الحالة: {{ $previewStatus }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td>رقم الإذن:</td>
            <td>{{ $permit->number }}</td>
            <td>تاريخ الإذن:</td>
            <td>{{ $previewDate }}</td>
        </tr>
        <tr>
            <td>{{ $previewType === 3 ? 'من ← إلى:' : 'المستودع:' }}</td>
            <td>{{ $previewWarehouse }}</td>
            <td>الفرع:</td>
            <td>{{ $previewBranch }}</td>
        </tr>
        <tr>
            <td>مصدر الإذن:</td>
            <td>{{ \App\Constants\permissions\PermissionSources::getName($permit->permission_source_id ?? 0) }}</td>
            <td>أنشئ بواسطة:</td>
            <td>{{ $permit->user->name ?? '—' }}</td>
        </tr>
    </table>

    <table class="products-table">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>الكمية</th>
                <th>سعر الوحدة ({{ $previewCurrency }})</th>
                <th>الإجمالي ({{ $previewCurrency }})</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($permit->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->product->name ?? 'منتج غير محدد' }}</td>
                    <td>{{ \App\Support\CurrencySettings::quantity($item->quantity ?? 0) }}</td>
                    <td>{{ number_format((float) ($item->unit_price ?? 0), 2) }}</td>
                    <td>{{ number_format((float) ($item->total ?? (($item->quantity ?? 0) * ($item->unit_price ?? 0))), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">لا توجد بنود في هذا الإذن</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>عدد الأصناف:</td>
            <td>{{ $permit->items->count() }} صنف</td>
        </tr>
        <tr>
            <td>إجمالي الكمية:</td>
            <td>{{ \App\Support\CurrencySettings::quantity($permit->items->sum('quantity')) }}</td>
        </tr>
        <tr>
            <td>إجمالي القيمة:</td>
            <td><strong>{{ number_format((float) $permit->grand_total, 2) }} {{ $previewCurrency }}</strong></td>
        </tr>
    </table>

    @if ($permit->details)
        <div class="notes">
            <strong>ملاحظات:</strong> {{ $permit->details }}
        </div>
    @endif

    <div class="permit-preview__signatures">
        <div>توقيع مدير المستودع<span></span></div>
        <div>توقيع المستلم<span></span></div>
        <div>ختم المنشأة<span></span></div>
    </div>
</div>
