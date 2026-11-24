{{--
    إنشاء استلام مشتريات — على قياس شاشات دورة المشتريات (`po-*`، نفس نظام
    `purchases::partials.form`): بطاقة بيانات برأسٍ وحقول `po-field`،
    بطاقة بنود بجدول `modern-table`، وذيلٌ بزرّي رجوع/حفظ. لا صفوفَ تُضاف
    أو تُحذف هنا (الأصناف ثابتة من أمر الشراء)، فلا حاجة لسكربت
    `purchase-orders-form.js` الديناميكي — النمط البصري وحده هو المطلوب.
--}}
@extends('purchases::master')

@section('title')
    استلام مشتريات جديد — أمر شراء {{ $order->code }}
@stop

@section('css')
    @include('purchases::partials.style')
    @include('components.hint-tips')
@endsection

@section('content')
    <div class="po-page">
        @include('purchases::partials.head', [
            'title' => 'استلام مشتريات جديد',
            'crumbs' => [
                ['label' => 'استلام المشتريات', 'url' => route('PurchaseReceiving.index')],
                ['label' => 'أمر شراء '.$order->code, 'url' => route('PurchaseOrders.show', $order->id)],
                ['label' => 'استلام جديد'],
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('purchases::components.guide', [
            'key' => 'purchase-receiving.create',
            'title' => 'استلام مشتريات',
            'lead' => 'سجّل هنا ما وصل فعلياً من أمر الشراء — كلياً أو جزئياً. المخزون يزيد بالكمّية المستلمة فور اعتماد هذا المستند، ولا يتأثر بإنشائه قبل الاعتماد.',
            'open' => false,
            'blocks' => [
                'ماذا يحدث بعد الحفظ؟' => [
                    'يُحفظ الاستلام «بانتظار الاعتماد» ولا يدخل شيء إلى المخزون بعد.',
                    'من شاشة عرض الاستلام: زر «اعتماد الاستلام» يُدخل الكمّيات إلى المستودع المحدَّد.',
                    'بعد الاعتماد يمكن تحويل الاستلام إلى فاتورة شراء — كلياً أو جزئياً.',
                ],
            ],
            'facts' => [
                'الكمّية المدخلة محدودة بالمتبقي من كل صنف — لا يمكن استلام أكثر من المطلوب.',
            ],
        ])

        <form action="{{ route('PurchaseReceiving.store') }}" method="POST">
            @csrf
            <input type="hidden" name="purchase_order_id" value="{{ $order->id }}">

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
                                    <option value="">اختر المستودع</option>
                                    @foreach ($storeHouses as $storeHouse)
                                        <option value="{{ $storeHouse->id }}" @selected(old('store_house_id', $defaultStoreHouseId ?? null) == $storeHouse->id)>{{ $storeHouse->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="po-field">
                                <label for="receiving_date">
                                    تاريخ الاستلام <span class="required">*</span>
                                </label>
                                <input type="date" name="receiving_date" id="receiving_date" class="form-control" value="{{ date('Y-m-d') }}" required>
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
                                <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
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
                    <span class="po-hint">الكمّية الافتراضية أدناه هي كامل المتبقي للاستلام — يمكن تقليلها لاستلام جزء فقط.</span>
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
                                <th class="text-center">المستلم سابقاً</th>
                                <th class="text-center">المتبقي للاستلام</th>
                                <th style="width:180px">الكمّية المستلمة الآن</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $i => $line)
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
                                    <td class="text-center num" data-label="المستلم سابقاً">{{ \App\Support\LanguageSettings::formatNumber($line['received']) }}</td>
                                    <td class="text-center num" data-label="المتبقي للاستلام">{{ \App\Support\LanguageSettings::formatNumber($line['remaining_to_receive']) }}</td>
                                    <td data-label="الكمّية المستلمة الآن">
                                        <input type="number" step="0.0001" min="0"
                                            max="{{ $line['remaining_to_receive'] }}"
                                            name="items[{{ $i }}][received_quantity]"
                                            class="form-control po-input"
                                            value="{{ $line['remaining_to_receive'] }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="po-card__foot">
                    <a href="{{ route('PurchaseOrders.show', $order->id) }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-{{ \App\Support\LanguageSettings::isRtl() ? 'right' : 'left' }} me-1"></i>رجوع
                    </a>

                    <div class="po-toolbar">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>حفظ الاستلام
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
