{{--
    هيكلٌ مشتركٌ لمستندات الطباعة (الملصقات وقائمة الاستلام).

    **لماذا HTML لا PDF** — الملصقات الثلاثة كانت تُبنى بـDompdf، وDompdf
    لا يصل الحروف العربية ولا يعكس اتجاهها: تخرج الصفحة «؟؟؟؟؟ ؟؟؟؟؟».
    ولا ينفّذ `JsBarcode` أيضاً فيغيب الباركود. والطباعة من المتصفّح —
    وهي التي تطبع بها بقيّة شاشات المبيعات (`invoices.print`) — تصل
    الحروف وترسم الباركود، و«حفظ كـPDF» في حوار الطباعة يعطي الملفّ نفسه
    سليماً.

    المتغيّرات:
      • `title`      عنوان المستند (لسان المتصفّح ورأس الصفحة)
      • `pageSize`   حجم الورقة لقاعدة `@page` — افتراضه `A4`
      • `barcode`    صحيحٌ إن كان في المتن باركود، فتُحمَّل المكتبة

    الأقسام:
      • `print-styles`  أنماطٌ خاصّة بالمستند
      • `print-body`    متن المستند

    `?embed=1` يمنع الطباعة التلقائية — تُحمَّل هذه القوالب في إطارٍ داخل
    شاشة العرض، وفتحُ الشاشة وحده كان يفتح حوار الطباعة.
--}}
@php
    use App\Support\LanguageSettings;

    $isRtl = LanguageSettings::isRtl();
    $pageSize = $pageSize ?? 'A4';
    $barcode = $barcode ?? false;
    $side = $isRtl ? 'right' : 'left';
    $otherSide = $isRtl ? 'left' : 'right';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap');

        @page { size: {{ $pageSize }}; margin: 10mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 12mm;
            font-family: 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
            font-size: 11pt;
            line-height: 1.55;
            color: #1b1f27;
            background: #eef0f4;
        }

        .doc {
            max-width: 190mm;
            margin: 0 auto;
            background: #fff;
            padding: 10mm;
            box-shadow: 0 2px 14px rgba(0, 0, 0, .12);
        }

        /* ── الترويسة ── */
        .doc__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8mm;
            border-bottom: 2px solid #1b1f27;
            padding-bottom: 4mm;
            margin-bottom: 5mm;
        }

        .doc__title { margin: 0; font-size: 18pt; font-weight: 700; }

        .doc__org {
            font-size: 9.5pt;
            line-height: 1.5;
            color: #4a5160;
            text-align: {{ $otherSide }};
        }

        .doc__org strong { display: block; font-size: 11pt; color: #1b1f27; }

        /* ── شريط المعرّفات ── */
        .doc__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 2mm 8mm;
            margin-bottom: 5mm;
            font-size: 10pt;
        }

        .doc__meta span { color: #6b7280; }
        .doc__meta b { font-weight: 700; }

        /* ── البطاقات المتقابلة ── */
        .doc__parties { display: flex; gap: 5mm; margin-bottom: 5mm; }

        .party {
            flex: 1;
            border: 1px solid #d6dae2;
            border-radius: 3px;
            padding: 4mm;
        }

        .party__label {
            font-size: 8.5pt;
            font-weight: 700;
            letter-spacing: .5px;
            color: #6b7280;
            margin-bottom: 2mm;
            padding-bottom: 1.5mm;
            border-bottom: 1px solid #eceef2;
        }

        .party__name { font-weight: 700; font-size: 12pt; margin-bottom: 1mm; }
        .party__line { font-size: 10pt; color: #4a5160; }
        .party__line--muted { color: #9aa1ad; }

        /* ── الجداول ── */
        table { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }

        th, td { border: 1px solid #c9ced8; padding: 2.4mm 2.8mm; font-size: 10pt; }

        th { background: #f2f4f7; font-weight: 700; text-align: {{ $side }}; }

        td.num, th.num { text-align: center; font-variant-numeric: tabular-nums; }

        tfoot td { font-weight: 700; background: #f8f9fb; }

        /* ── الباركود ── */
        .doc__barcode { text-align: center; margin: 4mm 0; }
        .doc__barcode svg { max-width: 100%; }

        /* ── التذييل ── */
        .doc__sign { display: flex; gap: 8mm; margin-top: 12mm; }

        .doc__sign > div {
            flex: 1;
            border-top: 1px solid #9aa1ad;
            padding-top: 2mm;
            font-size: 9.5pt;
            color: #6b7280;
            text-align: center;
        }

        /* ── شريط الأدوات — لا يُطبع ── */
        .toolbar {
            max-width: 190mm;
            margin: 0 auto 5mm;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .toolbar button {
            font: inherit;
            font-size: 10pt;
            padding: 7px 16px;
            border: 1px solid #c9ced8;
            border-radius: 5px;
            background: #fff;
            color: #1b1f27;
            cursor: pointer;
        }

        .toolbar button:hover { background: #f2f4f7; }

        @media print {
            body { background: #fff; padding: 0; }
            .doc { box-shadow: none; max-width: none; padding: 0; }
            .toolbar, .no-print { display: none !important; }
            .page-break { page-break-after: always; }
        }

        @yield('print-styles')
    </style>
</head>

<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">{{ __('sales_documents.print.print') }}</button>
        <button type="button" onclick="window.close()">{{ __('sales_documents.print.close') }}</button>
    </div>

    <div class="doc">
        @yield('print-body')
    </div>

    @if ($barcode)
        <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    @endif

    <script>
        (function () {
            'use strict';

            /* الباركود يُرسم قبل الطباعة — `window.print()` لا ينتظر
               السكربتات، فطباعةٌ فوريّة تُخرج فراغاً مكانه. */
            function drawBarcodes() {
                if (typeof JsBarcode === 'undefined') return;

                document.querySelectorAll('svg.barcode').forEach(function (node) {
                    try {
                        JsBarcode(node, node.dataset.value, {
                            format: 'CODE128',
                            width: 1.8,
                            height: 46,
                            fontSize: 13,
                            margin: 0,
                            displayValue: true,
                        });
                    } catch (e) {
                        node.remove();
                    }
                });
            }

            window.addEventListener('load', function () {
                drawBarcodes();

                @unless (request()->boolean('embed'))
                    setTimeout(function () { window.print(); }, 400);
                @endunless
            });

            window.addEventListener('beforeprint', drawBarcodes);
        })();
    </script>
</body>

</html>
