{{--
    تعديل حركة محفظة مورد — نفس نموذج الإضافة، ومعه ما كانت عليه الحركة.

    التعديل يُعيد كتابة القيد في مكانه ويعيد اشتقاق الأرصدة من القيود، ثم
    يُعيد ترتيب «الرصيد بعدها» على الحركات كلِّها — انظر
    `SupplierWalletService::updateTransaction()`.
--}}
@extends('purchases::master')

@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;

    $displayName = $supplier->trade_name
        ?: (trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? '')) ?: '#'.$supplier->id);
@endphp

@section('title')
    تعديل حركة #{{ $transaction->id }} — محفظة {{ $displayName }}
@stop

@section('content')
    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">
        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => 'تعديل حركة #'.$transaction->id,
            'icon' => 'pen',
            'sub' => 'محفظة '.$displayName,
            'crumbs' => [
                'محافظ الموردين' => route('purchases.supplier-wallet.index'),
                $displayName => route('purchases.supplier-wallet.show', $supplier->id),
                'تعديل حركة' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ما كانت عليه الحركة — يُقرأ قبل التبديل لا بعده --}}
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            الحركة الحالية: <strong>{{ $transaction->typeLabel() }}</strong>
            بمبلغ <strong>{{ CurrencySettings::format($transaction->amount) }}</strong>
            بتاريخ {{ LanguageSettings::formatDate($transaction->created_at, true) }}
            @if ($transaction->journal_entry_id)
                —
                <a href="{{ route('journal.show', $transaction->journal_entry_id) }}">
                    القيد #{{ $transaction->journal_entry_id }}
                </a>
            @endif
            <div class="mt-1">
                <small>
                    الحفظ يُعيد كتابة هذا القيد نفسه بالقيم الجديدة، ويعيد حساب الأرصدة من القيود،
                    ويُصحّح عمود «الرصيد بعدها» في الحركات التالية.
                </small>
            </div>
        </div>

        <form action="{{ route('purchases.supplier-wallet.update', [$supplier->id, $transaction->id]) }}" method="POST">
            @method('PUT')
            @include('purchases::purchases.supplier_wallet._form')
        </form>
    </div>
@endsection
