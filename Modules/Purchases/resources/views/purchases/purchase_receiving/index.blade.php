{{--
    فهرس استلامات المشتريات — على نمط شاشة عرض أمر الشراء (po-*)، لا
    النسخة الكاملة الحُلّة (sl-*) لفهرس أوامر الشراء: شاشةٌ جديدة لا داعي
    لإثقالها بلوحة إرشاد وبطاقات إحصاء واسعة قبل أن يستقرّ شكلها.
--}}
@extends('purchases::master')

@section('title')
    استلام المشتريات
@stop

@section('css')
    @include('purchases::partials.style')
    @include('components.hint-tips')
@endsection

@section('content')
    <div class="po-page">
        @include('purchases::partials.head', [
            'title' => 'استلام المشتريات',
            'crumbs' => [
                ['label' => 'استلام المشتريات'],
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="po-card">
            <div class="po-card__head">
                <h6 class="po-card__title">
                    <i class="fas fa-dolly"></i>سجلّ استلامات المشتريات
                </h6>
            </div>

            @if ($receivings->isEmpty())
                <div class="po-card__body">
                    <div class="po-empty">
                        <i class="fas fa-box-open"></i>
                        <h5>لا توجد استلامات مشتريات بعد.</h5>
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table modern-table mb-0">
                        <thead>
                            <tr>
                                <th>الرقم</th>
                                <th>أمر الشراء</th>
                                <th>المورد</th>
                                <th>المستودع</th>
                                <th>تاريخ الاستلام</th>
                                <th>الحالة</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($receivings as $receiving)
                                <tr>
                                    {{-- الرقمُ رابطٌ إلى الاستعراض — نفسُ وجهة زرّ «عرض» --}}
                                    <td>
                                        <a href="{{ route('PurchaseReceiving.show', $receiving->id) }}">
                                            <strong>{{ $receiving->code }}</strong>
                                        </a>
                                    </td>
                                    <td>
                                        @if ($receiving->purchaseOrder)
                                            <a href="{{ route('PurchaseOrders.show', $receiving->purchaseOrder->id) }}">{{ $receiving->purchaseOrder->code }}</a>
                                        @endif
                                    </td>
                                    <td>{{ $receiving->supplier->trade_name ?? $receiving->supplier->name ?? '—' }}</td>
                                    <td>{{ $receiving->storeHouse->name ?? '—' }}</td>
                                    <td class="num">{{ \App\Support\LanguageSettings::formatDate($receiving->receiving_date) }}</td>
                                    <td>@include('purchases::partials.status', ['status' => $receiving->status])</td>
                                    <td>
                                        <a href="{{ route('PurchaseReceiving.show', $receiving->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @include('components.custom-pagination', ['paginator' => $receivings])
    </div>
@endsection
