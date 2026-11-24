{{--
    صفحة الطباعة المشتركة لكل مستندٍ يُصيَّر بقالبٍ مصمَّم في «قوالب المستندات».

    **لماذا؟** كانت المتحكّمات تعيد ناتج `DocumentTemplateRenderer::render()`
    كما هو — وهو **جزءٌ** من صفحة لا صفحةٌ كاملة: يبدأ بوسم `<style>` بلا
    `<!DOCTYPE html>` ولا `<html dir>` ولا `<meta charset>`. فيعرضه المتصفّح
    في **وضع التوافق** (`document.compatMode === 'BackCompat'`)، وفيه:

      • الجداول لا ترث حجم الخط من غلافها — ونظام تصميم القوالب كلّه مبنيّ
        على `em` منسوبةً إلى الغلاف، فينهار تدرّج المقاسات في كل جدول.
      • جهة الصفحة `ltr`، فتنقلب المسافات والانزلاق.
      • لا خلفية ولا توسيط: الورقة ملتصقة بحافة النافذة بهامش 8px.

    وكل من يعرض مستنداً يمرّ من هنا الآن، فلا يبقى موضعٌ يعرض جزءاً عارياً.

    @param string      $html      ناتج المصيّر — يحمل أنماطه و`@page` معه
    @param string      $title     عنوان النافذة
    @param string|null $backUrl   رابط الرجوع، أو `null` فيُعرض «إغلاق»
    @param bool        $autoPrint هل تُفتح نافذة الطباعة تلقائياً
--}}
@php
    use App\Support\LanguageSettings;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ LanguageSettings::direction() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&family=Tajawal:wght@400;500;700;800&display=swap"
          rel="stylesheet">
    <style>
        :root { color-scheme: light; }

        body {
            margin: 0;
            padding: 24px 16px;
            background: #eef0f4;
            font-family: 'Tajawal', 'Segoe UI', sans-serif;
        }

        .print-bar {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .print-bar button,
        .print-bar a {
            border: 0;
            border-radius: 7px;
            padding: 9px 20px;
            font-family: inherit;
            font-size: .92rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            background: #7367f0;
        }

        .print-bar a.secondary { background: #6b7280; }

        /* `fit-content` يوسّط الورقة مهما كان مقاسها — A4 أو شريطاً حرارياً */
        .print-sheet {
            margin: 0 auto;
            width: fit-content;
            max-width: 100%;
            overflow-x: auto;
            background: #fff;
            box-shadow: 0 2px 20px rgba(17, 24, 39, .14);
        }

        @media print {
            body { background: #fff; padding: 0; }
            .print-bar { display: none !important; }
            .print-sheet {
                margin: 0;
                width: auto;
                max-width: none;
                overflow: visible;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <div class="print-bar no-print">
        <button type="button" onclick="window.print()">
            {{ __('settings.doc_templates.print') }}
        </button>

        @if (! empty($backUrl))
            <a class="secondary" href="{{ $backUrl }}">{{ __('settings.doc_templates.back') }}</a>
        @else
            <a class="secondary" href="#" onclick="window.opener ? window.close() : history.back(); return false;">
                {{ __('settings.doc_templates.close') }}
            </a>
        @endif
    </div>

    <div class="print-sheet">
        {!! $html !!}
    </div>

    @if (! empty($autoPrint))
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 350);
            });
        </script>
    @endif
</body>

</html>
