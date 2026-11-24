{{--
    الشريط السفلي.

    كان يطبع «COPYRIGHT © 2024 Pixinvent» — اسم بائع القالب وسنةً ثابتة،
    في كل شاشات النظام. صار يقرأ اسم النظام من الإعدادات والسنة من التاريخ
    الجاري، ويتبع ألوان «مظهر النظام» عبر متغيّراته لا بألوانٍ مكتوبة.
--}}

@php
    $appName = config('app.name') ?: 'النظام';
    $appVersion = config('app.version');
@endphp

<footer class="footer footer-static app-footer">
    <div class="app-footer__inner">
        <span class="app-footer__copy">
            &copy; {{ now()->year }} — {{ $appName }}@if ($appVersion) <span class="app-footer__version stk-num">v{{ $appVersion }}</span>@endif
        </span>

        <span class="app-footer__meta">
            جميع الحقوق محفوظة
        </span>
    </div>

    <button class="btn btn-primary btn-icon scroll-top" type="button" aria-label="العودة إلى الأعلى">
        <i class="feather icon-arrow-up"></i>
    </button>
</footer>

<style>
    /* الشريط السفلي يتبع متغيّرات «مظهر النظام»، ويقع خارج نطاق الطباعة */
    .app-footer {
        padding: .75rem 1.5rem;
        background: var(--app-card-bg, #fff);
        border-top: 1px solid var(--app-border, rgba(0, 0, 0, .08));
        color: var(--app-muted, #6e6b7b);
        font-size: .82rem;
    }

    .app-footer__inner {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
    }

    .app-footer__copy { font-weight: 600; color: var(--app-text, #4b4b4b); }

    .app-footer__version {
        display: inline-block;
        padding: .05rem .4rem;
        margin-inline-start: .35rem;
        border-radius: 999px;
        font-size: .72rem;
        color: var(--app-primary, #7367f0);
        background: color-mix(in srgb, var(--app-primary, #7367f0) 12%, transparent);
    }

    @media print {
        .app-footer { display: none !important; }
    }
</style>
