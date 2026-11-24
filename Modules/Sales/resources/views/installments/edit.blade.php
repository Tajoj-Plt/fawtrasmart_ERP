@extends('sales::master')

{{-- تعديل اتفاقية — نفس نموذج الإنشاء، فلا نسختان تتباعدان.
     القالبان القديمان كانا نسختين اختلفتا فعلاً: أحدهما `name="installment number"`
     بفراغ، والآخر بلا زرّ إلغاءٍ يعمل. --}}

@section('title', __('sales_installments.edit_title', ['code' => $agreement->code]))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    <div class="content-body sl-scope">

        @section('page-actions')
            <a href="{{ route('installments.show', $agreement->id) }}" class="btn btn-outline-primary">
                <i class="feather icon-eye"></i> {{ __('sales_settings.ui.view') }}
            </a>

            <a href="{{ route('installments.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_installments.edit_title', ['code' => $agreement->code]),
            'icon' => 'edit-2',
            'sub' => __('sales_installments.edit_subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_installments.title') => route('installments.index'),
                $agreement->code => route('installments.show', $agreement->id),
                __('sales_settings.ui.edit') => null,
            ],
        ])

        @include('sales::components.system-context', ['only' => ['currency', 'payments', 'period']])

        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        @include('sales::installments.partials.guide-form', ['key' => 'installments.edit'])

        <form method="POST" action="{{ route('installments.update', $agreement->id) }}">
            @csrf
            @method('PUT')

            @include('sales::installments.partials.form', [
                'agreement' => $agreement,
                'invoice' => $invoice,
                'code' => $code,
                'hasPayments' => $hasPayments,
            ])

            <div class="sl-form-foot">
                <span class="sl-help">
                    {{ __('sales_installments.fields.created_at') }}:
                    <span class="sl-num">{{ optional($agreement->created_at)->format('Y-m-d H:i') }}</span>
                    @if ($agreement->creator)
                        — {{ __('sales_installments.fields.created_by') }}: {{ $agreement->creator->name }}
                    @endif
                </span>

                <div class="d-flex" style="gap:.5rem">
                    <a href="{{ route('installments.show', $agreement->id) }}" class="btn btn-outline-secondary">
                        <i class="feather icon-x"></i> {{ __('sales_settings.ui.cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary" @disabled($hasPayments)>
                        <i class="feather icon-save"></i> {{ __('sales_settings.ui.save') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
