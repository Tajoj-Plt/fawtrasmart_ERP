{{-- عروضُ الحسم السارية اليوم — تُمرَّر إلى حاسبة الفواتير.

     **لماذا في الشاشة أصلاً؟** الحسم يُطبَّق في الخادم عند الحفظ
     (`App\Services\Sales\OfferDiscountService`). فلو لم تعرفه الشاشةُ لعرضت
     إجمالياً أكبر ممّا يُحفَظ — وهو ما يكسر أوّل ما يكسر خانةَ «مدفوعة
     بالكامل»: تكتب الشاشةُ إجماليَّها في حقل الدفعة، فيردّها الخادم
     «الدفعة أكبر من قيمة الفاتورة».

     والقائمة تصل كاملةً بلا تصفيةٍ بالعميل: العميل يتبدّل في الشاشة، فتصفيتُه
     في الجافاسكربت. ومنتجاتُ العروض المحصورة بتصنيفات مُوسَّعةٌ إلى معرّفات
     منتجاتها هنا، فلا يحتاج السكربت إلى معرفة تصنيف كل منتج.

     @see public/assets/js/invoice-calculator.js — `matchOfferDiscount()` --}}

@php
    $discountOffersPayload = app(\App\Services\Sales\OfferDiscountService::class)->screenPayload();
@endphp

@if (! empty($discountOffersPayload))
    <script>
        /* عروض الحسم السارية — تقرؤها حاسبة الفواتير */
        window.DISCOUNT_OFFERS = @json($discountOffersPayload);
    </script>
@endif
