<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات النظام البيئي للنقاط — المصدر الوحيد لأسمائها.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 * **لا صلاحيةَ واحدة.** عشرون متحكّماً — نقاطٌ ومستويات ومكافآت واستبدالٌ
 * وإنجازاتٌ وتحدّياتٌ وإحالاتٌ وتقارير — ومساراتُها محروسةٌ بـ`auth` وحدَه.
 * وبطاقتُها في لوحة النظام كانت **بلا حارسٍ إطلاقاً**: يراها كلُّ من يسجّل
 * دخوله ويفتح منها ضبطَ قواعد النقاط وتعديلَ أرصدة العملاء.
 *
 * وهي الحالُ نفسُها التي كانت عليها الحجوزات قبل كتالوجها.
 *
 * ─────────────────────── مبدأ التقسيم ───────────────────────
 *
 * **ما يُغيّر رصيدَ عميلٍ يُفصل عمّا يقرؤه، وما يضبط القواعد يُفرد.**
 *
 *   • `ecosystem.view` بابُ الوحدة — لوحتُها وحدها. وهي التي تحرس البطاقة
 *     في «لوحة النظام»: بطاقةٌ بلا صلاحيةٍ تُخالف قاعدةَ النظام
 *     («لا بطاقة تظهر إلا بصلاحية»).
 *
 *   • `ecosystem.points.adjust` تعديلُ أرصدة النقاط يدوياً — أخطرُ ما في
 *     الوحدة: نقطةٌ تُمنح تُستبدَل مالاً أو خصماً، فلها صلاحيتُها وحدها
 *     مفصولةً عن العرض.
 *
 *   • `ecosystem.redemptions.approve` اعتمادُ الاستبدال — يُخرج قيمةً
 *     فعلية (نقداً أو قسيمةً أو منتجاً)، فلا يُلحق بـ`view`.
 *
 *   • `ecosystem.settings` قواعدُ الكسب والانتهاء والمستويات — تُعاد بها
 *     كتابةُ اقتصاد النقاط كلِّه، وتمسّ كلَّ عميلٍ بأثرٍ لاحق.
 *
 * ─────────────────────── ما لا يُحرَس هنا ───────────────────────
 *
 * **حراسةُ المسارات لم تُركَّب بعد** — هذا الكتالوج يعرّف المفردات ويحرس
 * البطاقةَ والقائمة. وتركيبُ `permission:` على مسارات المتحكّمات العشرين
 * عملٌ تالٍ يلزمه مراجعةُ كلّ شاشة، وتركُه معلوماً خيرٌ من تركيبٍ عجولٍ
 * يقفل شاشاتٍ في وجه من يعمل بها اليوم.
 *
 * @see \App\Constants\permissions\ReservationsPermissions النظير الذي بُني عليه
 */
class EcosystemPermissions extends BasePermissionCatalog
{
    /** بادئة أسماء صلاحيات هذه الوحدة. */
    public const PREFIX = 'ecosystem.';

    public static function module(): array
    {
        return [
            'key' => 'ecosystem',
            'label' => 'النظام البيئي للنقاط',
            'description' => 'النقاط والمستويات والمكافآت والإنجازات والتحدّيات والإحالات.',
            'icon' => 'star',
            'order' => 27,
        ];
    }

    public static function groups(): array
    {
        return [
            'ecosystem' => [
                'label' => 'النظام البيئي',
                'description' => 'بابُ الوحدة ولوحتها — وبها تُحرَس بطاقتها في لوحة النظام.',
                'icon' => 'star',
                'controller' => 'Ecosystem/EcosystemController',
                'permissions' => [
                    'ecosystem.view' => 'عرض النظام البيئي للنقاط ولوحته',
                ],
            ],

            'points' => [
                'label' => 'النقاط والأرصدة',
                'description' => 'أرصدة العملاء وحركاتها — والتعديل اليدوي مفصولٌ عن العرض.',
                'icon' => 'award',
                'controller' => 'Ecosystem/PointsTransactionController، PointsUsersController، PointsBatchController',
                'permissions' => [
                    'ecosystem.points.view' => 'عرض أرصدة النقاط وحركاتها',
                    'ecosystem.points.adjust' => 'تعديل أرصدة النقاط يدوياً',
                    'ecosystem.points.batch' => 'منح النقاط بالجملة',
                ],
            ],

            'tiers' => [
                'label' => 'المستويات',
                'description' => 'مستويات العملاء وشروط الترقية وسجلّها.',
                'icon' => 'trending-up',
                'controller' => 'Ecosystem/PointsTierController، TierUpgradeLogController',
                'permissions' => [
                    'ecosystem.tiers.view' => 'عرض المستويات وسجلّ الترقيات',
                    'ecosystem.tiers.manage' => 'إدارة المستويات وشروطها',
                ],
            ],

            'rewards' => [
                'label' => 'المكافآت والاستبدال',
                'description' => 'كتالوج المكافآت وطلبات الاستبدال — والاعتماد يُخرج قيمةً فعلية.',
                'icon' => 'gift',
                'controller' => 'Ecosystem/RewardController، RewardRedemptionController',
                'permissions' => [
                    'ecosystem.rewards.view' => 'عرض المكافآت وطلبات الاستبدال',
                    'ecosystem.rewards.manage' => 'إدارة كتالوج المكافآت',
                    'ecosystem.redemptions.approve' => 'اعتماد طلبات الاستبدال ورفضها',
                ],
            ],

            'engagement' => [
                'label' => 'الإنجازات والتحدّيات والإحالات',
                'description' => 'برامج التحفيز وفرقها وسجلّ تحقيقها.',
                'icon' => 'target',
                'controller' => 'Ecosystem/AchievementController، ChallengeController، ReferralController',
                'permissions' => [
                    'ecosystem.engagement.view' => 'عرض الإنجازات والتحدّيات والإحالات',
                    'ecosystem.engagement.manage' => 'إدارة الإنجازات والتحدّيات والإحالات',
                ],
            ],

            'settings' => [
                'label' => 'إعدادات النقاط',
                'description' => 'قواعد الكسب والانتهاء والإشعارات — تُعاد بها كتابة اقتصاد النقاط.',
                'icon' => 'settings',
                'controller' => 'Ecosystem/PointsGeneralSettingController، PointsSettingController، PointsExpirySettingController',
                'permissions' => [
                    'ecosystem.settings' => 'ضبط قواعد النقاط والانتهاء والإشعارات',
                ],
            ],

            'reports' => [
                'label' => 'تقارير النظام البيئي',
                'description' => 'تقارير النقاط والمستويات والمكافآت والإحالات وتصديرها.',
                'icon' => 'bar-chart-2',
                'controller' => 'Ecosystem/PointsReportController',
                'permissions' => [
                    'ecosystem.reports.view' => 'عرض تقارير النظام البيئي',
                    'ecosystem.reports.export' => 'تصدير تقارير النظام البيئي',
                ],
            ],
        ];
    }
}
