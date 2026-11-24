{{--
    قائمة الاستلام — ورقةُ أمين المستودع: ماذا يُخرِج، ومن أيّ مستودع،
    وبأيّ كمّية.

    **ما كان** — نسخةٌ حرفيّةٌ من «ملصق الطرد»: العنوان نفسه، والجدول
    نفسه، والأسعار نفسها. وأمين المستودع لا يعنيه السعر ولا يجوز أن
    يراه في ورقة تجهيز؛ يعنيه الصنف والمستودع والكمّية وخانةٌ يعلّم فيها
    ما جهّزه.

    فصارت بلا أسعار، وفيها عمود المستودع وخانةُ التعليم وتوقيعا
    المُجهِّز والمراجع.
--}}
@php
    use App\Support\LanguageSettings;
    use App\Support\OrganizationInfo;

    $client = $invoice->client;

    $clientName = $client
        ? ($client->trade_name ?: trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')))
        : __('sales_documents.ui.unknown');

    $lines = $invoice->items;
    $pieces = $lines->sum('quantity');

    // المستودعات المذكورة في البنود — الأمين يعرف من أين يجمع
    $warehouses = $lines
        ->map(fn ($line) => $line->storeHouse->name ?? null)
        ->filter()
        ->unique()
        ->values();
@endphp

@extends('sales::partials.print-document', [
    'title' => __('sales_documents.print.picklist') . ' — ' . $invoice->code,
    'barcode' => true,
])

@section('print-styles')
    /* خانةُ التعليم — يُعلّم فيها الأمين ما أخرجه فعلاً */
    .tick {
        display: inline-block;
        width: 6mm;
        height: 6mm;
        border: 1.2px solid #4a5160;
        border-radius: 2px;
    }
@endsection

@section('print-body')
    <div class="doc__head">
        <div>
            <h1 class="doc__title">{{ __('sales_documents.print.picklist') }}</h1>
            <div class="party__line party__line--muted">{{ $invoice->code }}</div>
        </div>

        <div class="doc__org">
            <strong>{{ OrganizationInfo::name() }}</strong>
            {{ OrganizationInfo::phone() }}
        </div>
    </div>

    <div class="doc__meta">
        <div><span>{{ __('sales_documents.print.invoice_no') }}:</span> <b>{{ $invoice->code }}</b></div>
        <div>
            <span>{{ __('sales_documents.print.invoice_date') }}:</span>
            <b>{{ LanguageSettings::formatDate($invoice->invoice_date ?: $invoice->created_at) }}</b>
        </div>
        <div>
            <span>{{ __('sales_documents.print.printed_at') }}:</span>
            <b>{{ LanguageSettings::formatDate(now()) }}</b>
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
        </div>

        <div class="party">
            <div class="party__label">{{ __('sales_documents.print.warehouse') }}</div>
            <div class="party__name">
                {{ $warehouses->isNotEmpty() ? $warehouses->implode('، ') : __('sales_documents.ui.dash') }}
            </div>
            <div class="party__line party__line--muted">{{ OrganizationInfo::address() }}</div>
        </div>
    </div>

    {{-- بلا أسعار: ورقةُ تجهيزٍ لا فاتورة --}}
    <table>
        <thead>
            <tr>
                <th class="num" style="width:12mm">{{ __('sales_documents.print.row') }}</th>
                <th>{{ __('sales_documents.print.item') }}</th>
                <th>{{ __('sales_documents.print.description') }}</th>
                <th style="width:32mm">{{ __('sales_documents.print.warehouse') }}</th>
                <th class="num" style="width:22mm">{{ __('sales_documents.print.unit') }}</th>
                <th class="num" style="width:20mm">{{ __('sales_documents.print.quantity') }}</th>
                <th class="num" style="width:22mm">{{ __('sales_documents.print.picked') }}</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td class="num">{{ $loop->iteration }}</td>
                    <td>{{ $line->item ?: ($line->product->name ?? '') }}</td>
                    <td>{{ $line->description ?: '—' }}</td>
                    <td>{{ $line->storeHouse->name ?? __('sales_documents.ui.dash') }}</td>
                    <td class="num">{{ $line->unit_name ?: '—' }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                    <td class="num"><span class="tick"></span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="num">{{ __('sales_documents.print.no_items') }}</td>
                </tr>
            @endforelse
        </tbody>

        <tfoot>
            <tr>
                <td colspan="5">{{ __('sales_documents.print.pieces') }}</td>
                <td class="num">{{ (int) $pieces }}</td>
                <td></td>
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
        <div>{{ __('sales_documents.print.checker') }}</div>
        <div>{{ __('sales_documents.print.receiver') }}</div>
    </div>
@endsection
