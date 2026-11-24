<?php

namespace Modules\Purchases\Services;

use Illuminate\Support\Facades\Route;

/**
 * روابط الشاشات التي تضبط المشتريات ولا تسكن شاشة خياراتها.
 *
 * ترقيم مستندات الشراء وقوالب طباعتها ونِسَب ضريبتها تعيش في وحدة الإعدادات
 * ولا مفرّ — فهي جداول مشتركة بين الأقسام كلّها. لكن من يضبط سلوك المشتريات
 * كان يخرج من شاشته ليبحث عنها هناك بلا دليل. هذه الروابط تصل بينهما.
 *
 * `permission` تُخفي البند عمّن لا يملكها، وهي للعرض فقط — المنع الحقيقي
 * في المتحكّمات، فإخفاء رابط ليس حمايةً.
 *
 * @see PurchaseSettings الخيارات نفسها
 */
class PurchaseSettingsNavigation
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function links(): array
    {
        return [
            [
                'label' => __('purchase_settings.links.templates'),
                'hint' => __('purchase_settings.links.templates_hint'),
                'icon' => 'fa-stamp',
                'route' => 'document-templates.index',
            ],
            [
                'label' => __('purchase_settings.links.designs'),
                'hint' => __('purchase_settings.links.designs_hint'),
                'icon' => 'fa-paint-roller',
                'route' => 'purchases.invoice_settings.bill_designs',
            ],
            [
                'label' => __('purchase_settings.links.sequence'),
                'hint' => __('purchase_settings.links.sequence_hint'),
                'icon' => 'fa-hashtag',
                'route' => 'SequenceNumbering.index',
                'params' => ['section' => 'purchase-invoice'],
            ],
            [
                'label' => __('purchase_settings.links.taxes'),
                'hint' => __('purchase_settings.links.taxes_hint'),
                'icon' => 'fa-percent',
                'route' => 'TaxSitting.index',
            ],
            [
                'label' => __('purchase_settings.links.payments'),
                'hint' => __('purchase_settings.links.payments_hint'),
                'icon' => 'fa-credit-card',
                'route' => 'PaymentMethods.index',
            ],
            [
                'label' => __('purchase_settings.links.accounting'),
                'hint' => __('purchase_settings.links.accounting_hint'),
                'icon' => 'fa-code-branch',
                'route' => 'AccountingSettings.index',
                'permission' => 'settings.accounting_settings.view',
            ],
        ];
    }

    /**
     * الروابط بعد إسقاط ما لا يملكه المستخدم أو ما لا وجود لمساره.
     *
     * فحص وجود المسار مقصود: وحدة الإعدادات معطَّلةً في `modules_statuses.json`
     * تُسقط مساراتها، فيصير `route()` استثناءً يكسر شاشة الخيارات كلّها —
     * وهي تشير إلى وحدة غير وحدتها.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function visible(): array
    {
        $links = [];

        foreach (static::links() as $link) {
            if (! Route::has($link['route'])) {
                continue;
            }

            if (isset($link['permission']) && ! hasPermission($link['permission'])) {
                continue;
            }

            $link['url'] = route($link['route'], $link['params'] ?? []);
            $links[] = $link;
        }

        return $links;
    }
}
