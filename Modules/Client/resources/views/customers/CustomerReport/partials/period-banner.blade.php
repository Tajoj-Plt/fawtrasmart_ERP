{{--
    شريطُ حالة الفترة فوق التقرير.

    يقول ثلاثة أشياء يحتاجها من يقرأ رقماً: **أيّ مدىً** يقرأ، و**أيّ فترةٍ
    وسنةٍ** يقع فيه، و**أمقفلٌ** هو أم مفتوح. وبدونها يقرأ المستخدم إجمالياً
    لا يعرف حدوده، وهي أوّلُ ما يُسأل عنه عند مراجعة تقرير.

    والمقفلُ يُعلَن ولا يُحجب: العرضُ والطباعةُ والتصديرُ مسموحةٌ كلُّها،
    والممنوعُ هو الكتابة — فالشريطُ إخبارٌ لا منع.

    ولا يظهر تنبيهُ الإقفال ما دام `enforce_lock` مطفأً: الحالةُ حينئذٍ
    سجلٌّ تنظيميٌّ لا قيدٌ فعليّ، فإعلانُها منعاً يُخلف ما تَعِد به شاشةُ
    الإعدادات.

    @param array $periodContext  خرجُ RespectsAccountingPeriods::reportPeriodContext()
--}}

@php
    $ctx = $periodContext ?? [];
    $bnStatus = $ctx['status'] ?? 'open';
    $bnLocked = ($ctx['is_locked'] ?? false) && ($ctx['enforced'] ?? false);
    $bnPeriod = $ctx['period'] ?? null;
    $bnYear = $ctx['fiscal_year'] ?? null;

    $bnTone = $bnLocked ? 'warning' : 'info';
    $bnIcon = $bnLocked ? 'fa-lock' : 'fa-calendar-check-o';
    $bnLabel = ['open' => 'مفتوحة', 'closed' => 'مغلقة', 'locked' => 'مقفلة نهائياً'][$bnStatus] ?? $bnStatus;
@endphp

@if (($ctx['start'] ?? null) && ($ctx['end'] ?? null))
    <div class="alert alert-{{ $bnTone }} rpt-period-banner" role="status">
        <i class="fa {{ $bnIcon }}"></i>

        <strong>النطاق المعروض:</strong>
        <span dir="ltr">{{ $ctx['start'] }} — {{ $ctx['end'] }}</span>

        @if ($bnYear)
            <span class="mx-2">|</span>
            <strong>السنة المالية:</strong> {{ $bnYear->name }}
        @endif

        @if ($bnPeriod)
            <span class="mx-2">|</span>
            <strong>الفترة:</strong> {{ $bnPeriod->name }}
            <span class="badge badge-{{ $bnStatus === 'open' ? 'success' : ($bnStatus === 'locked' ? 'dark' : 'warning') }}">
                {{ $bnLabel }}
            </span>
        @endif

        @if ($bnLocked)
            <div class="mt-1">
                <small>
                    هذه الفترة {{ $bnLabel }} — العرضُ والطباعةُ والتصديرُ متاحةٌ كالمعتاد،
                    ولا يُقبل أيُّ تعديلٍ أو قيدٍ يمسّها.
                </small>
            </div>
        @endif
    </div>
@endif
