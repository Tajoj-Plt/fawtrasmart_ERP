<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * إعدادات مظهر النظام — لون واحد سابقاً، وصارت هوية بصرية كاملة.
 *
 * اسم الجدول `color_sittings` والعمود `color` باقيان كما هما لأن 32 ملف قالب
 * يقرأ `$backgroundColorr` المشتق منهما.
 *
 * صف واحد فقط يُستعمل — `current()` تجلبه مخزَّناً في الكاش.
 */
class ColorSitting extends Model
{
    protected $fillable = [
        'color',
        'primary_color',
        'sidebar_color',
        'sidebar_text_color',
        'topbar_color',
        'success_color',
        'danger_color',
        'info_color',
        'warning_color',
        'link_color',
        'font_family',
        'font_size',
        'heading_font_weight',
        'border_radius',
        'layout_mode',
        'sidebar_position',
        'sidebar_width',
        'card_shadow',
        'card_border',
        'card_header_bg',
        'card_padding',
        'table_header_bg',
        'table_header_text',
        'table_striped',
        'table_hover',
        'table_border_style',
        'button_size',
        'button_radius',
        'input_bg',
        'input_height',
        'topbar_height',
        'content_width',
        'compact_mode',
        'login_bg_color',
        'custom_css',
        'login_logo',
        'favicon',
        'app_title',
        'updated_by',
    ];

    protected $casts = [
        'compact_mode' => 'boolean',
        'card_border' => 'boolean',
        'table_striped' => 'boolean',
        'table_hover' => 'boolean',
    ];

    /**
     * القيم المعتمدة حين لا يضبط المستخدم شيئاً — ألوان قالب Vuexy الأصلية،
     * فالمظهر الافتراضي يطابق ما كان عليه النظام قبل هذه الشاشة.
     */
    public const DEFAULTS = [
        'color' => '#ffffff',
        'primary_color' => '#7367F0',
        'sidebar_color' => '#ffffff',
        'sidebar_text_color' => '#565656',
        'topbar_color' => '#ffffff',
        'success_color' => '#28C76F',
        'danger_color' => '#EA5455',
        'info_color' => '#00CFE8',
        'warning_color' => '#FF9F43',
        'font_family' => 'Cairo',
        'font_size' => '14px',
        'heading_font_weight' => '600',
        'border_radius' => '6px',
        'layout_mode' => 'light',
        'sidebar_position' => 'fixed',
        'sidebar_width' => '260px',
        'card_shadow' => 'default',
        'card_border' => false,
        'table_striped' => false,
        'table_hover' => false,
        'table_border_style' => 'default',
        'button_size' => 'default',
        'content_width' => 'full',
        'compact_mode' => false,
    ];

    /**
     * الخطوط المتاحة — العائلة الكاملة تُبنى مع بدائل النظام.
     */
    public const FONTS = [
        'Cairo' => ['label' => 'Cairo — القاهرة', 'stack' => "'Cairo', sans-serif"],
        'Tajawal' => ['label' => 'Tajawal — تجوّل', 'stack' => "'Tajawal', sans-serif"],
        'Almarai' => ['label' => 'Almarai — المراعي', 'stack' => "'Almarai', sans-serif"],
        'IBM Plex Sans Arabic' => ['label' => 'IBM Plex Sans Arabic', 'stack' => "'IBM Plex Sans Arabic', sans-serif"],
        'Noto Kufi Arabic' => ['label' => 'Noto Kufi Arabic — كوفي', 'stack' => "'Noto Kufi Arabic', sans-serif"],
        'Readex Pro' => ['label' => 'Readex Pro', 'stack' => "'Readex Pro', sans-serif"],
        'Rubik' => ['label' => 'Rubik', 'stack' => "'Rubik', sans-serif"],
        'system' => ['label' => 'خط النظام الافتراضي', 'stack' => "system-ui, -apple-system, 'Segoe UI', sans-serif"],
    ];

