<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة الباركود - {{ $product->name }}</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
        }
        .controls {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .controls label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .controls input, .controls select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .controls-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #7367f0;
            color: #fff;
        }
        .btn-primary:hover {
            background: #5e50ee;
        }
        .btn-success {
            background: #28c76f;
            color: #fff;
        }
        .btn-success:hover {
            background: #1fa85d;
        }
        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .preview-area {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .barcode-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }
        .barcode-item {
            border: 1px dashed #ddd;
            padding: 15px;
            text-align: center;
            background: #fff;
            border-radius: 5px;
        }
        .barcode-item .product-name {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .barcode-item .barcode-number {
            font-size: 11px;
            margin-top: 5px;
            color: #666;
        }
        .barcode-item .price {
            font-size: 14px;
            font-weight: 700;
            color: #28c76f;
            margin-top: 5px;
        }
        
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .header, .controls, .btn-group {
                display: none !important;
            }
            .preview-area {
                box-shadow: none;
                padding: 10px;
            }
            .barcode-item {
                border: 1px dashed #ccc;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa fa-barcode"></i> طباعة الباركود</h1>
            <p>المنتج: <strong>{{ $product->name }}</strong> | رقم المنتج: <strong>#{{ $product->serial_number }}</strong></p>
        </div>

        <div class="controls">
            @if($product->variants->isNotEmpty())
                <div>
                    <label for="variantSelect">المتغيّر</label>
                    <select id="variantSelect">
                        <option value="">المنتج الأساسي ({{ $product->barcode ?? $product->serial_number }})</option>
                        @foreach($product->variants as $variant)
                            <option value="{{ $variant->id }}">
                                {{ collect($variant->variant_options ?? [])->map(fn($v, $k) => "{$k}: {$v}")->implode(' / ') ?: $variant->sku }}
                                — {{ $variant->barcode ?? $variant->sku }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="controls-row">
                <div>
                    <label for="copies">عدد النسخ</label>
                    <input type="number" id="copies" value="1" min="1" max="100">
                </div>
                <div>
                    <label for="showName">عرض اسم المنتج</label>
                    <select id="showName">
                        <option value="1">نعم</option>
                        <option value="0">لا</option>
                    </select>
                </div>
                <div>
                    <label for="showPrice">عرض السعر</label>
                    <select id="showPrice">
                        <option value="1">نعم</option>
                        <option value="0">لا</option>
                    </select>
                </div>
                <div>
                    <label for="showBarcode">عرض رقم الباركود</label>
                    <select id="showBarcode">
                        <option value="1">نعم</option>
                        <option value="0">لا</option>
                    </select>
                </div>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-primary" onclick="generateBarcodes()">
                    <i class="fa fa-refresh"></i> تحديث المعاينة
                </button>
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="fa fa-print"></i> طباعة
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.close()">
                    <i class="fa fa-times"></i> إغلاق
                </button>
            </div>
        </div>

        <div class="preview-area">
            <div class="barcode-preview" id="barcodeContainer">
                <!-- سيتم إضافة الباركودات هنا -->
            </div>
        </div>
    </div>

    @php
        /*
            @json() على تعبيرٍ متعدد الأسطر بدالة سهمية متشعبة (fn () => [...])
            كان يُسقط تحليل Blade للقوس الخاتم لهذا الاستدعاء — فيقطع الناتج
            المُصرَّف في منتصف المصفوفة برسالة "unexpected token ';'"، وتفشل
            الصفحة كاملةً بدل أن تُعرض. حساب المصفوفة هنا كمتغيّرٍ عادي أولاً
            يتفادى تحليل Blade المعقّد، وتوجيه json أدناه لا يأخذ إلا اسم متغيّر.
        */
        $variantsData = $product->variants->mapWithKeys(fn ($v) => [
            $v->id => [
                'name' => $product->name.' - '.collect($v->variant_options ?? [])->implode(' / '),
                'barcode' => $v->barcode ?: $v->sku,
                'price' => number_format($v->finalPrice(), 2),
                'currency' => \App\Support\CurrencySettings::symbol(),
            ],
        ]);
    @endphp

    <script>
        const baseProductData = {
            name: @json($product->name),
            barcode: @json($product->barcode ?? $product->serial_number),
            price: @json(number_format($product->sale_price, 2)),
            currency: '{{ \App\Support\CurrencySettings::symbol() }}'
        };

        const variantsData = @json($variantsData);

        let productData = baseProductData;

        const variantSelect = document.getElementById('variantSelect');
        if (variantSelect) {
            variantSelect.addEventListener('change', function () {
                productData = this.value && variantsData[this.value] ? variantsData[this.value] : baseProductData;
                generateBarcodes();
            });
        }

        function generateBarcodes() {
            const copies = parseInt(document.getElementById('copies').value) || 1;
            const showName = document.getElementById('showName').value === '1';
            const showPrice = document.getElementById('showPrice').value === '1';
            const showBarcode = document.getElementById('showBarcode').value === '1';
            
            const container = document.getElementById('barcodeContainer');
            container.innerHTML = '';

            for (let i = 0; i < copies; i++) {
                const item = document.createElement('div');
                item.className = 'barcode-item';
                
                let html = '';
                
                if (showName) {
                    html += `<div class="product-name">${productData.name}</div>`;
                }
                
                html += `<svg id="barcode-${i}"></svg>`;
                
                if (showBarcode) {
                    html += `<div class="barcode-number">${productData.barcode}</div>`;
                }
                
                if (showPrice) {
                    html += `<div class="price">${productData.price} ${productData.currency}</div>`;
                }
                
                item.innerHTML = html;
                container.appendChild(item);

                // إنشاء الباركود
                try {
                    JsBarcode(`#barcode-${i}`, productData.barcode, {
                        format: "CODE128",
                        width: 2,
                        height: 50,
                        displayValue: false,
                        margin: 5
                    });
                } catch (e) {
                    console.error('Error generating barcode:', e);
                }
            }
        }

        // إنشاء الباركودات عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', generateBarcodes);
    </script>
</body>
</html>
