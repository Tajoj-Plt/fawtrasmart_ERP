<?php

namespace App\Support;

use App\Models\ColorSitting;
use Illuminate\Support\Facades\Cache;

/**
 * مظهر النظام — الألوان والخط والشكل العام كما ضُبطت في شاشة «مظهر النظام».
 *
 * يُحلّ مرة واحدة لكل طلب ويُخزَّن في الكاش، بديلاً عن `ColorSitting::first()`
 * التي كانت تُنفَّذ داخل `View::composer('*')` عند كل رندر لكل صفحة.
 *
 * المخرج متغيّرات CSS تُحقن في الترويسة ثم قواعد تشير إليها — لا تُلمس أي
 * ورقة أنماط أصلية، ويبقى أي نمط أخصّ في الصفحة قادراً على تجاوزها.
 */
class ThemeSettings
{
    protected const CACHE_KEY = 'system_theme_settings';

    protected const CACHE_TTL = 86400; // يوم — يُبطَل فوراً عند الحفظ

    protected static ?ColorSitting $resolved = null;

    /**
     * سجل المظهر الفعّال — سجل بالقيم الافتراضية إن كان الجدول فارغاً.
     */
    public static function current(): ColorSitting
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        try {
            $theme = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => ColorSitting::first());
        } catch (\Throwable $e) {
            // قاعدة بيانات غير مهيّأة (أثناء الترحيلات مثلاً)
            $theme = null;
        }

        return self::$resolved = ($theme ?: new ColorSitting(ColorSitting::DEFAULTS));
    }

    /** يُنسى الحل المخزَّن — يُستدعى بعد كل حفظ */
    public static function forget(): void
    {
        self::$resolved = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** لون الخلفية القديم — مصدر `$backgroundColorr` المشترك في كل القوالب */
    public static function backgroundColor(): string
    {
        return self::current()->valueOr('color') ?: '#ffffff';
    }

    public static function layoutMode(): string
    {
        return self::current()->valueOr('layout_mode') ?: 'light';
    }

    public static function isDark(): bool
    {
        return self::layoutMode() === 'dark';
    }

    /** رابط تحميل الخط من Google Fonts — فارغ لخط النظام */
    public static function fontUrl(): ?string
    {
        $font = self::current()->valueOr('font_family');

        if (! $font || $font === 'system') {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?family='
            .str_replace(' ', '+', $font)
            .':wght@400;500;600;700&display=swap';
    }

    /**
     * لوحة الألوان الكاملة بعد اشتقاق الدرجات — مصدر واحد للـ CSS وللمعاينة.
     *
     * @return array<string, string>
     */
    public static function palette(): array
    {
        $theme = self::current();
        $dark = self::isDark();
        $semiDark = self::layoutMode() === 'semi-dark';

        $primary = $theme->valueOr('primary_color');
        $sidebarBg = $semiDark ? '#10163A' : $theme->valueOr('sidebar_color');

        // في الوضع الليلي تُشتق ألوان السطوح من الداكن بدل ما ضبطه المستخدم للوضع الفاتح
        $bodyBg = $dark ? '#161d31' : $theme->valueOr('color');
        $surface = $dark ? '#283046' : '#ffffff';
        $text = $dark ? '#d0d2d6' : '#4b4b4b';
        $muted = $dark ? '#8a8f9c' : '#7b8194';
        $border = $dark ? '#3b4253' : '#e6e9f0';

        $sidebarText = $semiDark
            ? '#d0d2d6'
            : ($theme->valueOr('sidebar_text_color') ?: ColorTools::readableOn($sidebarBg));

        return array_filter([
            '--app-primary' => $primary,
            '--app-primary-hover' => ColorTools::darken($primary, 0.10),
            '--app-primary-soft' => ColorTools::rgba($primary, 0.12),
            '--app-primary-glow' => ColorTools::rgba($primary, 0.28),
            '--app-on-primary' => ColorTools::readableOn($primary),

            '--app-success' => $theme->valueOr('success_color'),
            '--app-danger' => $theme->valueOr('danger_color'),
            '--app-info' => $theme->valueOr('info_color'),
            '--app-warning' => $theme->valueOr('warning_color'),
            '--app-success-soft' => ColorTools::rgba($theme->valueOr('success_color'), 0.12),
            '--app-danger-soft' => ColorTools::rgba($theme->valueOr('danger_color'), 0.12),
            // الروابط تتبع اللون الأساسي ما لم يُفرد لها لون
            '--app-link' => $theme->valueOr('link_color') ?: $primary,

            '--app-body-bg' => $bodyBg,
            '--app-surface' => $surface,
            '--app-text' => $text,
            '--app-muted' => $muted,
            '--app-border' => $border,

            '--app-sidebar-bg' => $sidebarBg,
            '--app-sidebar-text' => $sidebarText,
            '--app-sidebar-hover' => ColorTools::rgba($primary, 0.14),
            '--app-topbar-bg' => $dark ? $surface : $theme->valueOr('topbar_color'),

            '--app-font-family' => $theme->font_stack,
            '--app-font-size' => $theme->valueOr('font_size'),
            '--app-heading-weight' => $theme->valueOr('heading_font_weight'),
            '--app-radius' => $theme->valueOr('border_radius'),
            '--app-sidebar-width' => $theme->valueOr('sidebar_width'),
            '--app-card-shadow' => ColorSitting::SHADOWS[$theme->valueOr('card_shadow')] ?? null,

            // الجداول والبطاقات وعناصر التحكّم — الفارغ يعني «اترك القالب»
            // بديلٌ دائم لا `null`: القوالب تكتبه، و`array_filter` أدناه
            // يُسقط الفارغ فيؤول المتغيّر إلى لا شيء
            '--app-table-header-text' => $theme->table_header_text
                ?: ($dark ? '#d0d2d6' : '#4b4b4b'),
            '--app-table-stripe' => $dark ? 'rgba(255,255,255,.03)' : ColorTools::rgba($primary, 0.045),
            '--app-card-padding' => $theme->card_padding,
            '--app-button-radius' => $theme->button_radius,
            '--app-input-height' => $theme->input_height,
            '--app-topbar-height' => $theme->topbar_height,

            /*
                السطوح التي كانت تسقط في الوضع الليلي.

                هذه الثلاثة تُقرأ من الإعدادات، فإن لم يضبطها المستخدم كانت
                `null` فيُسقطها `array_filter` أدناه. والقوالب تكتب
                `var(--app-table-header-bg)` بلا بديل — فيؤول المتغيّر إلى
                لا شيء، ويرثُ العنصرُ أبيضَ المتصفّح. فترويسةُ الجدول وحقلُ
                الإدخال يخرجان بيضاوين على صفحةٍ داكنة.

                فصار لكلٍّ منها بديلٌ يعرف الوضعَ الداكن: يُحترم ما ضبطه
                المستخدم، ويُشتقّ من السطح إن لم يضبط.
            */
            '--app-table-header-bg' => $theme->table_header_bg
                ?: ($dark ? '#323a53' : '#f7f8fb'),
            '--app-card-header-bg' => $theme->card_header_bg
                ?: ($dark ? $surface : '#ffffff'),
            '--app-input-bg' => $theme->input_bg
                ?: ($dark ? '#283046' : '#ffffff'),

            /*
                أسماءٌ مرادفة.

                قوالبُ في المشروع تكتب `--app-card-bg` و`--app-body` و`--app-card`
                و`--app-font` و`--app-warning-soft` — وهي أسماءٌ لم تُعرَّف قط.
                الأثر نفسه: المتغيّر لا يؤول إلى شيء، فيظهر السطحُ أبيض في
                الوضع الليلي — وهو ما كان يحدث للشريط السفلي.

                فتُعرَّف مرادفاتٍ للأسماء القائمة بدل تتبّع كل موضعٍ كُتبت
                فيه؛ والموضعُ الجديد يعمل من تلقائه.
            */
            '--app-card-bg' => $surface,
            '--app-card' => $surface,
            '--app-body' => $text,
            '--app-font' => $theme->font_stack,
            '--app-warning-soft' => ColorTools::rgba($theme->valueOr('warning_color'), 0.12),
            '--app-info-soft' => ColorTools::rgba($theme->valueOr('info_color'), 0.12),
            '--app-accent-color' => $primary,
        ], fn ($value) => filled($value));
    }

    /**
     * متغيّرات CSS وحدها — بلا قواعد تطبيق.
     */
    public static function cssVariables(): string
    {
        $rules = '';

        foreach (self::palette() as $name => $value) {
            $rules .= $name.':'.$value.';';
        }

        return $rules ? ':root{'.$rules.'}' : '';
    }

    /**
     * أنماط المظهر كاملة — المتغيّرات وقواعد تطبيقها.
     */
    public static function css(): string
    {
        $variables = self::cssVariables();

        if ($variables === '') {
            return '';
        }

        $theme = self::current();
        $palette = self::palette();

        return $variables
            .self::typographyRules()
            .self::brandRules()
            .self::shapeRules()
            .self::chromeRules()
            .self::widgetRules()
            .(isset($palette['--app-sidebar-width']) ? self::sidebarWidthRules() : '')
            .(isset($palette['--app-card-shadow']) ? self::shadowRules() : '')
            .(self::isDark() ? self::darkRules() : '')
            .($theme->valueOr('sidebar_position') === 'static' ? self::staticSidebarRules() : '')
            .($theme->compact_mode ? self::compactRules() : '')
            // الأخيرة عمداً: خيارات صريحة يجب أن تتجاوز الوضع المضغوط والليلي
            .self::tableRules()
            .self::cardRules()
            .self::controlRules()
            .self::layoutRules()
            .self::customCss();
    }

    // ==================== مجموعات القواعد ====================

    protected static function typographyRules(): string
    {
        return <<<'CSS'
        body,.navigation,.header-navbar,.breadcrumb,.card,.btn,.form-control,.custom-select,
        h1,h2,h3,h4,h5,h6,.modal,table,input,select,textarea,label,.dropdown-menu,.swal2-popup{
            font-family:var(--app-font-family,inherit);
        }
        body{font-size:var(--app-font-size,inherit);}
        h1,h2,h3,h4,h5,h6,.card-title{font-weight:var(--app-heading-weight,inherit);}
        CSS;
    }

    protected static function brandRules(): string
    {
        return <<<'CSS'
        .btn-primary,.bg-primary,.badge-primary,.page-item.active .page-link,.nav-pills .nav-link.active,
        .custom-control-input:checked~.custom-control-label::before,.custom-checkbox .custom-control-input:checked~.custom-control-label::before{
            background-color:var(--app-primary)!important;border-color:var(--app-primary)!important;
            color:var(--app-on-primary)!important;
        }
        .btn-primary:hover,.btn-primary:focus,.btn-primary:not(:disabled):not(.disabled):active{
            background-color:var(--app-primary-hover)!important;border-color:var(--app-primary-hover)!important;
        }
        .btn-primary:focus{box-shadow:0 0 0 3px var(--app-primary-glow)!important;}
        .text-primary,a.text-primary,a:hover{color:var(--app-primary)!important;}
        .btn-outline-primary{color:var(--app-primary)!important;border-color:var(--app-primary)!important;background:transparent!important;}
        .btn-outline-primary:hover{background-color:var(--app-primary)!important;color:var(--app-on-primary)!important;}
        .badge-light-primary{background-color:var(--app-primary-soft)!important;color:var(--app-primary)!important;}
        .form-control:focus,.custom-select:focus,.select2-container--focus .select2-selection{
            border-color:var(--app-primary)!important;box-shadow:0 0 0 3px var(--app-primary-glow)!important;
        }
        .btn-success,.bg-success,.badge-success{background-color:var(--app-success)!important;border-color:var(--app-success)!important;}
        .btn-danger,.bg-danger,.badge-danger{background-color:var(--app-danger)!important;border-color:var(--app-danger)!important;}
        .btn-info,.bg-info,.badge-info{background-color:var(--app-info)!important;border-color:var(--app-info)!important;}
        .btn-warning,.bg-warning,.badge-warning{background-color:var(--app-warning)!important;border-color:var(--app-warning)!important;}
        .text-success{color:var(--app-success)!important;}
        .text-danger{color:var(--app-danger)!important;}
        .text-info{color:var(--app-info)!important;}
        .text-warning{color:var(--app-warning)!important;}
        .badge-light-success{background-color:var(--app-success-soft)!important;color:var(--app-success)!important;}
        .badge-light-danger{background-color:var(--app-danger-soft)!important;color:var(--app-danger)!important;}
        .alert-success{background-color:var(--app-success-soft)!important;color:var(--app-success)!important;border-color:var(--app-success)!important;}
        .alert-danger{background-color:var(--app-danger-soft)!important;color:var(--app-danger)!important;border-color:var(--app-danger)!important;}
        /* الروابط داخل المحتوى فقط — أزرار Bootstrap تحمل .btn ولها ألوانها */
        a:not(.btn):not(.nav-link):not(.dropdown-item):not(.page-link){color:var(--app-link);}
        a:not(.btn):not(.nav-link):not(.dropdown-item):not(.page-link):hover{color:var(--app-primary-hover);}
        CSS;
    }

    protected static function shapeRules(): string
    {
        return <<<'CSS'
        .card,.btn,.form-control,.custom-select,.modal-content,.alert,.dropdown-menu,.badge,.nav-pills .nav-link{
            border-radius:var(--app-radius,inherit);
        }
        CSS;
    }

    /**
     * الودجات: القوائم المنسدلة، وselect2، وDataTables، والجداول.
     *
     * **ما كان**
     *
     * قواعدُ الأسطح كلّها محصورةٌ في `.header-navbar` — أي أن الشريط العلوي
     * وحده يتبع الثيم، وكلُّ ما في الصفحة يبقى على ألوان مكتبته:
     *
     *   • `.dropdown-menu` في الصفحة بيضاءُ من Bootstrap.
     *   • select2 يرسم صندوقه ولوحته بألوانٍ مكتوبةٍ في ورقته
     *     (`#fff`, `#aaa`, `#ddd`) لا تعرف الثيم — وهي أكثرُ ما يُرى في
     *     شاشات المالية: إحدى وثلاثون نسخةً في شاشة إنشاء أصلٍ وحدها.
     *   • DataTables يصبغ الترويسة والترقيم وحقلَ البحث بأبيضه.
     *
     * فتُفتح في الوضع الليلي لوحةٌ بيضاء فوق صفحةٍ داكنة، ويُقرأ الجدول
     * بترويسةٍ فاتحة على متنٍ داكن.
     *
     * **ما صار**
     *
     * كلُّ سطحٍ من هذه يُشتقّ من `--app-surface` و`--app-border`
     * و`--app-text`، وحالاتُه من `--app-primary`. فيتبع الثيم في الوضعين
     * بلا لونٍ مكتوب.
     *
     * وتأتي بعد `chromeRules` وقبل الخيارات الصريحة عمداً: فما يضبطه
     * المستخدم في «مظهر النظام» يبقى فوقها.
     */
    protected static function widgetRules(): string
    {
        return <<<'CSS'
        /* ═══════════ القوائم المنسدلة في الصفحة ═══════════ */
        .dropdown-menu{
            background-color:var(--app-surface)!important;
            border:1px solid var(--app-border)!important;
            box-shadow:0 6px 20px color-mix(in srgb,var(--app-text) 12%,transparent)!important;
            color:var(--app-text)!important;
        }
        .dropdown-menu .dropdown-item{color:var(--app-text)!important;}
        .dropdown-menu .dropdown-item:hover,
        .dropdown-menu .dropdown-item:focus{
            background-color:var(--app-primary-soft)!important;color:var(--app-primary)!important;
        }
        .dropdown-menu .dropdown-item.active,
        .dropdown-menu .dropdown-item:active{
            background-color:var(--app-primary)!important;color:var(--app-on-primary)!important;
        }
        .dropdown-menu .dropdown-item.disabled,
        .dropdown-menu .dropdown-item:disabled{color:var(--app-muted)!important;background:transparent!important;}
        .dropdown-menu .dropdown-divider{border-color:var(--app-border)!important;}
        .dropdown-menu .dropdown-header{color:var(--app-muted)!important;}

        /* ═══════════ select2 ═══════════ */
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple,
        .select2-selection{
            background-color:var(--app-input-bg,var(--app-surface))!important;
            border-color:var(--app-border)!important;
            color:var(--app-text)!important;
            border-radius:var(--app-radius,4px)!important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered,
        .select2-container--default .select2-selection--multiple .select2-selection__rendered{
            color:var(--app-text)!important;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder,
        .select2-container--default .select2-selection__placeholder{color:var(--app-muted)!important;}
        .select2-container--default .select2-selection--single .select2-selection__arrow b{
            border-color:var(--app-muted) transparent transparent transparent!important;
        }
        .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b{
            border-color:transparent transparent var(--app-muted) transparent!important;
        }

        /* اللوحة المنسدلة — أكثر ما كان يظهر أبيض */
        .select2-dropdown,.select2-container--default .select2-dropdown{
            background-color:var(--app-surface)!important;
            border-color:var(--app-border)!important;
            color:var(--app-text)!important;
        }
        .select2-container--default .select2-results__option{
            background-color:transparent!important;color:var(--app-text)!important;
        }
        .select2-container--default .select2-results__option--highlighted,
        .select2-container--default .select2-results__option--highlighted[aria-selected]{
            background-color:var(--app-primary)!important;color:var(--app-on-primary)!important;
        }
        .select2-container--default .select2-results__option[aria-selected=true],
        .select2-container--default .select2-results__option[data-selected=true]{
            background-color:var(--app-primary-soft)!important;color:var(--app-primary)!important;
        }
        .select2-container--default .select2-results__group{color:var(--app-muted)!important;}
        .select2-search--dropdown{background:var(--app-surface)!important;}
        .select2-container--default .select2-search--dropdown .select2-search__field{
            background-color:var(--app-input-bg,var(--app-surface))!important;
            border-color:var(--app-border)!important;color:var(--app-text)!important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice{
            background-color:var(--app-primary-soft)!important;
            border-color:color-mix(in srgb,var(--app-primary) 40%,transparent)!important;
            color:var(--app-primary)!important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove{
            color:var(--app-primary)!important;
        }
        .select2-results__message{color:var(--app-muted)!important;}

        /* ═══════════ قوائم المتصفّح الأصلية ═══════════ */
        select,select.form-control,select.custom-select{
            background-color:var(--app-input-bg,var(--app-surface))!important;
            color:var(--app-text)!important;
            border-color:var(--app-border)!important;
        }
        select option,select optgroup{
            background-color:var(--app-surface)!important;color:var(--app-text)!important;
        }

        /* ═══════════ الجداول ═══════════ */
        .table,table.dataTable{
            color:var(--app-text)!important;
            border-color:var(--app-border)!important;
            background-color:transparent!important;
        }
        .table thead th,table.dataTable thead th,table.dataTable thead td{
            background-color:var(--app-table-header-bg)!important;
            color:var(--app-table-header-text,var(--app-text))!important;
            border-color:var(--app-border)!important;
            font-weight:600;
        }
        .table tfoot th,.table tfoot td,table.dataTable tfoot th,table.dataTable tfoot td{
            background-color:var(--app-table-header-bg)!important;
            color:var(--app-text)!important;border-color:var(--app-border)!important;
        }
        .table td,.table th,table.dataTable td,table.dataTable th,
        .table-bordered,.table-bordered td,.table-bordered th{
            border-color:var(--app-border)!important;
        }
        .table tbody tr,table.dataTable tbody tr{background-color:transparent!important;}
        .table-striped tbody tr:nth-of-type(odd),
        table.dataTable.stripe tbody tr.odd,table.dataTable.display tbody tr.odd{
            background-color:var(--app-table-stripe)!important;
        }
        .table-hover tbody tr:hover,table.dataTable.hover tbody tr:hover,
        table.dataTable.display tbody tr:hover{
            background-color:var(--app-primary-soft)!important;color:var(--app-text)!important;
        }
        table.dataTable tbody tr.selected,.table-active,.table tbody tr.selected{
            background-color:var(--app-primary-soft)!important;color:var(--app-text)!important;
        }
        .table .thead-light th,.table .thead-dark th{
            background-color:var(--app-table-header-bg)!important;
            color:var(--app-table-header-text,var(--app-text))!important;
            border-color:var(--app-border)!important;
        }

        /* ═══════════ غلاف DataTables ═══════════ */
        .dataTables_wrapper{color:var(--app-text)!important;}
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate{color:var(--app-text)!important;}
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input{
            background-color:var(--app-input-bg,var(--app-surface))!important;
            border:1px solid var(--app-border)!important;
            color:var(--app-text)!important;
            border-radius:var(--app-radius,4px)!important;
        }
        .dataTables_wrapper .dataTables_processing{
            background:var(--app-surface)!important;
            border-color:var(--app-border)!important;
            box-shadow:0 4px 14px color-mix(in srgb,var(--app-text) 14%,transparent)!important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button{
            background:transparent!important;border-color:var(--app-border)!important;
            color:var(--app-text)!important;border-radius:var(--app-radius,4px)!important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover{
            background:var(--app-primary-soft)!important;color:var(--app-primary)!important;
            border-color:var(--app-border)!important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover{
            background:var(--app-primary)!important;color:var(--app-on-primary)!important;
            border-color:var(--app-primary)!important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover{
            background:transparent!important;color:var(--app-muted)!important;
        }
        table.dataTable thead .sorting,table.dataTable thead .sorting_asc,
        table.dataTable thead .sorting_desc{color:var(--app-table-header-text,var(--app-text))!important;}

        /* ═══════════ الترقيم القياسي ═══════════ */
        .pagination .page-link{
            background-color:transparent!important;border-color:var(--app-border)!important;
            color:var(--app-text)!important;
        }
        .pagination .page-link:hover{
            background-color:var(--app-primary-soft)!important;color:var(--app-primary)!important;
        }
        .pagination .page-item.active .page-link{
            background-color:var(--app-primary)!important;border-color:var(--app-primary)!important;
            color:var(--app-on-primary)!important;
        }
        .pagination .page-item.disabled .page-link{
            background-color:transparent!important;color:var(--app-muted)!important;
        }

        /* ═══════════ الأدوات المنبثقة ═══════════ */
        .tooltip .tooltip-inner{
            background:color-mix(in srgb,var(--app-text) 88%,transparent)!important;
            color:var(--app-surface)!important;
        }
        .popover,.popover-body,.popover-header{
            background:var(--app-surface)!important;color:var(--app-text)!important;
            border-color:var(--app-border)!important;
        }
        .modal-content{
            background:var(--app-surface)!important;color:var(--app-text)!important;
            border-color:var(--app-border)!important;
        }
        .modal-header,.modal-footer{border-color:var(--app-border)!important;}
        .swal2-popup{background:var(--app-surface)!important;color:var(--app-text)!important;}
        .swal2-title,.swal2-html-container,.swal2-content{color:var(--app-text)!important;}
        .list-group-item{
            background:var(--app-surface)!important;color:var(--app-text)!important;
            border-color:var(--app-border)!important;
        }
        .nav-tabs{border-color:var(--app-border)!important;}
        .nav-tabs .nav-link{color:var(--app-muted)!important;}
        .nav-tabs .nav-link.active{
            color:var(--app-primary)!important;background:transparent!important;
            border-color:var(--app-border) var(--app-border) transparent!important;
        }
        CSS;
    }

    /**
     * الشريطان العلوي والجانبي.
     *
     * **ما كان**
     *
     * القواعد تصبغ خلفية الشريطين ولونَ نصّهما ثم تقف، فيبقى ما فوقهما
     * على تنسيق قالب Vuexy: أيقوناتٌ رماديةٌ ثابتة تختفي على خلفيةٍ داكنة،
     * وقوائمُ منسدلةٌ بيضاءُ تُفتح من شريطٍ أسود في الوضع الليلي، وشاراتٌ
     * بلون القالب لا بلون النظام، وبنودُ قائمةٍ فرعيةٍ بلا لونٍ أصلاً.
     *
     * ولا نظامَ مقاسٍ يجمعها: كل بندٍ بارتفاعه وحشوته من القالب، فلا
     * إيقاع بصري ولا تناسق مع شاشات المخزون والمالية المبنيّة على `stk-*`.
     *
     * **ما صار**
     *
     * الشريطان يتبعان نظام `stk-*` نفسه — الشعاع والحشوة والظلّ والحدّ
     * والانتقال — فيقرأ الشريطُ كامتدادٍ للصفحة تحته لا كقطعةٍ غريبة عنها.
     * ولكل حالةٍ لونها المشتقّ من متغيّرات الثيم: تمرير، ونشِط، ومفتوح.
     * ولا لونَ مكتوباً بيده إلّا في مزج الظلال.
     *
     * والبنية هنا نسقٌ واحد: مقاساتٌ في `:root`، ثم الجانبي، ثم العلوي.
     */
    protected static function chromeRules(): string
    {
        return <<<'CSS'
        /* ═══════════ مقاسات الشريطين ═══════════ */
        :root{
            --chr-1:.25rem; --chr-2:.5rem; --chr-3:.75rem; --chr-4:1rem;
            --chr-r:calc(var(--app-radius, 6px));
            --chr-r-lg:calc(var(--app-radius, 6px) + 4px);
            --chr-ease:.16s cubic-bezier(.4,0,.2,1);
            --chr-line:color-mix(in srgb, var(--app-sidebar-text) 14%, transparent);
            --chr-dim:color-mix(in srgb, var(--app-sidebar-text) 70%, transparent);
            --chr-sub:color-mix(in srgb, var(--app-sidebar-bg) 90%, #000);
            --chr-shadow:0 1px 2px rgba(16,24,40,.04), 0 4px 12px rgba(16,24,40,.06);
            --chr-shadow-lg:0 2px 6px rgba(16,24,40,.06), 0 16px 36px rgba(16,24,40,.12);
        }

        /* ═══════════ الشريط الجانبي ═══════════ */
        .main-menu,.main-menu.menu-light,.main-menu .navbar-header,.main-menu-content{
            background-color:var(--app-sidebar-bg)!important;
        }
        .main-menu{
            border-inline-end:1px solid var(--chr-line)!important;
            box-shadow:none!important;
        }
        .main-menu .navbar-header{
            border-bottom:1px solid var(--chr-line);
            padding-block:var(--chr-3);
        }
        .main-menu .navigation,.main-menu .navigation.navigation-main{
            background:transparent!important;
            padding-inline:var(--chr-2);
        }

        /* البند: مقاسٌ واحد وإيقاعٌ واحد بدل مقاس القالب */
        .main-menu .navigation>li>a{
            display:flex;align-items:center;gap:var(--chr-3);
            margin-block:2px;padding:.62rem var(--chr-3)!important;
            border-radius:var(--chr-r);
            color:var(--app-sidebar-text)!important;
            font-weight:500;line-height:1.35;
            transition:background-color var(--chr-ease),color var(--chr-ease);
        }
        .main-menu .navigation>li>a>i{
            flex:0 0 auto;font-size:1.05rem;
            color:var(--chr-dim)!important;
            transition:color var(--chr-ease);
        }
        .main-menu .navigation>li>a .menu-title{flex:1 1 auto;min-width:0;}
        .main-menu .brand-text{color:var(--app-sidebar-text)!important;font-weight:700;}

        .main-menu .navigation>li>a:hover{
            background:var(--app-sidebar-hover)!important;
        }
        .main-menu .navigation>li>a:hover>i{color:var(--app-sidebar-text)!important;}

        /* النشِط: كتلةٌ ممتلئة بلا توهّجٍ ضبابي */
        .main-menu .navigation>li.active>a{
            background:var(--app-primary)!important;
            box-shadow:0 2px 8px var(--app-primary-glow)!important;
            font-weight:600;
        }
        .main-menu .navigation>li.active>a,
        .main-menu .navigation>li.active>a>i,
        .main-menu .navigation>li.active>a .menu-title{
            color:var(--app-on-primary)!important;
        }

        /* المفتوح غير النشِط: يُميَّز بخلفيةٍ خفيفة لا بلونٍ كامل */
        .main-menu .navigation>li.open:not(.active)>a{
            background:var(--app-sidebar-hover)!important;
        }

        /* البنود الفرعية: كانت بلا لونٍ فتُقرأ باهتةً تحت بندٍ مضيء */
        .main-menu .navigation li ul.menu-content{
            background:var(--chr-sub)!important;
            border-radius:var(--chr-r);
            margin:2px 0 var(--chr-1);
            padding:var(--chr-1) 0;
        }
        .main-menu .navigation li ul.menu-content li a{
            display:flex;align-items:center;gap:var(--chr-2);
            padding:.42rem var(--chr-3) .42rem 2.1rem!important;
            margin-inline:var(--chr-1);
            border-radius:var(--chr-r);
            color:var(--chr-dim)!important;
            font-size:.86rem;
            transition:background-color var(--chr-ease),color var(--chr-ease);
        }
        .main-menu .navigation li ul.menu-content li a>i{font-size:.5rem;opacity:.6;}
        .main-menu .navigation li ul.menu-content li a:hover{
            background:var(--app-sidebar-hover)!important;
            color:var(--app-sidebar-text)!important;
        }
        .main-menu .navigation li ul.menu-content li.active>a{
            background:var(--app-primary-soft)!important;
            color:var(--app-primary)!important;font-weight:600;
        }
        .main-menu .navigation li ul.menu-content li.active>a>i{opacity:1;}

        /* ترويسة القسم: فاصلٌ لا بند */
        .main-menu .navigation-header{
            color:color-mix(in srgb, var(--app-sidebar-text) 48%, transparent)!important;
            font-size:.68rem;font-weight:700;
            letter-spacing:.08em;text-transform:uppercase;
            padding:var(--chr-4) var(--chr-3) var(--chr-2)!important;
            margin:0;
        }
        .main-menu .shadow-bottom{
            background:linear-gradient(var(--app-sidebar-bg) 42%,
                color-mix(in srgb, var(--app-sidebar-bg) 55%, transparent) 76%,
                transparent)!important;
            margin-inline:0;
        }
        .main-menu ::-webkit-scrollbar-thumb{
            background:color-mix(in srgb, var(--app-sidebar-text) 22%, transparent);
            border-radius:999px;
        }
        .main-menu ::-webkit-scrollbar{width:6px;}

        /* ═══════════ الشريط العلوي ═══════════ */
        /*
            سطحٌ واحد لا ثلاثة.

            الشريط ثلاث حاوياتٍ متداخلة: `.header-navbar` ثم `.navbar-wrapper`
            ثم `.navbar-container`. وكانت الخلفية تُصبغ على الخارجيّة
            والداخليّة معاً، والداخليّة مربّعةٌ داخل خارجيّةٍ مستديرة الأركان
            وذاتِ حدّ — فيُقرأ الشريط طبقةً فوق طبقة، ويظهر أثرُ الحاوية
            الداخلية عند الأركان.

            صار السطحُ على `.header-navbar` وحدها، والحاويتان الداخليّتان
            شفّافتان تحملان الحشوة لا اللون.
        */
        .header-navbar,.header-navbar.navbar-light{
            background-color:var(--app-topbar-bg)!important;
            box-shadow:none!important;
            border:0;
        }
        .header-navbar .navbar-wrapper,
        .header-navbar .navbar-container,
        .header-navbar .navbar-container.content{
            background:transparent!important;
            box-shadow:none!important;
            border:0!important;
        }
        .header-navbar .navbar-wrapper{border-radius:inherit;}
        .header-navbar .navbar-container{
            padding:var(--chr-2) var(--chr-4)!important;
            border-radius:inherit;
        }

        /*
            الشريط الأبيض تحت الشريط العلوي.

            `.header-navbar-shadow` عنصرٌ ثابت من قالب Vuexy: ارتفاعه 102px،
            و`top:0`، و`z-index:11`، وخلفيته تدرّجٌ أبيض
            `rgba(248,248,248,.95)`. مهمّته أن يخفي المحتوى المارّ تحت شريطٍ
            طافٍ — ولونه مكتوبٌ للوضع الفاتح وحده.

            ونسخته الداكنة تعيش في `dark-layout.css` مرتبطةً بكلاسٍ على
            `<body>` لا يضبطه أحد في هذا المشروع. فيبقى الأبيض ظاهراً في
            الوضع الليلي شريطاً عرضه الشاشة فوق الصفحة الداكنة — وهو مصدر
            البياض الذي لا يأتي من الشريط العلوي نفسه.

            صار يُشتقّ من خلفية الصفحة، فيؤدّي وظيفته في الوضعين.
        */
        .header-navbar-shadow{
            background:linear-gradient(180deg,
                color-mix(in srgb, var(--app-body-bg) 95%, transparent) 44%,
                color-mix(in srgb, var(--app-body-bg) 46%, transparent) 73%,
                transparent)!important;
        }
        /* المناطق الثلاث: بداية، فراغ مرِن، نهاية */
        .header-navbar .navbar-collapse.app-topbar{
            display:flex!important;align-items:center;
            width:100%;min-height:var(--app-topbar-height, 3.6rem);
            gap:var(--chr-2);
        }
        .header-navbar .app-topbar__start,
        .header-navbar .app-topbar__end{
            display:flex;align-items:center;gap:.35rem;
            flex:0 0 auto;margin:0;padding:0;list-style:none;float:none!important;
        }
        .header-navbar .app-topbar__spacer{flex:1 1 auto;min-width:var(--chr-4);}
        .header-navbar .app-topbar__divider{
            width:1px;height:1.6rem;margin-inline:var(--chr-2);
            background:color-mix(in srgb, var(--app-border) 90%, transparent);
            list-style:none;flex:0 0 auto;
        }
        /* مجموعةُ الإجراءات تُقرأ ككتلةٍ واحدة لا كأيقوناتٍ متناثرة */
        .header-navbar .app-topbar__start>.nav-item+.nav-item{margin:0;}
        .header-navbar .app-topbar__end .dropdown-user{margin-inline-start:var(--chr-1);}

        @media (max-width:575.98px){
            .header-navbar .app-topbar__end .user-nav{display:none!important;}
            .header-navbar .navbar-collapse.app-topbar{gap:var(--chr-1);}
        }

        .header-navbar.floating-nav{
            border:1px solid color-mix(in srgb, var(--app-border) 80%, transparent)!important;
            border-radius:var(--chr-r-lg)!important;
            box-shadow:var(--chr-shadow)!important;
        }

        /* الأيقونات: كانت رماديةً ثابتة تختفي على خلفيةٍ داكنة */
        .header-navbar .navbar-nav .nav-link{
            display:inline-flex;align-items:center;justify-content:center;
            gap:var(--chr-2);
            min-width:2.35rem;height:2.35rem;padding:0 var(--chr-2)!important;
            border-radius:var(--chr-r);
            color:var(--app-text)!important;
            transition:background-color var(--chr-ease),color var(--chr-ease);
        }
        .header-navbar .nav-link .ficon,
        .header-navbar .navbar-nav .nav-link>i{
            font-size:1.12rem;color:var(--app-muted)!important;
            transition:color var(--chr-ease);
        }
        .header-navbar .navbar-nav .nav-link:hover{
            background:var(--app-primary-soft)!important;
        }
        .header-navbar .navbar-nav .nav-link:hover .ficon,
        .header-navbar .navbar-nav .nav-link:hover>i,
        .header-navbar .navbar-nav .nav-link[aria-expanded="true"] .ficon{
            color:var(--app-primary)!important;
        }
        .header-navbar .navbar-nav .nav-link[aria-expanded="true"]{
            background:var(--app-primary-soft)!important;
        }
        .header-navbar .ficon.warning{color:var(--app-warning)!important;}

        /* بطاقة المستخدم */
        .header-navbar .dropdown-user .nav-link{
            gap:var(--chr-3);min-width:auto;height:auto;
            padding:var(--chr-1) var(--chr-2)!important;
        }
        .header-navbar .dropdown-user .user-nav{
            display:flex;flex-direction:column;align-items:flex-end;line-height:1.25;
        }
        .header-navbar .dropdown-user .user-name{
            color:var(--app-text)!important;font-weight:600;font-size:.86rem;
        }
        .header-navbar .dropdown-user .user-status{
            color:var(--app-muted)!important;font-size:.72rem;
        }
        .header-navbar .profile-picture-header{
            display:inline-flex;align-items:center;justify-content:center;
            width:2.35rem;height:2.35rem;border-radius:50%;
            background:var(--app-primary)!important;color:var(--app-on-primary)!important;
            font-weight:700;font-size:.95rem;line-height:1;
            box-shadow:0 0 0 2px color-mix(in srgb, var(--app-primary) 22%, transparent);
        }

        /*
            الشارات.

            كانت بلون القالب لا بلون النظام. والأهمّ: القالب يثبّتها عند
            `left:-2px` و`top:12px` — فتخرج من حدّ زرّها إلى خارجه وتركب
            على الأيقونة المجاورة، وتقف في منتصف الارتفاع لا عند الزاوية.

            صارت تُثبَّت بـ`inset-inline` فتعرف اتّجاه الصفحة، وتقف داخل
            حدّ زرّها عند زاويته العليا، فلا تلمس جارتها.
        */
        .header-navbar .navbar-nav .nav-link{position:relative;}
        .header-navbar .badge-up,
        .header-navbar .badge.badge-primary,
        .header-navbar .badge-pill.badge-primary{
            position:absolute!important;
            top:.28rem!important;bottom:auto!important;
            inset-inline-end:.2rem!important;inset-inline-start:auto!important;
            right:auto!important;left:auto!important;
            display:inline-flex;align-items:center;justify-content:center;
            min-width:1.02rem;height:1.02rem;padding:0 .26rem;
            background:var(--app-danger)!important;color:#fff!important;
            font-size:.64rem;font-weight:700;line-height:1;
            border-radius:999px;
            box-shadow:0 0 0 2px var(--app-topbar-bg);
        }

        /*
            اتّجاه فتح قوائم الشريط العلوي.

            **العطل**

            قوائم الشريط كلّها تحمل `dropdown-menu-right` من القالب. وملفّ
            بوتستراب المعكوس يعرّفها `left:0;right:auto` — فتُثبَّت الحافّةُ
            اليسرى للقائمة عند حافّة زرّها وتمتدّ **يميناً**. ومجموعةُ
            `app-topbar__start` في الصفحة العربية تقف في أقصى اليمين مُلاصقةً
            للقائمة الجانبية، فتمتدّ قائمةُ الإشعارات (وقائمة الزيارات معها)
            تحت الشريط الجانبي.

            والشريط الجانبي `z-index:1031` بينما الشريطُ العلويّ `997` —
            وهو سياقُ تراصٍّ يضمّ القائمة، فلا يرفعها فوقه شيء. فيختفي جزءٌ
            من كل إشعار: أوّلُ السطر مقصوص، و«عرض كل الإشعارات» في الذيل
            نصفُها مخفيّ.

            **العلاج**

            كلُّ مجموعةٍ تفتح قوائمها نحو **داخل** الصفحة لا نحو حافّتها:
            مجموعةُ البداية تُحاذي حافّتها الابتدائية فتمتدّ إلى الداخل،
            ومجموعةُ النهاية عكسها. فلا تلامس القائمةُ الشريطَ الجانبي
            أصلاً، ويستوي الأمرُ في الاتّجاهين بلا اعتمادٍ على تسمية
            `-right` المعكوسة.

            والقاعدةُ مكتوبةٌ مرّتين عمداً: بخصائصَ منطقية أوّلاً لتعمل حتى
            لو غاب `dir` عن `<html>`، ثم بخصائصَ فيزيائية مُقيَّدة بالاتّجاه
            — فترتيبُ التتالي بين `left` و`inset-inline-start` يختلف بين
            المتصفّحات، والثانيةُ أخصُّ فتحسم.
        */
        .header-navbar .app-topbar__start .dropdown-menu{
            inset-inline-start:0!important;inset-inline-end:auto!important;
        }
        .header-navbar .app-topbar__end .dropdown-menu{
            inset-inline-end:0!important;inset-inline-start:auto!important;
        }
        html[dir="rtl"] .header-navbar .app-topbar__start .dropdown-menu{
            right:0!important;left:auto!important;
        }
        html[dir="rtl"] .header-navbar .app-topbar__end .dropdown-menu{
            left:0!important;right:auto!important;
        }
        html[dir="ltr"] .header-navbar .app-topbar__start .dropdown-menu{
            left:0!important;right:auto!important;
        }
        html[dir="ltr"] .header-navbar .app-topbar__end .dropdown-menu{
            right:0!important;left:auto!important;
        }
        /* ومؤشّرُ القائمة يتبع حافّتها المُثبَّتة، وإلّا أشار إلى فراغ */
        html[dir="rtl"] .header-navbar .app-topbar__start .dropdown-menu::before{
            right:1.2rem!important;left:auto!important;
        }
        html[dir="rtl"] .header-navbar .app-topbar__end .dropdown-menu::before{
            left:1.2rem!important;right:auto!important;
        }
        html[dir="ltr"] .header-navbar .app-topbar__start .dropdown-menu::before{
            left:1.2rem!important;right:auto!important;
        }
        html[dir="ltr"] .header-navbar .app-topbar__end .dropdown-menu::before{
            right:1.2rem!important;left:auto!important;
        }
        /* ولا تتجاوز القائمةُ النافذة على الشاشات الضيّقة */
        .header-navbar .app-topbar .dropdown-menu{
            max-width:calc(100vw - 1.5rem);
        }

        /* القوائم المنسدلة: كانت بيضاءَ دائماً — تُفتح من شريطٍ أسود */
        .header-navbar .dropdown-menu{
            background:var(--app-surface)!important;
            border:1px solid var(--app-border)!important;
            border-radius:var(--chr-r-lg)!important;
            box-shadow:var(--chr-shadow-lg)!important;
            padding:var(--chr-1) 0;
            overflow:hidden;
        }
        .header-navbar .dropdown-menu .dropdown-item{
            display:flex;align-items:center;gap:var(--chr-2);
            padding:.52rem var(--chr-4);
            color:var(--app-text)!important;font-size:.86rem;
            transition:background-color var(--chr-ease),color var(--chr-ease);
        }
        .header-navbar .dropdown-menu .dropdown-item>i{
            color:var(--app-muted);font-size:1rem;
        }
        .header-navbar .dropdown-menu .dropdown-item:hover,
        .header-navbar .dropdown-menu .dropdown-item:focus{
            background:var(--app-primary-soft)!important;color:var(--app-primary)!important;
        }
        .header-navbar .dropdown-menu .dropdown-item:hover>i{color:var(--app-primary)!important;}
        .header-navbar .dropdown-menu .dropdown-item.active,
        .header-navbar .dropdown-menu .dropdown-item.bg-light{
            background:var(--app-primary-soft)!important;color:var(--app-primary)!important;
        }
        .header-navbar .dropdown-menu-header .dropdown-header{
            background:var(--app-primary)!important;
            margin:calc(var(--chr-1) * -1) 0 0;
            padding:var(--chr-3) var(--chr-4)!important;
        }
        .header-navbar .dropdown-menu-header .dropdown-header h3,
        .header-navbar .dropdown-menu-header .notification-title,
        .header-navbar .dropdown-menu-header .white{
            color:var(--app-on-primary)!important;
        }
        .header-navbar .dropdown-menu-footer{
            border-top:1px solid var(--app-border)!important;
            margin-bottom:calc(var(--chr-1) * -1);
        }
        .header-navbar .media-list .media,
        .header-navbar .notification-item{
            border-color:var(--app-border)!important;
            background:transparent!important;
        }
        .header-navbar .media-list .media:hover{background:var(--app-primary-soft)!important;}
        .header-navbar .notification-text,
        .header-navbar .media-heading{color:var(--app-text)!important;}
        .header-navbar .dropdown-divider{
            border-color:var(--app-border)!important;margin:var(--chr-1) 0;
        }
        .header-navbar .scrollable-container{background:var(--app-surface)!important;}

        /* ═══════════ الشريط السفلي ═══════════ */
        .footer,.footer-static,.app-footer{
            background:var(--app-surface)!important;
            border-top:1px solid var(--app-border)!important;
            color:var(--app-muted)!important;
        }
        .footer a,.app-footer a{color:var(--app-primary)!important;}
        .scroll-top{
            border-radius:var(--chr-r)!important;
            box-shadow:var(--chr-shadow)!important;
        }
        CSS;
    }

    /**
     * الوضع الليلي — يُطبَّق على السطوح والنصوص والحدود.
     *
     * قالب Vuexy يشحن `dark-layout.css` لكنه مرتبط بكلاس على `<body>` لا يضبطه
     * أحد في هذا المشروع، فالقواعد هنا تغطّي العناصر الفعلية المستعملة.
     */
    protected static function darkRules(): string
    {
        return <<<'CSS'
        /*
            كان `html` خارج القاعدة — و`body` لا يبلغ دائماً كامل الارتفاع،
            والشريط العلوي `floating-nav` يطفو بهامشٍ حوله. فيظهر أبيضُ
            المتصفّح من فوق الشريط وحوله في الوضع الليلي: إطارٌ أبيض حول
            شريطٍ داكن.
        */
        html,body,.app-content,.content-wrapper,.content-body,
        .vertical-layout,.vertical-menu-modern,#app,.wrapper{
            background-color:var(--app-body-bg)!important;color:var(--app-text)!important;
        }
        .card,.modal-content,.dropdown-menu,.list-group-item,.nav-tabs .nav-link.active,
        .table,.table-responsive,.custom-file-label,.popover,.toast{
            background-color:var(--app-surface)!important;color:var(--app-text)!important;
            border-color:var(--app-border)!important;
        }
        .card-header,.card-footer,.modal-header,.modal-footer{
            background-color:var(--app-surface)!important;border-color:var(--app-border)!important;
        }
        h1,h2,h3,h4,h5,h6,.card-title,label,.content-header-title,strong,th{color:var(--app-text)!important;}
        .text-muted,small,.form-text{color:var(--app-muted)!important;}
        .form-control,.custom-select,input,select,textarea{
            background-color:var(--app-body-bg)!important;color:var(--app-text)!important;
            border-color:var(--app-border)!important;
        }
        .form-control::placeholder{color:var(--app-muted)!important;}
        .table td,.table th,.table thead th,hr,.border,.border-top,.border-bottom{
            border-color:var(--app-border)!important;
        }
        .table-striped tbody tr:nth-of-type(odd),.table-hover tbody tr:hover{
            background-color:rgba(255,255,255,.03)!important;
        }
        .bg-light,.bg-white,.breadcrumb{background-color:var(--app-surface)!important;}
        .dropdown-item{color:var(--app-text)!important;}
        .dropdown-item:hover{background-color:var(--app-primary-soft)!important;}
        CSS;
    }

    protected static function staticSidebarRules(): string
    {
        return <<<'CSS'
        .main-menu.menu-fixed{position:absolute!important;}
        CSS;
    }

    /**
     * عرض القائمة الجانبية — القائمة والمحتوى يتحركان معاً وإلا تداخلا.
     *
     * القالب يزيح المحتوى بـ `margin` في الاتجاهين، فتُضبط الجهتان معاً
     * ليعمل مع العربية (RTL) والإنجليزية على السواء.
     */
    protected static function sidebarWidthRules(): string
    {
        /*
         * `:not(.menu-collapsed)` ليس تجميلاً — بدونه لا تُطوى القائمة أبداً.
         *
         * القالب يطوي القائمة بـ`body.menu-collapsed .main-menu{width:80px}`،
         * وكانت القاعدة أدناه تفرض العرض المخصّص بـ`!important` على كل حال،
         * فتغلبها. النتيجة: قائمة عالقة مفتوحة لا يردّها زرّ الطيّ ولا خروج
         * المؤشّر، ومحتوى يحتفظ بهامش القائمة الكاملة فتظهر فجوة إلى جانبه.
         *
         * والمطويّة عند مرور المؤشّر تحمل `.main-menu.expanded`، فتأخذ العرض
         * المخصّص أيضاً — وإلا اتّسعت إلى 260 من القالب متجاهلةً الإعداد.
         * أما هامش المحتوى فيبقى على 80 حينها لأن التوسّع طبقة فوق المحتوى
         * لا إزاحة له.
         * أما إزاحة المحتوى فمقيَّدة بـ`.menu-expanded` لا `:not(.menu-collapsed)`:
         * الثانية تصدق أيضاً على `menu-hide` (القائمة مخفيّة تحت 1200px) وعلى
         * الصفحات التي لا شريط فيها أصلاً (الداشبورد)، فكانت تترك فجوة بعرض
         * القائمة إلى جانب محتوىً لا قائمة بجواره.
         */
        return <<<'CSS'
        body:not(.menu-collapsed) .main-menu,
        body:not(.menu-collapsed) .main-menu.menu-fixed,
        body.menu-collapsed .main-menu.expanded{width:var(--app-sidebar-width)!important;}
        @media (min-width:992px){
            body.vertical-layout.vertical-menu-modern.menu-expanded .app-content,
            body.vertical-layout.vertical-menu-modern.menu-expanded .header-navbar,
            body.vertical-layout.vertical-menu-modern.menu-expanded footer{
                margin-left:var(--app-sidebar-width)!important;margin-right:0!important;
            }
            html[dir="rtl"] body.vertical-layout.vertical-menu-modern.menu-expanded .app-content,
            html[dir="rtl"] body.vertical-layout.vertical-menu-modern.menu-expanded .header-navbar,
            html[dir="rtl"] body.vertical-layout.vertical-menu-modern.menu-expanded footer{
                margin-right:var(--app-sidebar-width)!important;margin-left:0!important;
            }
        }
        CSS;
    }

    /**
     * ظل البطاقات — يشمل الحالة المطوية لأن القالب يعيد ضبطها.
     */
    protected static function shadowRules(): string
    {
        return <<<'CSS'
        .card,.card.collapsed{box-shadow:var(--app-card-shadow)!important;}
        CSS;
    }

    protected static function compactRules(): string
    {
        return <<<'CSS'
        .card-body{padding:.9rem 1.1rem!important;}
        .card{margin-bottom:1rem!important;}
        .table td,.table th{padding:.45rem .6rem!important;}
        .content-wrapper{padding-top:.5rem!important;}
        .content-header{margin-bottom:.5rem!important;}
        .btn{padding:.45rem .9rem!important;}
        CSS;
    }

    // ==================== الجداول والبطاقات وعناصر التحكّم ====================

    /**
     * الجداول — رأس ملوّن، تخطيط الصفوف، ونمط الحدود.
     *
     * تُبنى بالسلاسل لا بـ heredoc لأن كل قاعدة مشروطة بإعدادها وحده: من لم
     * يضبط شيئاً لا يُطبع له شيء، فيبقى جدول القالب الأصلي كما هو.
     */
    protected static function tableRules(): string
    {
        $theme = self::current();
        $rules = '';

        $header = '';
        if (filled($theme->table_header_bg)) {
            $header .= 'background-color:var(--app-table-header-bg)!important;';
        }
        if (filled($theme->table_header_text)) {
            $header .= 'color:var(--app-table-header-text)!important;';
        }
        if ($header !== '') {
            $rules .= '.table thead th,.table th{'.$header.'}';
        }

        if ($theme->table_striped) {
            $rules .= '.table tbody tr:nth-of-type(odd){background-color:var(--app-table-stripe)!important;}';
        }

        if ($theme->table_hover) {
            $rules .= '.table tbody tr:hover{background-color:var(--app-primary-soft)!important;}';
        }

        return $rules.(ColorSitting::TABLE_BORDERS[$theme->valueOr('table_border_style')] ?? '');
    }

    /**
     * البطاقات — الحد ورأس البطاقة والحشو الداخلي.
     */
    protected static function cardRules(): string
    {
        $theme = self::current();
        $rules = '';

        if ($theme->card_border) {
            $rules .= '.card{border:1px solid var(--app-border,#e6e9f0)!important;}';
        }

        if (filled($theme->card_header_bg)) {
            $rules .= '.card-header{background-color:var(--app-card-header-bg)!important;}';
        }

        if (filled($theme->card_padding)) {
            $rules .= '.card-body{padding:var(--app-card-padding)!important;}';
        }

        return $rules;
    }

    /**
     * الأزرار وحقول الإدخال.
     *
     * مقاس الزر يستثني `.btn-sm` و`.btn-lg` و`.btn-icon` لأنها مقاسات قصدها
     * كاتب الشاشة، فلا يصحّ أن يبتلعها المقاس العام.
     */
    protected static function controlRules(): string
    {
        $theme = self::current();
        $rules = '';

        $size = ColorSitting::BUTTON_SIZES[$theme->valueOr('button_size')] ?? null;
        if ($size) {
            $rules .= '.btn:not(.btn-sm):not(.btn-lg):not(.btn-icon){'.$size.'}';
        }

        if (filled($theme->button_radius)) {
            $rules .= '.btn{border-radius:var(--app-button-radius)!important;}';
        }

        if (filled($theme->input_bg)) {
            $rules .= '.form-control,.custom-select{background-color:var(--app-input-bg)!important;}';
        }

        if (filled($theme->input_height)) {
            // `textarea` مستثناة: ارتفاعها من عدد الأسطر لا من مقاس الحقل
            $rules .= '.form-control:not(textarea),.custom-select{height:var(--app-input-height)!important;}';
        }

        return $rules;
    }

    /**
     * الشريط العلوي وعرض منطقة المحتوى.
     */
    protected static function layoutRules(): string
    {
        $theme = self::current();
        $rules = '';

        if (filled($theme->topbar_height)) {
            $rules .= '.header-navbar,.header-navbar .navbar-container{min-height:var(--app-topbar-height)!important;}';
        }

        if ($theme->valueOr('content_width') === 'boxed') {
            $rules .= '.app-content .content-wrapper{max-width:1440px;margin-left:auto;margin-right:auto;}';
        }

        return $rules;
    }

    /**
     * CSS إضافي كتبه المسؤول — آخر ما يُحقن فيتجاوز كل ما سبقه.
     *
     * الحقل محمي بصلاحية إعدادات النظام، لكن `</style` يُبطَل على أي حال حتى
     * لا يُغلَق وسم الأنماط مبكراً فينقلب باقي النص إلى HTML منفَّذ.
     */
    protected static function customCss(): string
    {
        $css = self::current()->custom_css;

        if (! filled($css)) {
            return '';
        }

        return preg_replace('#</\s*style#i', '', $css);
    }

    // ==================== الهوية البصرية ====================

    /**
     * اسم النظام المعروض — ما ضبطه المسؤول، وإلا النص الأصلي لموضع النداء.
     *
     * كل موضع يمرّر نصّه المكتوب حالياً كبديل، فلا يتغيّر شيء قبل أن يضبط
     * المسؤول الاسم فعلاً (`APP_NAME` هنا ما زال `Laravel` فلا يصلح بديلاً).
     */
    public static function appTitle(?string $fallback = null): string
    {
        $title = self::current()->app_title;

        if (filled($title)) {
            return $title;
        }

        return $fallback ?? (string) config('app.name', 'Fawtra');
    }

    /** أيقونة المتصفح المرفوعة — null يعني الإبقاء على أيقونة القالب */
    public static function faviconUrl(): ?string
    {
        return self::current()->favicon_url;
    }

    /** شعار صفحة الدخول المرفوع */
    public static function loginLogoUrl(): ?string
    {
        return self::current()->login_logo_url;
    }

    /**
     * خلفية صفحة الدخول — تدرّج من اللون المختار إلى درجة أغمق منه.
     *
     * تتبع اللون الأساسي ما لم يُفرد لها لون، فصفحة الدخول تواكب هوية النظام
     * دون إعداد إضافي.
     */
    public static function loginBackground(): string
    {
        $theme = self::current();
        $base = filled($theme->login_bg_color)
            ? $theme->login_bg_color
            : $theme->valueOr('primary_color');

        return 'linear-gradient(135deg, '.$base.' 0%, '.ColorTools::darken($base, 0.22).' 100%)';
    }
}
