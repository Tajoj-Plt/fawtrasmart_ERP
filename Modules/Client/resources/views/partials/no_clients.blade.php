{{--
    الحالة الفارغة لقائمة العملاء — على `cl-empty` من نظام تصميم الوحدة.

    كانت `alert alert-info` زرقاءَ عريضة: تنبيهٌ يقول «انتبه» حيث لا شيء
    يستدعي الانتباه — غيابُ نتائجٍ بعد فلترةٍ حالةٌ طبيعية لا تحذير.
    و`cl-empty` تقولها بأيقونةٍ وعنوانٍ وسطرٍ شارح، بألوان `--app-*`.
--}}
<div class="cl-scope">
    <div class="cl-empty">
        <div class="cl-empty__icon">
            <i class="feather icon-users"></i>
        </div>

        <div class="cl-empty__title">لا توجد عملاء متاحون</div>

        <p class="cl-empty__hint">
            @if (auth()->user()->isEmployee())
                لا توجد زيارات عملاء مجدولة لك اليوم ({{ $currentDayName ?? '' }}).
            @else
                لا يوجد عملاء مسجلين حالياً أو لا توجد نتائج مطابقة لمعايير البحث.
            @endif
        </p>

        <div class="d-flex justify-content-center gap-2 flex-wrap">
            @if (hasPermission('clients_add_client'))
                <a href="{{ route('clients.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i>
                    إضافة عميل جديد
                </a>
            @endif

            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                <i class="feather icon-refresh-cw"></i>
                إعادة تحميل
            </button>
        </div>
    </div>
</div>
