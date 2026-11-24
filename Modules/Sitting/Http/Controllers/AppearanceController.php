<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ColorSitting;
use App\Models\Log as ModelsLog;
use App\Support\ThemeSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * مظهر النظام — الألوان والخط والشكل العام والهوية البصرية.
 *
 * صف واحد في `color_sittings` يمثّل مظهر النظام كله. الشاشة تقرأه وتكتب عليه،
 * وتُبطل كاش المظهر بعد كل حفظ حتى يسري التغيير على الطلب التالي مباشرة.
 */
class AppearanceController extends Controller
{
    /** ألوان مقبولة: كود سداسي فقط — يمنع حقن CSS عبر قيمة اللون */
    protected const COLOR_RULE = ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

    public function __construct()
    {
        $this->middleware('permission:settings_edit_general_settings');
    }

    public function index()
    {
        return view('sitting::appearance.index', [
            'theme' => ThemeSettings::current(),
            'fonts' => ColorSitting::FONTS,
            'presets' => ColorSitting::PRESETS,
            'shadows' => ColorSitting::SHADOWS,
            'tableBorders' => ColorSitting::TABLE_BORDERS,
            'buttonSizes' => ColorSitting::BUTTON_SIZES,
            'contentWidths' => ColorSitting::CONTENT_WIDTHS,
            'defaults' => ColorSitting::DEFAULTS,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'color' => self::COLOR_RULE,
            'primary_color' => self::COLOR_RULE,
            'sidebar_color' => self::COLOR_RULE,
            'sidebar_text_color' => self::COLOR_RULE,
            'topbar_color' => self::COLOR_RULE,
            'success_color' => self::COLOR_RULE,
            'danger_color' => self::COLOR_RULE,
            'info_color' => self::COLOR_RULE,
            'warning_color' => self::COLOR_RULE,
            'link_color' => self::COLOR_RULE,
            'login_bg_color' => self::COLOR_RULE,
            'font_family' => ['nullable', Rule::in(array_keys(ColorSitting::FONTS))],
            'font_size' => ['nullable', 'integer', 'min:11', 'max:20'],
            'heading_font_weight' => ['nullable', Rule::in(['400', '500', '600', '700', '800'])],
            'border_radius' => ['nullable', 'integer', 'min:0', 'max:24'],
            'layout_mode' => ['nullable', Rule::in(['light', 'dark', 'semi-dark'])],
            'sidebar_position' => ['nullable', Rule::in(['fixed', 'static'])],
            'sidebar_width' => ['nullable', 'integer', 'min:200', 'max:400'],
            'card_shadow' => ['nullable', Rule::in(array_keys(ColorSitting::SHADOWS))],
            'card_border' => ['nullable', 'boolean'],
            'card_header_bg' => self::COLOR_RULE,
            'card_padding' => ['nullable', 'integer', 'min:8', 'max:40'],
            'table_header_bg' => self::COLOR_RULE,
            'table_header_text' => self::COLOR_RULE,
            'table_striped' => ['nullable', 'boolean'],
            'table_hover' => ['nullable', 'boolean'],
            'table_border_style' => ['nullable', Rule::in(array_keys(ColorSitting::TABLE_BORDERS))],
            'button_size' => ['nullable', Rule::in(array_keys(ColorSitting::BUTTON_SIZES))],
            'button_radius' => ['nullable', 'integer', 'min:0', 'max:30'],
            'input_bg' => self::COLOR_RULE,
            'input_height' => ['nullable', 'integer', 'min:30', 'max:60'],
            'topbar_height' => ['nullable', 'integer', 'min:48', 'max:100'],
            'content_width' => ['nullable', Rule::in(ColorSitting::CONTENT_WIDTHS)],
            'custom_css' => ['nullable', 'string', 'max:20000'],
            'compact_mode' => ['nullable', 'boolean'],
            'app_title' => ['nullable', 'string', 'max:100'],
            'login_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,svg', 'max:512'],
        ], [
            'color.regex' => __('settings.appearance.errors.color_regex'),
            'primary_color.regex' => __('settings.appearance.errors.primary_regex'),
            'font_size.min' => __('settings.appearance.errors.font_size_min'),
            'font_size.max' => __('settings.appearance.errors.font_size_max'),
            'login_logo.max' => __('settings.appearance.errors.logo_max'),
        ]);

        $theme = ColorSitting::first() ?: new ColorSitting;

