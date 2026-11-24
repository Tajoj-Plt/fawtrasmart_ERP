{{--
    ملصق الطرد — يُلصَق على الصندوق فيُعرَف محتواه دون فتحه.

    **ما كان** — ثلاثة قوالبَ متطابقة، عنوانها جميعاً «ملصق الطرد»،
    تُبنى بـDompdf فتخرج «؟؟؟؟؟» لأنّ Dompdf لا يصل الحروف العربية،
    وباركودُها `<script>JsBarcode</script>` لا ينفّذه Dompdf أصلاً. وفي
    جدولها أربعة عناوين (البند/الوصف/الكمية/المجموع) وأربع خلايا تحمل
    (البند/الكمية/سعر الوحدة/المجموع) — فتقع الكميةُ تحت «الوصف»
    والسعرُ تحت «الكمية».

    صارت الثلاثة تُطبع من المتصفّح على `sales::partials.print-document`،
    ولكلٍّ منها محتواه: هذا يعرض ما في الطرد، و«قائمة الاستلام» تعرض ما
    يُجهَّز من المستودع بلا أسعار، و«ملصق التوصيل» يعرض عنوانَي المُرسِل
    والمستلم والمبلغ المستحقّ عند التسليم.
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;
    use App\Support\OrganizationInfo;

    $client = $invoice->client;

    $clientName = $client
        ? ($client->trade_name ?: trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')))
        : __('sales_documents.ui.unknown');

    $clientAddress = $client
        ? trim(implode('، ', array_filter([$client->street1, $client->city, $client->region, $client->country])))
        : '';

    $lines = $invoice->items;
    $pieces = $lines->sum('quantity');
@endphp

@extends('sales::partials.print-document', [
    'title' => __('sales_documents.print.parcel_label') . ' — ' . $invoice->code,
    'barcode' => true,
])

@section('print-body')
    <div class="doc__head">
        <div>
            <h1 class="doc__title">{{ __('sales_documents.print.parcel_label') }}</h1>
            <div class="party__line party__line--muted">{{ $invoice->code }}</div>
        </div>

        <div class="doc__org">
            <strong>{{ OrganizationInfo::name() }}</strong>
            {{ OrganizationInfo::phone() }}<br>
            {{ OrganizationInfo::email() }}
        </div>
    </div>

    <div class="doc__meta">
        <div><span>{{ __('sales_documents.print.invoice_no') }}:</span> <b>{{ $invoice->code }}</b></div>
        <div>
            <span>{{ __('sales_documents.print.invoice_date') }}:</span>
            <b>{{ LanguageSettings::formatDate($invoice->invoice_date ?: $invoice->created_at) }}</b>
        </div>
        <div><span>{{ __('sales_documents.print.lines') }}:</span> <b>{{ $lines->count() }}</b></div>
        <div><span>{{ __('sales_documents.print.pieces') }}:</span> <b>{{ (int) $pieces }}</b></div>
    </div>

    <div class="doc__barcode">
        <svg class="barcode" data-value="{{ $invoice->code }}"></svg>
    </div>

    <div class="doc__parties">
        <div class="party">
            <div class="party__label">{{ __('sales_documents.print.bill_to') }}</div>
            <div class="party__name">{{ $clientName }}</div>
            <div class="party__line">{{ $client?->mobile ?: $client?->phone ?: __('sales_documents.print.no_phone') }}</div>
            <div class="party__line {{ $clientAddress ? '' : 'party__line--muted' }}">
                {{ $clientAddress ?: __('sales_documents.print.no_address') }}
            </div>
        </div>

        <div class="party">
            <div class="party__label">{{ __('sales_documents.print.ship_to') }}</div>
            <div class="party__name">{{ $clientName }}</div>
            <div class="party__line {{ $clientAddress ? '' : 'party__line--muted' }}">
                {{ $clientAddress ?: __('sales_documents.print.no_address') }}
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="num" style="width:12mm">{{ __('sales_documents.print.row') }}</th>
                <th>{{ __('sales_documents.print.item') }}</th>
                <th>{{ __('sales_documents.print.description') }}</th>
                <th class="num" style="width:22mm">{{ __('sales_documents.print.unit') }}</th>
                <th class="num" style="width:20mm">{{ __('sales_documents.print.quantity') }}</th>
                <th class="num" style="width:28mm">{{ __('sales_documents.print.line_total') }}</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td class="num">{{ $loop->iteration }}</td>
                    <td>{{ $line->item ?: ($line->product->name ?? '') }}</td>
                    <td>{{ $line->description ?: '—' }}</td>
                    <td class="num">{{ $line->unit_name ?: '—' }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                    <td class="num">{{ CurrencySettings::format($line->total) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="num">{{ __('sales_documents.print.no_items') }}</td>
                </tr>
            @endforelse
        </tbody>

        <tfoot>
            <tr>
                <td colspan="4">{{ __('sales_documents.print.total') }}</td>
                <td class="num">{{ (int) $pieces }}</td>
                <td class="num">{{ CurrencySettings::format($invoice->grand_total) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($invoice->notes)
        <div class="party">
            <div class="party__label">{{ __('sales_documents.print.notes') }}</div>
            <div class="party__line">{{ $invoice->notes }}</div>
        </div>
    @endif

    <div class="doc__sign">
        <div>{{ __('sales_documents.print.picker') }}</div>
        <div>{{ __('sales_documents.print.receiver') }}</div>
    </div>
@endsection
