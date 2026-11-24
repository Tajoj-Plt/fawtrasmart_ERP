{{--
    ملصق التوصيل — يُلصَق على الشحنة فيقرؤه المندوب: من أين، وإلى أين،
    وكم يُحصَّل عند التسليم.

    **ما كان** — عنوانه «ملصق الطرد» مثل القالبين الآخرين، وفيه اسمُ
    الجهتين بلا هاتفٍ ولا عنوان — وهما كلُّ ما يحتاجه ملصقُ توصيل — ثمّ
    ينتهي المتن فارغاً بعد بطاقتَي الاسم.

    صار يحمل عنوانَ المستلم وهاتفه كاملين، وباركود رقم الفاتورة،
    والمبلغ المستحقّ عند التسليم محسوباً من متبقّي الفاتورة — فإن كانت
    مسدَّدة قيل ذلك صراحةً كي لا يُحصَّل مرّتين.
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
        ? trim(implode('، ', array_filter([
            $client->street1,
            $client->street2,
            $client->city,
            $client->region,
            $client->postal_code,
            $client->country,
        ])))
        : '';

    $lines = $invoice->items;
    $pieces = $lines->sum('quantity');

    /* المستحقّ عند التسليم — من متبقّي الفاتورة لا من إجماليها:
       فاتورةٌ سُدِّدت مقدّماً لا يُحصَّل عنها شيء. */
    $due = max(0, (float) ($invoice->due_value ?? 0) - (float) ($invoice->returned_payment ?? 0));
@endphp

@extends('sales::partials.print-document', [
    'title' => __('sales_documents.print.shipping_label') . ' — ' . $invoice->code,
    'barcode' => true,
])

@section('print-styles')
    .cod {
        border: 2px solid #1b1f27;
        border-radius: 3px;
        padding: 4mm;
        text-align: center;
        margin-bottom: 5mm;
    }

    .cod__label {
        font-size: 9.5pt;
        font-weight: 700;
        letter-spacing: .5px;
        color: #4a5160;
    }

    .cod__value {
        font-size: 22pt;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .cod__value--paid { font-size: 15pt; color: #1a7f4b; }

    .party--strong { border-width: 2px; border-color: #1b1f27; }
@endsection

@section('print-body')
    <div class="doc__head">
        <div>
            <h1 class="doc__title">{{ __('sales_documents.print.shipping_label') }}</h1>
            <div class="party__line party__line--muted">{{ $invoice->code }}</div>
        </div>

        <div class="doc__org">
            <strong>{{ OrganizationInfo::name() }}</strong>
            {{ OrganizationInfo::phone() }}
        </div>
    </div>

    <div class="doc__barcode">
        <svg class="barcode" data-value="{{ $invoice->code }}"></svg>
    </div>

    <div class="doc__parties">
        <div class="party">
            <div class="party__label">{{ __('sales_documents.print.sender') }}</div>
            <div class="party__name">{{ OrganizationInfo::name() }}</div>
            <div class="party__line">{{ OrganizationInfo::phone() ?: __('sales_documents.print.no_phone') }}</div>
            <div class="party__line">{{ OrganizationInfo::address() ?: __('sales_documents.print.no_address') }}</div>
        </div>

        {{-- عنوان المستلم هو المهمّ في الملصق، فله إطارٌ أثخن --}}
        <div class="party party--strong">
            <div class="party__label">{{ __('sales_documents.print.recipient') }}</div>
            <div class="party__name">{{ $clientName }}</div>
            <div class="party__line">
                {{ $client?->mobile ?: $client?->phone ?: __('sales_documents.print.no_phone') }}
            </div>
            <div class="party__line {{ $clientAddress ? '' : 'party__line--muted' }}">
                {{ $clientAddress ?: __('sales_documents.print.no_address') }}
            </div>
        </div>
    </div>

    <div class="cod">
        <div class="cod__label">{{ __('sales_documents.print.cod') }}</div>

        @if ($due > 0)
            <div class="cod__value">{{ CurrencySettings::format($due) }}</div>
        @else
            <div class="cod__value cod__value--paid">{{ __('sales_documents.print.cod_none') }}</div>
        @endif
    </div>

    <div class="doc__meta">
        <div><span>{{ __('sales_documents.print.invoice_no') }}:</span> <b>{{ $invoice->code }}</b></div>
        <div>
            <span>{{ __('sales_documents.print.invoice_date') }}:</span>
            <b>{{ LanguageSettings::formatDate($invoice->invoice_date ?: $invoice->created_at) }}</b>
        </div>
        <div><span>{{ __('sales_documents.print.lines') }}:</span> <b>{{ $lines->count() }}</b></div>
        <div><span>{{ __('sales_documents.print.pieces') }}:</span> <b>{{ (int) $pieces }}</b></div>
        <div>
            <span>{{ __('sales_documents.print.total') }}:</span>
            <b>{{ CurrencySettings::format($invoice->grand_total) }}</b>
        </div>
    </div>

    @if ($invoice->notes)
        <div class="party">
            <div class="party__label">{{ __('sales_documents.print.notes') }}</div>
            <div class="party__line">{{ $invoice->notes }}</div>
        </div>
    @endif

    <div class="doc__sign">
        <div>{{ __('sales_documents.print.sender') }}</div>
        <div>{{ __('sales_documents.print.receiver') }}</div>
    </div>
@endsection
