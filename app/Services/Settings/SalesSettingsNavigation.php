<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\Route;

/**
 * سجلّ شاشات إعدادات المبيعات — نظير `SettingsNavigation` لوحدة الإعدادات.
 *
 * لوحة إعدادات المبيعات كانت ثماني بطاقات مكتوبة يدوياً في قالبها، فبقيت
 * شاشات قائمة خارجها لا يُوصَل إليها إلا بكتابة الرابط: إعدادات أوامر
 * التوريد، وترقيم الفواتير، وعروض الحسومات. والبطاقة الوحيدة التي كانت
 * تشير إلى «العروض» كانت تشير إلى عروض الهدايا لا إليها.
 *
 * الآن: من يضيف شاشة يضيف سطراً هنا فتظهر في اللوحة، ومن يحذف مسارها
 * يسقط بندها تلقائياً بدل أن تنكسر اللوحة كلّها.
 *
 * `permission` تُخفي البند عمّن لا يملكها، وهي للعرض فقط — المنع الحقيقي
 * في المتحكّمات، فإخفاء رابط ليس حمايةً.
 *
 * @see SettingsNavigation النظير للإعدادات العامّة
 */
class SalesSettingsNavigation
{
    /**
     * @return array<string, array{icon:string,items:array<int, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            __('sales_settings.nav.groups.invoicing') => [
                'icon' => 'file-text',
                'items' => [
                    [
                        'label' => __('sales_settings.nav.invoice'),
                        'hint' => __('sales_settings.nav.invoice_hint'),
                        'icon' => 'settings',
                        'route' => 'SittingInvoice.invoice',
                    ],
                    [
                        'label' => __('sales_settings.nav.designs'),
                        'hint' => __('sales_settings.nav.designs_hint'),
                        'icon' => 'layout',
                        'route' => 'SittingInvoice.bill-designs',
                    ],
                    [
                        'label' => __('sales_settings.nav.sequence'),
                        'hint' => __('sales_settings.nav.sequence_hint'),
                        'icon' => 'hash',
                        'route' => 'SequenceNumbering.index',
                        'params' => ['section' => 'invoice'],
                    ],
                    [
                        'label' => __('sales_settings.nav.electronic'),
                        'hint' => __('sales_settings.nav.electronic_hint'),
                        'icon' => 'file-plus',
                        'route' => 'settings.electronic_invoice',
                    ],
                ],
            ],

            __('sales_settings.nav.groups.orders') => [
                'icon' => 'truck',
                'items' => [
                    [
                        'label' => __('sales_settings.nav.order_sources'),
                        'hint' => __('sales_settings.nav.order_sources_hint'),
                        'icon' => 'git-merge',
                        'route' => 'order_sources.index',
                    ],
                    [
                        'label' => __('sales_settings.nav.shipping'),
                        'hint' => __('sales_settings.nav.shipping_hint'),
                        'icon' => 'truck',
                        'route' => 'shippingOptions.index',
                    ],
                    /* «إدارة التبديلات» نُقلت من الشريط الجانبي إلى هنا:
                       شاشةُ تشغيلٍ تُدار من لوحة الإعدادات مع أسباب
                       التبديل، فلا يتفرّق ما يخصّ التبديل على موضعين. */
                    [
                        'label' => __('sales_exchanges.title'),
                        'hint' => __('sales_exchanges.subtitle'),
                        'icon' => 'refresh-cw',
                        'route' => 'product-exchanges.index',
                        'permission' => 'sales.exchanges.view',
                    ],
                    [
                        'label' => __('sales_settings.nav.exchange_reasons'),
                        'hint' => __('sales_settings.nav.exchange_reasons_hint'),
                        'icon' => 'repeat',
                        'route' => 'exchange-reasons.index',
                    ],
                ],
            ],

            __('sales_settings.nav.groups.offers') => [
                'icon' => 'tag',
                'items' => [
                    [
                        'label' => __('sales_settings.nav.gift_offers'),
                        'hint' => __('sales_settings.nav.gift_offers_hint'),
                        'icon' => 'gift',
                        'route' => 'gift-offers.index',
                    ],
                    [
                        'label' => __('sales_settings.nav.discount_offers'),
                        'hint' => __('sales_settings.nav.discount_offers_hint'),
                        'icon' => 'percent',
                        'route' => 'offers.index',
                    ],
                ],
            ],

            __('sales_settings.nav.groups.targets') => [
                'icon' => 'target',
                'items' => [
                    /* شاشتا الأهداف نُقلتا من الشريط الجانبي إلى هنا، إلى
                       جانب إعداداتهما — فما يُدار وما يُضبَط في مكانٍ واحد. */
                    [
                        'label' => __('sales_targets.sales.title'),
                        'hint' => __('sales_targets.sales.subtitle'),
                        'icon' => 'target',
                        'route' => 'sales-targets.index',
                        'permission' => 'sales.targets.manage',
                    ],
                    [
                        'label' => __('sales_targets.collections.title'),
                        'hint' => __('sales_targets.collections.subtitle'),
                        'icon' => 'trending-up',
                        'route' => 'collections-targets.index',
                        'permission' => 'sales.targets.manage',
                    ],
                    [
                        'label' => __('sales_settings.nav.targets'),
                        'hint' => __('sales_settings.nav.targets_hint'),
                        'icon' => 'target',
                        'route' => 'sales-targets.settings',
                        'children' => [
                            ['label' => __('sales_settings.nav.targets_sales'), 'route' => 'sales-targets.settings', 'params' => ['type' => 'sales']],
                            ['label' => __('sales_settings.nav.targets_collections'), 'route' => 'sales-targets.settings', 'params' => ['type' => 'collections']],
                        ],
                    ],
                ],
            ],

            /* أُسقطت من هذه اللوحة ثلاث مجموعات بطلب المستخدم:
               «إعدادات عامّة مرتبطة» و«مظهر النظام ولغاته» و«الأثر
               والسجلّات».

               ولم تكن شاشاتٍ لهذه الوحدة أصلاً: طرقُ الدفع والضرائبُ
               والعملاتُ والحساباتُ والفتراتُ والإشعاراتُ واللغاتُ
               والسجلّاتُ كلُّها في وحدة الإعدادات، وكانت تُعرَض هنا
               اختصاراً للرحلة. ومساراتُها على حالها تُفتح من لوحة
               الإعدادات — الإسقاطُ من هذه اللوحة لا من النظام.

               وشريط `sales::components.system-context` يبقى في شاشات
               المبيعات يدلّ على تلك الشاشات عند الحاجة إليها. */
        ];
    }

    /**
     * الأقسام بعد إسقاط ما لا يملكه المستخدم أو ما لا وجود لمساره.
     *
     * فحص وجود المسار مقصود: وحدة معطَّلة في `modules_statuses.json` تُسقط
     * مساراتها، فيصير `route()` استثناءً يكسر اللوحة كلّها. وهذا السجلّ يشير
     * إلى وحدة الإعدادات في قسمه الأخير، فالاحتمال قائم لا نظريّ.
     *
     * @return array<string, array{icon:string,items:array<int, array<string, mixed>>}>
     */
    public static function visible(): array
    {
        $groups = [];

        foreach (static::groups() as $name => $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if (! static::allows($item)) {
                    continue;
                }

                $item['url'] = route($item['route'], $item['params'] ?? []);
                $item['children'] = array_values(array_filter(
                    array_map(function (array $child) {
                        return static::allows($child)
                            ? $child + ['url' => route($child['route'], $child['params'] ?? [])]
                            : null;
                    }, $item['children'] ?? [])
                ));

                $items[] = $item;
            }

            if ($items !== []) {
                $groups[$name] = ['icon' => $group['icon'], 'items' => $items];
            }
        }

        return $groups;
    }

    /** @param  array<string, mixed>  $item */
    protected static function allows(array $item): bool
    {
        if (! Route::has($item['route'])) {
            return false;
        }

        if (isset($item['permission']) && ! hasPermission($item['permission'])) {
            return false;
        }

        return true;
    }

    /** عدد الشاشات المتاحة للمستخدم الحالي. */
    public static function count(): int
    {
        $total = 0;

        foreach (static::visible() as $group) {
            foreach ($group['items'] as $item) {
                $total += 1 + count($item['children'] ?? []);
            }
        }

        return $total;
    }
}
