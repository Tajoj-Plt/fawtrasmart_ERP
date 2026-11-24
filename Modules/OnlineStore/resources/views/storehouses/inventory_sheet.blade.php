@extends('onlinestore::master')

@section('title')
    ورقة الجرد - {{ $storehouse->name }}
@stop

@section('content')
    <div class="content-body">

        @section('page-actions')
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="feather icon-printer"></i> طباعة
            </button>
            <a href="{{ route('store_storehouses.show', $storehouse->id) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> رجوع
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'ورقة الجرد',
            'icon' => 'file-text',
            'sub' => 'مخزن: ' . $storehouse->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المخازن' => route('store_storehouses.index'),
                $storehouse->name => route('store_storehouses.show', $storehouse->id),
                'ورقة الجرد' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-list"></i> منتجات المخزن</h6>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الاسم</th>
                            <th>التصنيف</th>
                            <th class="text-center">العدد بالنظام</th>
                            <th style="width: 150px;">الكمية الفعلية</th>
                            <th style="width: 200px;">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td class="stk-num">{{ $product->product->barcode ?? '-' }}</td>
                                <td>{{ $product->product->name ?? '-' }}</td>
                                <td>{{ $product->product->category->name ?? 'غير مصنف' }}</td>
                                <td class="text-center stk-num">{{ $product->quantity }}</td>
                                <td></td>
                                <td></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="stk-empty">
                                        <span class="stk-empty__icon"><i class="feather icon-package"></i></span>
                                        <h6 class="stk-empty__title">لا توجد منتجات في هذا المخزن</h6>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<style>
    @media print {
        .stk-page-head, .btn, .breadcrumb { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        table { font-size: 12px; }
    }
</style>
@endsection
