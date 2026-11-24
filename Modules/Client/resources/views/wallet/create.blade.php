{{-- إضافة حركة إلى محفظة عميل — النموذج في `wallet/_form` تشترك فيه مع التعديل --}}
@extends('client::master')

@section('title')
    حركة جديدة — محفظة {{ $client->display_name }}
@stop

@section('content')
@include('components.hint-tips')
    <div class="cl-scope">
        @section('page-actions')
            <a href="{{ route('clients.wallet.show', $client->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                <span>رجوع للمحفظة</span>
            </a>
        @endsection

        @include('client::components.page-head', [
            'title' => 'حركة جديدة',
            'icon' => 'plus-circle',
            'sub' => 'محفظة '.$client->display_name,
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'محافظ العملاء' => route('clients.wallet.index'),
                $client->display_name => route('clients.wallet.show', $client->id),
                'حركة جديدة' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @unless ($walletsParentMapped)
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                حساب <strong>«أرصدة محافظ العملاء»</strong> غير مربوط — اربطه من «الحسابات المحاسبية» قبل أي حركة.
            </div>
        @endunless

        <form action="{{ route('clients.wallet.store', $client->id) }}" method="POST">
            @include('client::wallet._form')
        </form>
    </div>
@endsection
