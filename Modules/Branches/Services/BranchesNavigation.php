<?php

namespace Modules\Branches\Services;

use App\Constants\planFeatures\BranchesPlanFeatures;
use App\Services\Fawtra\SubscriptionGate;
use App\Support\PlanLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ شاشات موديول الفروع — مصدر واحد للقائمة الجانبية وللوحة الفروع.
 *
 * الموديول كان يستعير قالب الإعدادات (sitting::master) وقائمتَه الجانبية،
 * فيظهر المستخدم داخل «الإعدادات» بينما هو يدير الهيكل التنظيمي، ولا يجد
 * في القائمة أي بند يخصّ الفروع سوى رابطين متناثرين. الآن للموديول قائمته
 * الخاصّة، ومن يضيف شاشة يضيف سطراً هنا فتظهر في القائمة واللوحة معاً.
 *
 * `permission` للعرض فقط — المنع الحقيقي في المتحكّمات، وإخفاء رابط ليس حمايةً.
 */
class BranchesNavigation
{
    /**
     * @return array<string, array{icon: string, items: array<int, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            __('branches.nav.group_structure') => [
                'icon' => 'git-branch',
                'items' => [
                    [
                        'label' => __('branches.nav.tree'),
                        'hint' => __('branches.nav.tree_hint'),
                        'icon' => 'git-branch',
                        'route' => 'branches.index',
                        'permission' => 'branches.view',
                        'plan' => BranchesPlanFeatures::BRANCHES,
                        'limit' => BranchesPlanFeatures::LIMIT_BRANCHES,
                        'color' => 'primary',
                    ],
                    [
                        'label' => __('branches.nav.create'),
                        'hint' => __('branches.nav.create_hint'),
                        'icon' => 'plus-circle',
                        'route' => 'branches.create',
                        'permission' => 'branches.create',
                        'plan' => BranchesPlanFeatures::BRANCHES,
                        'limit' => BranchesPlanFeatures::LIMIT_BRANCHES,
                        'color' => 'success',
                    ],
                ],
            ],

            __('branches.nav.group_merge') => [
                'icon' => 'shuffle',
                'items' => [
                    [
                        'label' => __('branches.nav.bulk_merge'),
                        'hint' => __('branches.nav.bulk_merge_hint'),
                        'icon' => 'minimize-2',
                        'route' => 'groups.bulk_merge_page',
                        'permission' => ['branches.groups.merge', 'branches.groups.view'],
                        'plan' => BranchesPlanFeatures::MERGE,
                        'limit' => BranchesPlanFeatures::LIMIT_TRANSFERS,
                        'color' => 'info',
                    ],
                    [
                        'label' => __('branches.nav.transfer'),
                        'hint' => __('branches.nav.transfer_hint'),
                        'icon' => 'users',
                        'route' => 'groups.merge_select_page',
                        'permission' => ['branches.groups.merge', 'branches.groups.view'],
                        'plan' => BranchesPlanFeatures::MERGE,
                        'limit' => BranchesPlanFeatures::LIMIT_TRANSFERS,
                        'color' => 'warning',
                    ],
                    [
                        'label' => __('branches.nav.logs'),
                        'hint' => __('branches.nav.logs_hint'),
                        'icon' => 'rotate-ccw',
                        'route' => 'groups.merge_logs',
                        'permission' => 'branches.groups.view',
                        'plan' => BranchesPlanFeatures::TERRITORY_GROUPS,
                        'color' => 'danger',
                    ],
                ],
            ],

            __('branches.nav.group_settings') => [
                'icon' => 'sliders',
                'items' => [
                    [
                        'label' => __('branches.nav.settings'),
                        'hint' => __('branches.settings.nav_hint'),
                        'icon' => 'sliders',
                        'route' => 'branches.settings',
                        'permission' => 'branches.settings.view',
                        'plan' => BranchesPlanFeatures::SETTINGS,
                        'color' => 'primary',
                    ],
                    [
                        'label' => __('branches.nav.migrate'),
                        'hint' => __('branches.nav.migrate_hint'),
                        'icon' => 'download-cloud',
                        'route' => 'branches.migrate_region_groubs',
                        'permission' => 'branches.migrate',
                        'plan' => BranchesPlanFeatures::HIERARCHY,
                        'color' => 'danger',
                        'maintenance' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * المجموعات التي يملك المستخدم الحالي الوصول إليها، مع الروابط الجاهزة.
     *
     * @return array<string, array{icon: string, items: array<int, array<string, mixed>>}>
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

                /*
                    **الميزة خارج الباقة تُقفَل ولا تُخفى.**

                    `allows()` أعلاه تُخفي بصلاحية النظام — وذاك صواب: من لا
                    يملك الصلاحية لا شأن له بالشاشة. أمّا ما لم تشمله الباقة
                    فيبقى ظاهراً مقفولاً: إخفاؤه يجعل المنشأة لا تعرف أنه
                    موجود فلا تسأل عنه ولا تشتريه.

                    والمقفول يقود إلى شاشة الاشتراك، لأن شاشته تردّه بـ402
                    على كل حال (وسيط `plan:` على مساره).
                */
                $locked = isset($item['plan']) && PlanLock::locked($item['plan']);

