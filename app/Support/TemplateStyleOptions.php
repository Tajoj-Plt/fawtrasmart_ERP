<?php

namespace App\Support;

/**
 * خيارات تصميم القالب التي تُضبط من لوحة مرئية بدل كتابة CSS.
 *
 * كل خيار يعرف نوعه وحدوده وقيمته الافتراضية، فتُبنى منه الواجهة
 * ويُتحقَّق منه على الخادم — القيم تُطبع داخل `<style>` فلا تُقبل كما وردت.
 */
class TemplateStyleOptions
{
    /**
     * @return array<string, array{label: string, icon: string, hint: string, options: array}>
     */
    public const GROUPS = [
        'page' => [
            'label' => 'الصفحة',
            'icon' => 'file',
            'hint' => 'هوامش الورقة وخلفيتها وإطارها',
            'options' => [
                'page_padding' => [
                    'label' => 'هامش الورقة', 'type' => 'number', 'unit' => 'مم',
                    'default' => 15, 'min' => 0, 'max' => 40, 'step' => 1,
                ],
                'page_bg' => ['label' => 'لون الخلفية', 'type' => 'color', 'default' => '#ffffff'],
                'page_border' => [
                    'label' => 'إطار الصفحة', 'type' => 'select', 'default' => 'none',
                    'choices' => ['none' => 'بلا إطار', 'frame' => 'إطار كامل', 'top' => 'خط علوي عريض'],
                ],
                'page_border_color' => ['label' => 'لون الإطار', 'type' => 'color', 'default' => '#d5d8e0'],
            ],
        ],

        'text' => [
            'label' => 'النصوص والعناوين',
            'icon' => 'type',
            'hint' => 'لون النص وتباعد الأسطر وأحجام العناوين',
            'options' => [
                'text_color' => ['label' => 'لون النص', 'type' => 'color', 'default' => '#1f2430'],
                'line_height' => [
                    'label' => 'تباعد الأسطر', 'type' => 'number', 'unit' => '',
                    'default' => 1.6, 'min' => 1, 'max' => 3, 'step' => 0.1,
                ],
                'heading_color' => ['label' => 'لون العناوين', 'type' => 'color', 'default' => '#1f2430'],
                'heading_size' => [
                    'label' => 'حجم العنوان الرئيسي', 'type' => 'number', 'unit' => 'بكسل',
                    'default' => 22, 'min' => 10, 'max' => 48, 'step' => 1,
                ],
            ],
        ],

        'table' => [
            'label' => 'جدول الأصناف',
            'icon' => 'grid',
            'hint' => 'رأس الجدول وحدوده وتظليل صفوفه',
            'options' => [
                'table_head_bg' => ['label' => 'خلفية رأس الجدول', 'type' => 'color', 'default' => '#f4f5fa'],
                'table_head_color' => ['label' => 'لون نص الرأس', 'type' => 'color', 'default' => '#2f3349'],
                'table_border_color' => ['label' => 'لون الحدود', 'type' => 'color', 'default' => '#d5d8e0'],
                'table_border_width' => [
                    'label' => 'سُمك الحد', 'type' => 'number', 'unit' => 'بكسل',
                    'default' => 1, 'min' => 0, 'max' => 4, 'step' => 1,
                ],
                'table_cell_padding' => [
                    'label' => 'حشو الخلية', 'type' => 'number', 'unit' => 'بكسل',
                    'default' => 6, 'min' => 0, 'max' => 20, 'step' => 1,
                ],
                'table_font_size' => [
                    'label' => 'حجم خط الجدول', 'type' => 'number', 'unit' => 'بكسل',
                    'default' => 12, 'min' => 6, 'max' => 22, 'step' => 1,
                ],
                'table_stripe' => ['label' => 'تظليل الصفوف بالتناوب', 'type' => 'switch', 'default' => false],
                'table_stripe_color' => ['label' => 'لون التظليل', 'type' => 'color', 'default' => '#fafbfd'],
            ],
        ],

        'media' => [
            'label' => 'الشعار والرموز',
            'icon' => 'image',
            'hint' => 'مقاس شعار المنشأة ورمز QR',
            'options' => [
                'logo_height' => [
                    'label' => 'ارتفاع الشعار', 'type' => 'number', 'unit' => 'بكسل',
                    'default' => 70, 'min' => 20, 'max' => 200, 'step' => 5,
                ],
                'qr_size' => [
                    'label' => 'مقاس رمز QR', 'type' => 'number', 'unit' => 'بكسل',
                    'default' => 100, 'min' => 40, 'max' => 220, 'step' => 5,
                ],
            ],
        ],
    ];

    /**
     * المجموعات بتسمياتها وتلميحاتها المترجَمة.
     *
     * `GROUPS` ثابتٌ لا يقبل نداء `__()`، فقيمه العربية احتياط. الوحدات
     * («مم»، «بكسل») وقوائم الاختيار تُترجَم كذلك — هي نصّ يقرأه المستخدم.
     */
    public static function groups(): array
    {
        $units = __('settings.doc_templates.style_units');
        $units = is_array($units) ? array_values($units) : [];
        $unitMap = ['مم' => $units[0] ?? 'مم', 'بكسل' => $units[1] ?? 'بكسل'];

        $out = [];

        foreach (self::GROUPS as $groupKey => $group) {
            $options = [];

            foreach ($group['options'] as $key => $option) {
                $option['label'] = self::tr('style_options.'.$key, $option['label']);

                if (! empty($option['unit'])) {
                    $option['unit'] = $unitMap[$option['unit']] ?? $option['unit'];
                }

                if (! empty($option['choices'])) {
                    $choices = __('settings.doc_templates.style_choices.'.$key);

                    if (is_array($choices)) {
                        foreach ($option['choices'] as $choice => $label) {
                            $option['choices'][$choice] = $choices[$choice] ?? $label;
                        }
                    }
                }

                $options[$key] = $option;
            }

            $out[$groupKey] = [
                'label' => self::tr('style_groups.'.$groupKey.'.label', $group['label']),
                'icon' => $group['icon'],
                'hint' => self::tr('style_groups.'.$groupKey.'.hint', $group['hint']),
                'options' => $options,
            ];
        }

        return $out;
    }

    private static function tr(string $key, string $fallback): string
    {
        $full = 'settings.doc_templates.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    /**
     * تعريف خيار واحد بمفتاحه.
     */
    public static function option(string $key): ?array
    {
        foreach (self::GROUPS as $group) {
            if (isset($group['options'][$key])) {
                return $group['options'][$key];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $keys = [];

        foreach (self::GROUPS as $group) {
            $keys = array_merge($keys, array_keys($group['options']));
        }

        return $keys;
    }

    /**
     * القيم الافتراضية لكل الخيارات.
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::GROUPS as $group) {
            foreach ($group['options'] as $key => $option) {
                $defaults[$key] = $option['default'];
            }
        }

        return $defaults;
    }

    /**
     * المجموعة التي ينتمي إليها الخيار.
     */
    public static function groupOf(string $key): ?string
    {
        foreach (self::GROUPS as $groupKey => $group) {
            if (isset($group['options'][$key])) {
                return $groupKey;
            }
        }

        return null;
    }
}
