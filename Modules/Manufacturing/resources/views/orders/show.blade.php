@extends('manufacturing::master')

@section('title', $order->name)

@section('content')
    <div class="content-body">

        {{-- الأزرار تُحقن في ترويسة الصفحة الموحّدة --}}
        @section('page-actions')
            @include('manufacturing::orders.partials.action-buttons', ['order' => $order])
        @endsection

        @include('manufacturing::orders.partials.header', ['order' => $order])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')
        @include('layouts.alerts.success')

        <div class="card">
            @include('manufacturing::orders.partials.nav-tabs')

            <div class="tab-content p-2">
                @include('manufacturing::orders.partials.info-tab', ['order' => $order])
                @include('manufacturing::orders.partials.received-materials-tab', ['order' => $order])
                @include('manufacturing::orders.partials.finished-goods-tab', ['order' => $order])
                @include('manufacturing::orders.partials.waste-tab', ['order' => $order])
                @include('manufacturing::orders.partials.returned-materials-tab', ['order' => $order])
                @include('manufacturing::orders.partials.activities-tab', ['logs' => $logs ?? []])
            </div>
        </div>
    </div>

    @include('manufacturing::orders.partials.modals', [
        'order' => $order,
        'storehouse' => $storehouse ?? [],
    ])
@endsection

@section('scripts')
    @include('manufacturing::orders.partials.scripts')
@endsection