                $item['locked'] = $locked;
                $item['url'] = $locked
                    ? PlanLock::url()
                    : route($item['route'], $item['params'] ?? []);

                if ($locked) {
                    $item['hint'] = PlanLock::reason($item['plan']);
                }

                // شارة الامتلاء — تظهر عند بلوغ الحدّ أو مقاربته، ولا تُخفي
                // البند: من امتلأ سقفُه يحتاج شاشته ليحذف أو ليرى ما عنده
                $item['usage'] = $locked ? null : static::usageBadge($item['limit'] ?? null);

                $items[] = $item;
            }

            if ($items !== []) {
                $groups[$name] = ['icon' => $group['icon'], 'items' => $items];
            }
        }

        return $groups;
    }

    /** @param array<string, mixed> $item */
    protected static function allows(array $item): bool
    {
        if (! Route::has($item['route'])) {
            return false;
        }

        /*
            `permission` نصٌّ = «تكفي واحدة» (`a|b`)، ومصفوفةٌ = «تلزم كلُّها».

            وشاشةٌ تُحرَس مرّتين تحتاج الثانية: `groups.bulk_merge_page` يحرسها
            المسارُ بـ`branches.groups.merge` ويحرسها المتحكّمُ بـ
            `branches.groups.view`، فمن يملك الدمجَ وحده كان يرى البندَ هنا
            ويُردّ بـ403 هناك.
        */
        foreach ((array) ($item['permission'] ?? []) as $permission) {
            if (! hasPermission($permission)) {
                return false;
            }
        }

        // أداة الترحيل تختفي متى أُنجز الترحيل — لا معنى لعرض زر لا يفعل شيئاً.
        if (($item['maintenance'] ?? false) && ! static::migrationPending()) {
            return false;
        }

        return true;
    }

    /**
     * شارةُ حدٍّ لبندٍ في القائمة — أو `null` حين لا حدَّ له أو لم يقاربه.
     *
     * `limit()` لا `liveLimit()`: هذه قراءةٌ تقع مع كل تحميلِ صفحة، وتلك
     * تُطلق `COUNT(*)` لكل ميزة. والفارق بينهما دورةُ مزامنةٍ واحدة — مقبولٌ
     * لتنبيهٍ لا لمنع، والمنعُ يقع في `plan.limit` وفي `PlanLimitObserver`.
     *
     * ولا تظهر قبل ٨٠٪: شارةٌ دائمة يتوقّف النظر عندها فلا تعود تنبيهاً.
     *
     * @return array{text: string, title: string, exhausted: bool}|null
     */
    protected static function usageBadge(?string $feature): ?array
    {
        if ($feature === null) {
            return null;
        }

        try {
            $limit = app(SubscriptionGate::class)->limit($feature);
        } catch (\Throwable) {
            return null;
        }

        if (! $limit['known'] || $limit['unlimited'] || (int) $limit['percent'] < 80) {
            return null;
        }

        return [
            'text' => $limit['exhausted'] ? __('branches.nav.limit_full') : $limit['percent'].'٪',
            'title' => sprintf('%s: %d / %d', $limit['label'], (int) $limit['used'], (int) $limit['allowance']),
            'exhausted' => (bool) $limit['exhausted'],
        ];
    }

    /** هل ما زال جدول region_groubs القديم موجوداً وفيه صفوف؟ */
    public static function migrationPending(): bool
    {
        static $pending = null;

        if ($pending === null) {
            $pending = Schema::hasTable('region_groubs')
                && DB::table('region_groubs')->exists();
        }

        return $pending;
    }

    /** عدد الشاشات المتاحة للمستخدم الحالي. */
    public static function count(): int
    {
        $total = 0;

        foreach (static::visible() as $group) {
            $total += count($group['items']);
        }

        return $total;
    }
}
