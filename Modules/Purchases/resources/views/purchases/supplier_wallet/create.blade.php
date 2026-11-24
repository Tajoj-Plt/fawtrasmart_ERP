{{-- إضافة حركة إلى محفظة مورد — النموذج في `_form` تشترك فيه مع التعديل --}}
@extends('purchases::master')

@php
    $displayName = $supplier->trade_name
        ?: (trim(($supplier->first_name ?? '').' '.($supplier->last_name ?? '')) ?: '#'.$supplier->id);
@endphp

@section('title')
    حركة جديدة — محفظة {{ $displayName }}
@stop

@section('content')
    @include('purchases::purchases.purchase_requests.partials.sales-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">
        @include('purchases::purchases.purchase_requests.partials.page-head', [
            'title' => 'حركة جديدة — محفظة '.$displayName,
            'icon' => 'plus-circle',
            'sub' => 'دفعة مقدَّمة أو استرداد أو سداد فاتورة — القيد يُكتب بحسب نوع الحركة',
            'crumbs' => [
                'محافظ الموردين' => route('purchases.supplier-wallet.index'),
                $displayName => route('purchases.supplier-wallet.show', $supplier->id),
                'حركة جديدة' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @unless ($walletsParentMapped)
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                حساب <strong>«دفعات مقدَّمة للموردين»</strong> غير مربوط — اربطه من «الحسابات المحاسبية» قبل أي حركة.
            </div>
        @endunless

        <form action="{{ route('purchases.supplier-wallet.store', $supplier->id) }}" method="POST">
            @include('purchases::purchases.supplier_wallet._form')
        </form>
    </div>
@endsection
