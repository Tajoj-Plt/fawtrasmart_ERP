{{--
    تعديل حركة محفظة — نفس نموذج الإضافة، ومعه ما كانت عليه الحركة.

    التعديل يُعيد كتابة القيد في مكانه ويعيد اشتقاق الأرصدة من القيود، ثم
    يُعيد ترتيب «الرصيد بعدها» على الحركات كلِّها — انظر
    `ClientWalletService::updateTransaction()`.
--}}
@extends('client::master')

@section('title')
    تعديل حركة #{{ $transaction->id }} — محفظة {{ $client->display_name }}
@stop

@section('content')
@include('components.hint-tips')
    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;
    @endphp

    <div class="cl-scope">
        @section('page-actions')
            <a href="{{ route('clients.wallet.show', $client->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                <span>رجوع للمحفظة</span>
            </a>
        @endsection

        @include('client::components.page-head', [
            'title' => 'تعديل حركة #'.$transaction->id,
            'icon' => 'edit-2',
            'sub' => 'محفظة '.$client->display_name,
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'محافظ العملاء' => route('clients.wallet.index'),
                $client->display_name => route('clients.wallet.show', $client->id),
                'تعديل حركة' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        {{-- ما كانت عليه الحركة — يُقرأ قبل التبديل لا بعده --}}
        <div class="alert alert-info">
            <i class="feather icon-info"></i>
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

        <form action="{{ route('clients.wallet.update', [$client->id, $transaction->id]) }}" method="POST">
            @method('PUT')
            @include('client::wallet._form')
        </form>
    </div>
@endsection