    /**
     * ظلال البطاقات — `default` تترك ظل القالب الأصلي كما هو.
     *
     * القيمة هي `box-shadow` كاملة، فالاختيار يُطبَّق مباشرة بلا اشتقاق.
     */
    public const SHADOWS = [
        'default' => null,
        'none' => 'none',
        'soft' => '0 2px 8px rgba(34,41,47,.06)',
        'medium' => '0 4px 16px rgba(34,41,47,.10)',
        'strong' => '0 8px 28px rgba(34,41,47,.16)',
    ];

    /**
     * حدود الجداول — `null` تترك حدود القالب كما هي.
     *
     * القيمة قواعد CSS جاهزة لأن كل خيار يلمس عناصر مختلفة (الصفوف وحدها،
     * أو الخلايا كلها)، فلا يصلح اشتقاقها من قيمة واحدة.
     */
    public const TABLE_BORDERS = [
        'default' => null,
        'none' => '.table td,.table th,.table thead th{border:0!important;}',
        'horizontal' => '.table td,.table th{border-left:0!important;border-right:0!important;}',
        'full' => '.table td,.table th{border:1px solid var(--app-border,#e6e9f0)!important;}',
    ];

    /**
     * مقاسات الأزرار — `null` تترك مقاس القالب.
     */
    public const BUTTON_SIZES = [
        'default' => null,
        'sm' => 'padding:.35rem .8rem!important;font-size:.8rem!important;',
        'md' => 'padding:.6rem 1.2rem!important;font-size:.9rem!important;',
        'lg' => 'padding:.8rem 1.6rem!important;font-size:1rem!important;',
    ];

    /**
     * عرض منطقة المحتوى — `boxed` تحصر الصفحة في عرض أقصى وتوسّطها.
     */
    public const CONTENT_WIDTHS = ['full', 'boxed'];

    /**
     * لوحات ألوان جاهزة تُطبَّق بنقرة واحدة.
     *
     * كل لوحة تحمل `layout_mode` أيضاً، فاختيارها يضبط الوضع الفاتح أو الليلي
     * المناسب لها بدل أن تُترك ألوان داكنة فوق سطوح بيضاء.
     */
    public const PRESETS = [
        'default' => ['label' => 'البنفسجي الافتراضي', 'group' => 'فاتح', 'layout_mode' => 'light', 'primary_color' => '#7367F0', 'sidebar_color' => '#FFFFFF', 'sidebar_text_color' => '#565656', 'topbar_color' => '#FFFFFF', 'color' => '#F8F8F8', 'success_color' => '#28C76F', 'danger_color' => '#EA5455', 'info_color' => '#00CFE8', 'warning_color' => '#FF9F43'],
        'emerald' => ['label' => 'الأخضر الزمردي', 'group' => 'فاتح', 'layout_mode' => 'light', 'primary_color' => '#0E9F6E', 'sidebar_color' => '#0B3B2E', 'sidebar_text_color' => '#D7E8E1', 'topbar_color' => '#FFFFFF', 'color' => '#F5F8F7', 'success_color' => '#28C76F', 'danger_color' => '#E14D4D', 'info_color' => '#17A2B8', 'warning_color' => '#E8A33D'],
        'ocean' => ['label' => 'الأزرق المحيطي', 'group' => 'فاتح', 'layout_mode' => 'light', 'primary_color' => '#1B6CA8', 'sidebar_color' => '#0F2C43', 'sidebar_text_color' => '#CFE0EC', 'topbar_color' => '#FFFFFF', 'color' => '#F4F7FA', 'success_color' => '#20A97B', 'danger_color' => '#DC4C4C', 'info_color' => '#2AA9CF', 'warning_color' => '#E7A33C'],
        'sunset' => ['label' => 'البرتقالي الدافئ', 'group' => 'فاتح', 'layout_mode' => 'light', 'primary_color' => '#E8720C', 'sidebar_color' => '#2B1B10', 'sidebar_text_color' => '#F0DCC9', 'topbar_color' => '#FFFFFF', 'color' => '#FAF7F4', 'success_color' => '#2FA84F', 'danger_color' => '#D93A3A', 'info_color' => '#2F9FB5', 'warning_color' => '#F0A31E'],
        'royal' => ['label' => 'النبيذي الملكي', 'group' => 'فاتح', 'layout_mode' => 'light', 'primary_color' => '#9B1B4B', 'sidebar_color' => '#2C0F1E', 'sidebar_text_color' => '#EFD6E0', 'topbar_color' => '#FFFFFF', 'color' => '#FAF5F7', 'success_color' => '#28A05C', 'danger_color' => '#D33B3B', 'info_color' => '#2C93B5', 'warning_color' => '#E0A22F'],
        'sand' => ['label' => 'الرملي الهادئ', 'group' => 'فاتح', 'layout_mode' => 'light', 'primary_color' => '#8A6D3B', 'sidebar_color' => '#FBF8F2', 'sidebar_text_color' => '#5B5347', 'topbar_color' => '#FFFFFF', 'color' => '#FAF8F4', 'success_color' => '#3E8E5A', 'danger_color' => '#C4553D', 'info_color' => '#4F8FA6', 'warning_color' => '#C99A3F'],

        'midnight' => ['label' => 'ليلي — منتصف الليل', 'group' => 'ليلي', 'layout_mode' => 'dark', 'primary_color' => '#7367F0', 'sidebar_color' => '#10163A', 'sidebar_text_color' => '#D0D2D6', 'topbar_color' => '#283046', 'color' => '#161D31', 'success_color' => '#28C76F', 'danger_color' => '#EA5455', 'info_color' => '#00CFE8', 'warning_color' => '#FF9F43'],
        'carbon' => ['label' => 'ليلي — الكربون', 'group' => 'ليلي', 'layout_mode' => 'dark', 'primary_color' => '#00CFE8', 'sidebar_color' => '#1A1D24', 'sidebar_text_color' => '#C8CBD3', 'topbar_color' => '#22262E', 'color' => '#15181E', 'success_color' => '#28C76F', 'danger_color' => '#FF6B6B', 'info_color' => '#00CFE8', 'warning_color' => '#FFA94D'],
        'forest' => ['label' => 'ليلي — الغابة', 'group' => 'ليلي', 'layout_mode' => 'dark', 'primary_color' => '#2FBF71', 'sidebar_color' => '#0F1F1A', 'sidebar_text_color' => '#CADFD6', 'topbar_color' => '#16281F', 'color' => '#101A16', 'success_color' => '#2FBF71', 'danger_color' => '#E5544B', 'info_color' => '#2BB3C0', 'warning_color' => '#E8A33D'],
    ];

