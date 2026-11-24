@extends('sales::master')

{{-- إنشاء اتفاقية تقسيط. تُفتَح على فاتورة: `?id=` معرّفُها. --}}

@section('title', __('sales_installments.create_title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('installments.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_installments.create_title'),
            'icon' => 'plus-circle',
            'sub' => __('sales_installments.create_subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_installments.title') => route('installments.index'),
                __('sales_installments.create_title') => null,
            ],
        ])

        @include('sales::components.system-context', ['only' => ['currency', 'payments', 'period']])

        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        @include('sales::installments.partials.guide-form', ['key' => 'installments.create'])

        <form method="POST" action="{{ route('installments.store') }}">
            @csrf

            @include('sales::installments.partials.form', [
                'agreement' => $agreement,
                'invoice' => $invoice,
                'code' => $code,
            ])

            <div class="sl-form-foot">
                <span class="sl-help">{{ __('sales_installments.hints.code') }}</span>

                <div class="d-flex" style="gap:.5rem">
                    <a href="{{ route('installments.index') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> {{ __('sales_settings.ui.cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
