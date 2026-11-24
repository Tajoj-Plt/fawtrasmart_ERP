{{--
    غلافُ ورقة القيد — للطباعة والـPDF وحدهما.

    ═══════════════ ما كان ═══════════════

    هذا الملفُّ كان يحمل المستندَ **والمتن** معاً: `<!DOCTYPE>` و`<html>`
    و`<head>` و`<style>` و`<body>`. وكان يُضمّ داخل صفحةٍ حيّة:

        @include('account::journal.pdf', ['entry' => $entry])   ← في show

    فتخرج صفحةُ «عرض القيد» وفيها **وسمَا `<body>`** ووسما `<html>`. ويُسقط
    المتصفّح الوسومَ المكرَّرة **ويُبقي محتوى `<style>`** — فتسري قواعدُ
    الورقة على الصفحة كلِّها:

        body  { font-family: 'Cairo'; padding: 20px; background: #d3d9e1 }
        table { width: 100%; background: #fff }
        th, td { border: 1px solid #aaa; padding: 10px }

    قواعدُ بلا صنفٍ تُصيب جسمَ الصفحة وكلَّ جدولٍ فيها. ولأنها تُحقن بعد
    أنماط النظام تغلبها عند تساوي الترجيح — فينكسر الشريطُ الجانبي
    والجداولُ في هذه الصفحة وحدها.

    ═══════════════ وما صار ═══════════════

    المتنُ في [partials/document.blade.php] وكلُّ قاعدةٍ فيه مقصورةٌ على
    `.jed`. وبقي الغلافُ هنا لمن يفتح الورقةَ وحدها أو يطبعها.

    **ومن أراد عرضَها داخل صفحة يضمّ المتنَ لا هذا الملفّ.**
--}}
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>قيد يومية #{{ $entry->id ?? '' }}</title>

    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #d3d9e1;
        }

        @media print {
            body { background: #fff; padding: 0; }
        }
    </style>
</head>
<body>
    @include('account::journal.partials.document', ['entry' => $entry])
</body>
</html>