    // ==================== مساعدات ====================

    /**
     * اسم اللوحة الجاهزة مترجَماً.
     *
     * `PRESETS` ثابتٌ لا يقبل نداء `__()`، فقيمه العربية احتياط. اللوحات
     * تُجمَّع في الشاشة باسم المجموعة العربي، فيُبحَث عن مفتاحه اللاتيني.
     */
    public static function presetLabel(string $key, ?string $fallback = null): string
    {
        $fallback ??= self::PRESETS[$key]['label'] ?? $key;

        return self::trOr('presets.'.$key, $fallback);
    }

    public static function presetGroupLabel(string $arabicGroup): string
    {
        $keys = ['light' => 'فاتح', 'dark' => 'ليلي'];
        $key = array_search($arabicGroup, $keys, true);

        return $key === false ? $arabicGroup : self::trOr('preset_groups.'.$key, $arabicGroup);
    }

    private static function trOr(string $key, string $fallback): string
    {
        $full = 'settings.appearance.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    /** قيمة الحقل أو الافتراضي حين يكون فارغاً */
    public function valueOr(string $key)
    {
        return filled($this->{$key}) ? $this->{$key} : (self::DEFAULTS[$key] ?? null);
    }

    /** عائلة الخط الكاملة الجاهزة لـ CSS */
    public function getFontStackAttribute(): string
    {
        $font = $this->valueOr('font_family');

        return self::FONTS[$font]['stack'] ?? "'{$font}', sans-serif";
    }

    public function getLoginLogoUrlAttribute(): ?string
    {
        return $this->login_logo ? asset('storage/'.$this->login_logo) : null;
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->favicon ? asset('storage/'.$this->favicon) : null;
    }
}
