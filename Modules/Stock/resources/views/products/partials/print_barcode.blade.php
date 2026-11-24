{{--
    صفحة باركود المنتج.

    كانت شذرةً بلا قالب: تُعاد من الكنترولر كصفحةٍ كاملة وهي لا تحمل ترويسة
    ولا شريطاً جانبيّاً ولا أنماط النظام — فتظهر بيضاء عارية. صارت تمتدّ من
    قالب المخزون كبقيّة الصفحات.

    ملاحظة: الملف مسمّىً «partial» لأن مسار الكنترولر يشير إليه هناك؛ لم يُنقل
    كي لا يُكسر المسار.
--}}

@extends('stock::master')

@section('title', 'باركود ' . $product->name)

@section('content')
    @php
        // الكنترولر يمرّر $settings؛ والقيم الافتراضية لمن يستدعي القالب بدونها
        $settings = ($settings ?? null) ?: App\Models\ProductBarcodeSettings::getCurrent() ?: (object) [
            'barcode_type' => 'CODE128',
            'barcode_width' => 2,
            'barcode_height' => 50,
            'font_size' => 12,
            'margin' => 10,
            'display_value' => true,
        ];

        $value = $product->barcode ?: $product->id;
    @endphp

    <div class="content-body">

        @include('stock::components.page-head', [
            'title' => 'باركود المنتج',
            'icon' => 'hash',
            'sub' => $product->name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المنتجات' => route('products.index'),
                'الباركود' => null,
            ],
            'actions' => '<a href="' . route('products.show', $product->id) . '" class="btn btn-outline-secondary">'
                . '<i class="feather icon-arrow-right"></i> عودة للمنتج</a>',
        ])

        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-eye"></i> المعاينة</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="stk-barcode">
                            <svg id="barcode" class="stk-barcode__svg"></svg>

                            <div class="stk-barcode__meta">
                                <strong>{{ $product->name }}</strong>
                                <span class="stk-num">{{ $value }}</span>
                                @if ($product->sale_price)
                                    <span class="stk-barcode__price stk-num">@money($product->sale_price)</span>
                                @endif
                            </div>
                        </div>

                        <p class="stk-help mt-3 mb-0" id="barcode_error" hidden>
                            تعذّر توليد الباركود بنمط <b>{{ $settings->barcode_type }}</b> لهذه القيمة.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-printer"></i> الطباعة</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="printCopies">عدد النسخ</label>
                            <select id="printCopies" class="form-control">
                                @foreach ([1, 2, 3, 4, 5, 10, 20] as $n)
                                    <option value="{{ $n }}">{{ $n }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="button" class="btn btn-primary btn-block" onclick="printBarcode()">
                            <i class="feather icon-printer"></i> طباعة
                        </button>
                    </div>

                    <div class="stk-form-foot">
                        <a href="{{ route('products.download_barcode', $product->id) }}" class="btn btn-outline-primary">
                            <i class="feather icon-download"></i> صورة
                        </a>
                        <a href="{{ route('products.print_barcode_pdf', $product->id) }}" class="btn btn-outline-danger">
                            <i class="feather icon-file-text"></i> PDF
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-sliders"></i> الإعدادات المطبَّقة</h4>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>النمط</dt>
                                <dd>{{ $settings->barcode_type }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>العرض × الارتفاع</dt>
                                <dd class="stk-num">{{ $settings->barcode_width }} × {{ $settings->barcode_height }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>حجم الخط</dt>
                                <dd class="stk-num">{{ $settings->font_size }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الهامش</dt>
                                <dd class="stk-num">{{ $settings->margin }}</dd>
                            </div>
                        </dl>
                        <span class="stk-help">تُضبط من إعدادات المنتجات</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        @php
            $barcodeConfig = [
                'value' => (string) $value,
                'format' => $settings->barcode_type,
                'width' => (int) $settings->barcode_width,
                'height' => (int) $settings->barcode_height,
                'fontSize' => (int) $settings->font_size,
                'margin' => (int) $settings->margin,
                'displayValue' => (bool) $settings->display_value,
            ];

            $priceLabel = $product->sale_price
                ? html_entity_decode(strip_tags(Modules\Stock\Support\StockMoney::render($product->sale_price)))
                : null;
        @endphp

        const barcodeConfig = @json($barcodeConfig);
        const productLabel = @json($product->name);
        const priceLabel = @json($priceLabel);

        function drawBarcode() {
            try {
                JsBarcode('#barcode', barcodeConfig.value, barcodeConfig);
            } catch (e) {
                // قيمةٌ لا تقبلها هذه الصيغة — يُقال ذلك بدل ترك مربّع فارغ
                document.getElementById('barcode_error').hidden = false;
                console.error(e);
            }
        }

        function printBarcode() {
            const copies = parseInt(document.getElementById('printCopies').value, 10) || 1;
            const svg = document.getElementById('barcode').outerHTML;
            const esc = s => String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

            let label = '<div><strong>' + esc(productLabel) + '</strong></div>'
                + '<div>' + esc(barcodeConfig.value) + '</div>';
            if (priceLabel) label += '<div>' + esc(priceLabel) + '</div>';

            let body = '';
            for (let i = 0; i < copies; i++) {
                body += '<div class="item">' + svg + '<div class="label">' + label + '</div></div>';
            }

            const w = window.open('', '', 'width=800,height=600');
            w.document.write(
                '<!doctype html><html dir="rtl"><head><meta charset="utf-8">'
                + '<title>طباعة الباركود</title><style>'
                + 'body{font-family:Arial,sans-serif;text-align:center;margin:20px}'
                + '.item{margin:20px 0;page-break-inside:avoid}.label{margin-top:8px;font-size:13px}'
                + '</style></head><body>' + body + '</body></html>'
            );
            w.document.close();
            w.focus();
            w.print();
        }

        document.addEventListener('DOMContentLoaded', drawBarcode);
    </script>
@endsection
