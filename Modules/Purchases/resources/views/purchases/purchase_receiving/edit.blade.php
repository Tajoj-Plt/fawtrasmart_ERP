{{--
    تعديل استلام مشتريات — متاحٌ فقط بينما الاستلام لا يزال «بانتظار
    الاعتماد» (PurchaseReceivingController::edit/update يرفضان غير ذلك).
    نفس نمط create.blade.php (بطاقة بيانات + بطاقة بنود على قياس
    purchases::partials.form، بلا سكربت صفوفٍ ديناميكي).
--}}
@extends('purchases::master')

@section('title')
    تعديل استلام مشتريات — {{ $receiving->code }}
@stop

@section('css')
    @include('purchases::partials.style')
    @include('components.hint-tips')
@endsection

@section('content')
    <div class="po-page">
        @include('purchases::partials.head', [
            'title' => 'تعديل استلام مشتريات — '.$receiving->code,
            'crumbs' => [
                ['label' => 'استلام المشتريات', 'url' => route('PurchaseReceiving.index')],
                ['label' => $receiving->code, 'url' => route('PurchaseReceiving.show', $receiving->id)],
                ['label' => 'تعديل'],
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('purchases::components.guide', [
            'key' => 'purchase-receiving.edit',
            'title' => 'تعديل استلام مشتريات',
            'lead' => 'الاستلام هنا لا يزال «بانتظار الاعتماد» — يمكن تعديل المستودع والتاريخ والكمّيات المستلمة قبل اعتماده. بعد الاعتماد يُقفَل التعديل نهائياً.',
            'open' => false,
            'blocks' => [
                'ماذا يحدث بعد الحفظ؟' => [
                    'يبقى الاستلام «بانتظار الاعتماد» بعد التعديل — لا شيء يدخل المخزون بعد.',
                    'من شاشة عرض الاستلام: زر «اعتماد الاستلام» يُدخل الكمّيات المعدَّلة إلى المستودع المحدَّد.',
                ],
            ],
            'facts' => [
                'الكمّية المدخلة محدودة بالمتبقي من كل صنف — لا يمكن استلام أكثر من المطلوب.',
            ],
        ])

        <form action="{{ route('PurchaseReceiving.update', $receiving->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="po-card">
                <div class="po-card__head">
                    <h6 class="po-card__title">
                        <i class="fas fa-dolly"></i>بيانات الاستلام
                    </h6>
                </div>

                <div class="po-card__body">
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <div class="po-field">
                                <label for="store_house_id">
                                    المستودع المستلِم <span class="required">*</span>
                                </label>
                                <select name="store_house_id" id="store_house_id" class="form-control po-select2" required>
                                    @foreach ($storeHouses as $storeHouse)
                                        <option value="{{ $storeHouse->id }}" @selected($storeHouse->id == $receiving->store_house_id)>{{ $storeHouse->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="po-field">
                                <label for="receiving_date">
                                    تاريخ الاستلام <span class="required">*</span>
                                </label>
                                <input type="date" name="receiving_date" id="receiving_date" class="form-control"
                                    value="{{ \Illuminate\Support\Carbon::parse($receiving->receiving_date)->format('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="po-field">
                                <label for="supplier_display">المورد</label>
                                <input type="text" id="supplier_display" class="form-control po-locked" value="{{ $order->supplier->trade_name ?? $order->supplier->name ?? '' }}" readonly>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="po-field">
                                <label for="notes">ملاحظات</label>
                                <textarea name="notes" id="notes" class="form-control" rows="2">{{ $receiving->notes }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="po-card">
                <div class="po-card__head">
                    <h6 class="po-card__title">
                        <i class="fas fa-boxes"></i>بنود أمر الشراء
                    </h6>
                    <span class="po-hint">المتبقي أدناه يحتسب هذا الاستلام نفسه — يمكن إعادة توزيع كمّياته بحرّية.</span>
                </div>

                <div class="table-responsive">
                    <table class="table modern-table po-table po-doc-table mb-0">
                        <thead>
                            <tr>
                                <th>الصنف</th>
                                {{-- الوحدة عمودٌ قائمٌ بذاته كما في جدول بنود
                                     الدورة: كمّيةٌ تُقرأ بلا وحدتها تُستلَم
                                     بالحبّة وقد طُلبت بالكرتون --}}
                                <th style="width:120px">{{ __('purchase_orders.items.unit') }}</th>
                                <th class="text-center">الكمّية المطلوبة</th>
                                <th class="text-center">المتبقي (باحتساب هذا الاستلام)</th>
                                <th style="width:180px">الكمّية المستلمة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $i => $line)
                                @if ($line['remaining_to_receive'] > 0)
                                    <tr>
                                        @php
                                            /* `unit_name` على `InvoiceItem` مُشتقٌّ من
                                               `product_unit_id` ويُهمل العمود المخزَّن،
                                               فيعود فارغاً لبندٍ وحدتُه من وحدات الصنف —
                                               وتلك تُحفَظ بالاسم والمعامل وحدهما بلا صفٍّ
                                               في `product_units`. فيُقرأ العمود أوّلاً،
                                               ويبقى المشتقُّ بديلاً للبنود القديمة. */
                                            $storedUnit = $line['item']->getAttributes()['unit_name'] ?? null;
                                            $unitLabel = filled($storedUnit) ? $storedUnit : $line['item']->unit_name;
                                        @endphp
                                        <td data-label="الصنف">
                                            {{ $line['item']->item }}
                                            <input type="hidden" name="items[{{ $i }}][purchase_order_item_id]" value="{{ $line['item']->id }}">
                                        </td>
                                        <td data-label="{{ __('purchase_orders.items.unit') }}">
                                            @if (filled($unitLabel))
                                                {{ $unitLabel }}
                                            @else
                                                <span class="po-unit-none">&mdash;</span>
                                            @endif
                                        </td>
                                        <td class="text-center num" data-label="الكمّية المطلوبة">{{ \App\Support\LanguageSettings::formatNumber($line['ordered']) }}</td>
                                        <td class="text-center num" data-label="المتبقي">{{ \App\Support\LanguageSettings::formatNumber($line['remaining_to_receive']) }}</td>
                                        <td data-label="الكمّية المستلمة">
                                            <input type="number" step="0.0001" min="0"
                                                max="{{ $line['remaining_to_receive'] }}"
                                                name="items[{{ $i }}][received_quantity]"
                                                class="form-control po-input"
                                                value="{{ $line['current_quantity'] }}">
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="po-card__foot">
                    <a href="{{ route('PurchaseReceiving.show', $receiving->id) }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-{{ \App\Support\LanguageSettings::isRtl() ? 'right' : 'left' }} me-1"></i>رجوع
                    </a>

                    <div class="po-toolbar">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>حفظ التعديلات
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && jQuery.fn.select2) {
                jQuery('.po-select2').select2({ width: '100%' });
            }
        });
    </script>
@endsection
