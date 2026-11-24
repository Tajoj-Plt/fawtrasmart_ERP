{{--
    ترويسة PDF تقارير الموارد البشرية — بنفس تنسيق ترويسات المخزون
    (`stock::storehouse.pdf.*`, `stock::products.partials.products_pdf`):
    اسم المنشأة عبر `print-org-head` ثم شريطٌ سفليّ داكن، وعنوان التقرير
    وتاريخ التصدير تحته. أنماطٌ مضمَّنة لا صنف CSS: TCPDF يقرأ كل
    `writeHTML()` مستقلةً عن سابقتها فلا تُضمَن استمرارية `<style>`.

    @props: title
--}}
<div style="text-align:center;margin-bottom:8px;padding-bottom:6px;border-bottom: 2px solid var(--app-text, #2f3349);">
    @include('stock::components.print-org-head')
    <h1 style="margin:0;font-size:15px;color: var(--app-text, #2f3349);">{{ $title }}</h1>
</div>
<div style="margin-bottom:8px;font-size:9px;color: var(--app-muted, #6e6b7b);">
    تاريخ التصدير: {{ now()->format('Y-m-d H:i') }}
</div>
