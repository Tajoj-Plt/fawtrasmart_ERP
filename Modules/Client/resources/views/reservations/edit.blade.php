@extends('client::reservations.master')

@section('title', __('reservations.form.edit_title', ['code' => $reservation->code]))

@section('content')
    @section('page-actions')
        <a href="{{ route('Reservations.show', $reservation->id) }}" class="btn btn-outline-secondary">
            <i class="feather icon-arrow-right"></i> {{ __('reservations.form.back_to_reservation') }}
        </a>
    @endsection

    {{-- كان هنا زرُّ «رجوع» مكرَّرٌ بعد الترويسة و`</div>` بلا فاتح يكسر تخطيطَ الصفحة --}}
    @include('client::reservations.components.page-head', [
        'title' => __('reservations.form.edit_title', ['code' => $reservation->code]),
        'icon' => 'edit-2',
        'sub' => __('reservations.form.status_now', ['status' => $reservation->status->label()]),
        'crumbs' => [
            __('reservations.menu.home') => route('dashboard'),
            __('reservations.menu.reservations') => route('Reservations.index'),
            $reservation->code => route('Reservations.show', $reservation->id),
            __('reservations.form.edit') => null,
        ],
    ])

    @include('client::reservations.partials.alerts')

    {{-- بفاتورةٍ قائمة — لا بمعرّفٍ يشير إلى فاتورةٍ حُذفت --}}
    @if ($reservation->isInvoiced())
        <div class="alert alert-info">
            <i class="feather icon-info"></i> {{ __('reservations.form.invoiced_locked') }}
        </div>
    @endif

    <form method="POST" action="{{ route('Reservations.update', $reservation->id) }}" id="rsv-form" class="mb-4">
        @csrf
        @method('PUT')
        @include('client::reservations.partials.form')
    </form>
@endsection

@section('scripts')
    @include('client::reservations.partials.form_scripts')
@endsection