        // الوحدات تُلحق هنا لا في النموذج، فيبقى المُدخل رقماً يمكن التحقق منه
        $attributes = [
            'color' => $data['color'] ?? null,
            'primary_color' => $data['primary_color'] ?? null,
            'sidebar_color' => $data['sidebar_color'] ?? null,
            'sidebar_text_color' => $data['sidebar_text_color'] ?? null,
            'topbar_color' => $data['topbar_color'] ?? null,
            'success_color' => $data['success_color'] ?? null,
            'danger_color' => $data['danger_color'] ?? null,
            'info_color' => $data['info_color'] ?? null,
            'warning_color' => $data['warning_color'] ?? null,
            'link_color' => $data['link_color'] ?? null,
            'login_bg_color' => $data['login_bg_color'] ?? null,
            'font_family' => $data['font_family'] ?? null,
            'font_size' => isset($data['font_size']) ? $data['font_size'].'px' : null,
            'heading_font_weight' => $data['heading_font_weight'] ?? null,
            'border_radius' => isset($data['border_radius']) ? $data['border_radius'].'px' : null,
            'layout_mode' => $data['layout_mode'] ?? 'light',
            'sidebar_position' => $data['sidebar_position'] ?? 'fixed',
            'sidebar_width' => isset($data['sidebar_width']) ? $data['sidebar_width'].'px' : null,
            'card_shadow' => $data['card_shadow'] ?? 'default',
            'card_border' => $request->boolean('card_border'),
            'card_header_bg' => $data['card_header_bg'] ?? null,
            'card_padding' => isset($data['card_padding']) ? $data['card_padding'].'px' : null,
            'table_header_bg' => $data['table_header_bg'] ?? null,
            'table_header_text' => $data['table_header_text'] ?? null,
            'table_striped' => $request->boolean('table_striped'),
            'table_hover' => $request->boolean('table_hover'),
            'table_border_style' => $data['table_border_style'] ?? 'default',
            'button_size' => $data['button_size'] ?? 'default',
            'button_radius' => isset($data['button_radius']) ? $data['button_radius'].'px' : null,
            'input_bg' => $data['input_bg'] ?? null,
            'input_height' => isset($data['input_height']) ? $data['input_height'].'px' : null,
            'topbar_height' => isset($data['topbar_height']) ? $data['topbar_height'].'px' : null,
            'content_width' => $data['content_width'] ?? 'full',
            'custom_css' => $data['custom_css'] ?? null,
            'compact_mode' => $request->boolean('compact_mode'),
            'app_title' => $data['app_title'] ?? null,
            'updated_by' => auth()->id(),
        ];

        // الفراغ يعني «اترك ما في القالب» — يُخزَّن null لا سلسلة فارغة، حتى
        // يتصرّف الحقل بالطريقة نفسها سواء مرّ الطلب بمحوّل الفراغات أو لا
        $attributes = array_map(fn ($value) => $value === '' ? null : $value, $attributes);

        foreach (['login_logo', 'favicon'] as $field) {
            if ($request->hasFile($field)) {
                // الملف القديم يُحذف حتى لا تتراكم نسخ مهجورة في التخزين
                if ($theme->{$field}) {
                    Storage::disk('public')->delete($theme->{$field});
                }

                $attributes[$field] = $request->file($field)->store('appearance', 'public');
            }
        }

        $theme->fill($attributes)->save();

        ThemeSettings::forget();

        ModelsLog::create([
            'type' => 'setting',
            'type_id' => $theme->id,
            'type_log' => 'log',
            'description' => __('settings.appearance.log_updated'),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('Appearance.index')->with('success', __('settings.appearance.saved'));
    }

    /**
     * إعادة المظهر إلى القيم الافتراضية.
     */
    public function reset()
    {
        $theme = ColorSitting::first() ?: new ColorSitting;

        foreach (['login_logo', 'favicon'] as $field) {
            if ($theme->{$field}) {
                Storage::disk('public')->delete($theme->{$field});
            }
        }

        $theme->fill(ColorSitting::DEFAULTS + [
            // الحقول الاختيارية ليست في DEFAULTS — تُفرَّغ صراحةً
            'link_color' => null,
            'login_bg_color' => null,
            'custom_css' => null,
            'card_header_bg' => null,
            'card_padding' => null,
            'table_header_bg' => null,
            'table_header_text' => null,
            'button_radius' => null,
            'input_bg' => null,
            'input_height' => null,
            'topbar_height' => null,
            'login_logo' => null,
            'favicon' => null,
            'app_title' => null,
            'updated_by' => auth()->id(),
        ])->save();

        ThemeSettings::forget();

        return redirect()->route('Appearance.index')->with('success', __('settings.appearance.reset_done'));
    }

    // ==================== توافق مع المسارات القديمة ====================

    /** كان `SittingAccountController::color` */
    public function legacyColor()
    {
        return redirect()->route('Appearance.index');
    }

    /**
     * كان `SittingAccountController::updateColor` — يحفظ لون الخلفية وحده.
     * يبقى عاملاً لأي نموذج قديم ما زال يرسل إليه.
     */
    public function legacyUpdateColor(Request $request)
    {
        $data = $request->validate(
            ['color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/']],
            ['color.regex' => __('settings.appearance.errors.color_regex')]
        );

        $theme = ColorSitting::first() ?: new ColorSitting;
        $theme->color = $data['color'];
        $theme->updated_by = auth()->id();
        $theme->save();

        ThemeSettings::forget();

        return redirect()->back()->with('success', __('settings.appearance.color_updated'));
    }
}
