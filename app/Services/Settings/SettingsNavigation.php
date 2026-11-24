<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\Route;

/**
 * سجلّ شاشات الإعدادات — مصدر واحد للقائمة الجانبية وللوحة الإعدادات.
 *
 * كانت القائمة الجانبية مكتوبةً يدوياً في قالبها، ولوحة الإعدادات مكتوبةً
 * يدوياً في قالبها الآخر، فتباعدتا: القائمة فيها 27 شاشة واللوحة 11،
 * ورابط النسخ الاحتياطي في اللوحة ما زال يشير إلى `AccountInfo.index`
 * القديم. والأسوأ أن شاشة **إدارة التطبيقات** لم تكن في أيٍّ منهما —
 * موجودة ولها مسار ولا يُوصَل إليها إلا بكتابة الرابط.
 *
 * الآن: من يضيف شاشة يضيف سطراً هنا، فتظهر في الموضعين معاً أو لا تظهر
 * في أيّهما — ولا تظهر في أحدهما دون الآخر.
 *
 * `permission` تُخفي البند عمّن لا يملكها. وهي للعرض فقط — المنع الحقيقي
 * في المتحكّمات، فإخفاء رابط ليس حمايةً.
 *
 * **وبندٌ بلا `permission` يظهر للجميع.** كان ذلك حال اثنتين وعشرين شاشةً هنا
 * (إعدادات الحساب والمظهر والتطبيقات والضرائب وطرق الدفع والعملات والترقيم
 * وقوالب المستندات و«التكامل» كلِّه وسجلّات الأمان، وابنَي النسخ الاحتياطي)
 * بينما يفحص متحكّمُ كلٍّ منها صلاحيةً فعليّة — فكان المندوبُ يرى الشاشةَ في
 * القائمة ثم يُردّ بـ403 عند النقر. والقاعدة: **اسم `permission` هنا هو نفسُ
 * الاسم الذي يفحصه المتحكّم**، لا اسمٌ يشبهه ولا فراغ.
 *
 * والأبناء (`children`) يُفحَصون منفردين في `allows()` — لا يرثون صلاحية أبيهم،
 * فابنٌ بلا `permission` مفتوحٌ للجميع وإن كان أبوه محروساً.
 */
class SettingsNavigation
{
    /**
     * @return array<string, array{icon:string,items:array<int, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        return [
            __('settings.nav.groups.account') => [
                'icon' => 'award',
                'items' => [
                    [
                        'label' => __('settings.nav.subscription'),
                        'hint' => __('settings.nav.subscription_hint'),
                        'icon' => 'award',
                        'route' => 'Subscription.index',
                        'permission' => 'settings.subscription.view',
                    ],
                    [
                        'label' => __('settings.nav.account'),
                        'hint' => __('settings.nav.account_hint'),
                        'icon' => 'user',
                        'route' => 'SittingAccount.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.appearance'),
                        'hint' => __('settings.nav.appearance_hint'),
                        'icon' => 'droplet',
                        'route' => 'Appearance.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.applications'),
                        'hint' => __('settings.nav.applications_hint'),
                        'icon' => 'grid',
                        'route' => 'Application.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                ],
            ],

            __('settings.nav.groups.finance') => [
                'icon' => 'dollar-sign',
                'items' => [
                    [
                        'label' => __('settings.nav.taxes'),
                        'hint' => __('settings.nav.taxes_hint'),
                        'icon' => 'percent',
                        'route' => 'TaxSitting.index',
                        'permission' => 'settings_edit_tax_settings',
                    ],
                    [
                        'label' => __('settings.nav.payments'),
                        'hint' => __('settings.nav.payments_hint'),
                        'icon' => 'credit-card',
                        'route' => 'PaymentMethods.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.currencies'),
                        'hint' => __('settings.nav.currencies_hint'),
                        'icon' => 'dollar-sign',
                        'route' => 'SittingAccount.currency',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.sequence'),
                        'hint' => __('settings.nav.sequence_hint'),
                        'icon' => 'hash',
                        'route' => 'SequenceNumbering.index',
                        'permission' => 'settings_edit_general_settings',
                        'params' => ['section' => 'invoice'],
                    ],
                    [
                        'label' => __('settings.nav.accounting_periods'),
                        'hint' => __('settings.nav.accounting_periods_hint'),
                        'icon' => 'calendar',
                        'route' => 'AccountingPeriods.index',
                        'permission' => 'settings.accounting_periods.view',
                    ],
                    [
                        'label' => __('settings.nav.accounting_settings'),
                        'hint' => __('settings.nav.accounting_settings_hint'),
                        'icon' => 'git-branch',
                        'route' => 'AccountingSettings.index',
                        'permission' => 'settings.accounting_settings.view',
                    ],
                ],
            ],

            __('settings.nav.groups.language') => [
                'icon' => 'globe',
                'items' => [
                    [
                        'label' => __('settings.nav.languages'),
                        'hint' => __('settings.nav.languages_hint'),
                        'icon' => 'globe',
                        'route' => 'SittingAccount.language',
                        'gate' => 'languages',
                    ],
                    [
                        'label' => __('settings.nav.documents'),
                        'hint' => __('settings.nav.documents_hint'),
                        'icon' => 'file-text',
                        'route' => 'document-templates.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                ],
            ],

            /*
                إعدادات الوحدات التشغيلية — قسمٌ يجمع ما لكل وحدةٍ من
                إعداداتٍ عامّة تتشاركها مشاريعُها أو مستنداتُها كلّها، مقابلَ
                الإعدادات الخاصّة بمستندٍ واحد أو مشروعٍ واحد التي تُدار من
                داخله.

                أُنشئ لإدارة المهام: كانت إعداداتها لا تُفتح إلا من داخل
                مشروع (`projects/{id}/settings`)، فالحالات والأنواع
                والأولويات — وهي بياناتٌ مرجعية تتشاركها المشاريع كلّها —
                بلا موضعٍ تُدار منه أصلاً.
            */
            'الوحدات التشغيلية' => [
                'icon' => 'grid',
                'items' => [
                    [
                        'label' => 'إعدادات المهام',
                        'hint' => 'حالات المهام وأنواعها وأولوياتها وتنبيهاتها الافتراضية لكل المشاريع',
                        'icon' => 'check-square',
                        'route' => 'taskmanager.global-settings.index',
                        'permission' => 'tasks.settings.global.view',
                    ],
                ],
            ],

            __('settings.nav.groups.integration') => [
                'icon' => 'link',
                'items' => [
                    [
                        'label' => __('settings.nav.smtp'),
                        'hint' => __('settings.nav.smtp_hint'),
                        'icon' => 'mail',
                        'route' => 'SMPT.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.sms'),
                        'hint' => __('settings.nav.sms_hint'),
                        'icon' => 'message-square',
                        'route' => 'Sms.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.sms_templates'),
                        'hint' => __('settings.nav.sms_templates_hint'),
                        'icon' => 'edit-3',
                        'route' => 'SmsTemplates.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.notifications'),
                        'hint' => __('settings.nav.notifications_hint'),
                        'icon' => 'bell',
                        'route' => 'NotificationSettings.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.telegram'),
                        'hint' => __('settings.nav.telegram_hint'),
                        'icon' => 'send',
                        'route' => 'Telegram.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        // الشاشةُ كانت قائمةً بمسارها وصلاحيتها ولا رابطَ إليها من أيّ مكان
                        'label' => __('settings.nav.api_keys'),
                        'hint' => __('settings.nav.api_keys_hint'),
                        'icon' => 'key',
                        'route' => 'apikeys.index',
                        'permission' => 'api_keys.view',
                    ],
                ],
            ],

            'المستخدمون والصلاحيات' => [
                'icon' => 'users',
                'items' => [
                    [
                        'label' => 'المستخدمون',
                        'hint' => 'إنشاء المستخدمين وتعيين أدوارهم وفروعهم',
                        'icon' => 'user',
                        'route' => 'settings.users.index',
                        'permission' => 'settings.users.view',
                    ],
                    [
                        'label' => 'الأدوار',
                        'hint' => 'الأدوار وما يُسنَد إليها من صلاحيات',
                        'icon' => 'briefcase',
                        'route' => 'settings.roles.index',
                        'permission' => 'settings.roles.view',
                    ],
                    [
                        'label' => 'الصلاحيات',
                        'hint' => 'تصفّح صلاحيات النظام وإدارتها',
                        'icon' => 'shield',
                        'route' => 'settings.permissions.index',
                        'permission' => 'settings.permissions.view',
                    ],
                ],
            ],

            __('settings.nav.groups.security') => [
                'icon' => 'shield',
                'items' => [
                    [
                        'label' => __('settings.nav.activity_log'),
                        'hint' => __('settings.nav.activity_log_hint'),
                        'icon' => 'activity',
                        'route' => 'ActivityLog.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.database_sizes'),
                        'hint' => __('settings.nav.database_sizes_hint'),
                        'icon' => 'database',
                        'route' => 'DatabaseSizes.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.event_log'),
                        'hint' => __('settings.nav.event_log_hint'),
                        'icon' => 'alert-circle',
                        'route' => 'EventLogs.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.sessions'),
                        'hint' => __('settings.nav.sessions_hint'),
                        'icon' => 'monitor',
                        'route' => 'UserSessions.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.session_log'),
                        'hint' => __('settings.nav.session_log_hint'),
                        'icon' => 'log-in',
                        'route' => 'SessionLogs.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.error_log'),
                        'hint' => __('settings.nav.error_log_hint'),
                        'icon' => 'alert-triangle',
                        'route' => 'ErrorLogs.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                    [
                        'label' => __('settings.nav.sms_log'),
                        'hint' => __('settings.nav.sms_log_hint'),
                        'icon' => 'message-circle',
                        'route' => 'SmsLogs.index',
                        'permission' => 'settings_edit_general_settings',
                    ],
                ],
            ],

            __('settings.nav.groups.maintenance') => [
                'icon' => 'tool',
                'items' => [
                    [
                        'label' => __('settings.nav.system_update'),
                        'hint' => __('settings.nav.system_update_hint'),
                        'icon' => 'download-cloud',
                        'route' => 'SystemUpdate.index',
                        /*
                            [إقفال-تحديث-النظام] حرّاسُ `SystemUpdateController` ما زالت
                            معلّقةً بـ`//` — الشاشةُ نفسُها مفتوحة بقرارٍ سابق، ولم أمسسه.

                            لكنّ **الرابط** يُحرَس هنا: بلا حارسٍ كان بندُ «تحديث النظام»
                            هو البندَ الوحيد الذي يراه مندوبُ المبيعات في «الإعدادات»،
                            فتُفتح له المجموعةُ كلُّها لأجله وهو لا يملك فيها شيئاً.
                            ولإعادة فتحه للجميع يُعلَّق هذا السطر وحدَه.
                        */
                        'permission' => 'settings.system_update.view',
                    ],
                    [
                        'label' => 'وضع الصيانة',
                        'hint' => 'إقفال النظام على الجميع إلا مستخدمين محدَّدين — قبل الاسترجاع أو الترحيل أو تصحيح البيانات.',
                        'icon' => 'lock',
                        'route' => 'MaintenanceMode.index',
                        'permission' => 'settings.maintenance_mode.view',
                    ],
                    [
                        'label' => __('settings.nav.cache'),
                        'hint' => __('settings.nav.cache_hint'),
                        'icon' => 'refresh-cw',
                        'route' => 'SettingsCache.index',
                        'permission' => 'settings.cache.view',
                    ],
                    [
                        'label' => __('settings.nav.cron'),
                        'hint' => __('settings.nav.cron_hint'),
                        'icon' => 'clock',
                        'route' => 'CronJobs.index',
                        'permission' => 'settings.cron.view',
                        'children' => [
                            ['label' => __('settings.nav.cron_log'), 'route' => 'CronJobs.executions', 'permission' => 'settings.cron.logs'],
                        ],
                    ],
                    [
                        'label' => __('settings.nav.backup'),
                        'hint' => __('settings.nav.backup_hint'),
                        'icon' => 'database',
                        'route' => 'Backup.index',
                        'permission' => 'settings.backup.view',
                        'children' => [
                            ['label' => __('settings.nav.backup_history'), 'route' => 'Backup.history', 'permission' => 'settings.backup.view'],
                            ['label' => __('settings.nav.backup_restores'), 'route' => 'Backup.restores', 'permission' => 'settings.backup.view'],
                            ['label' => __('settings.nav.backup_settings'), 'route' => 'Backup.settings', 'permission' => 'settings.backup.settings'],
                        ],
                    ],
                    [
                        'label' => __('settings.nav.export'),
                        'hint' => __('settings.nav.export_hint'),
                        'icon' => 'download',
                        'route' => 'DataExport.index',
                        'permission' => 'settings.backup.export',
                    ],
                ],
            ],
        ];
    }

    /**
     * الأقسام بعد إسقاط ما لا يملكه المستخدم أو ما لا وجود لمساره.
     *
     * فحص وجود المسار مقصود: وحدة معطَّلة في `modules_statuses.json` تُسقط
     * مساراتها، فيصير `route()` استثناءً يكسر الصفحة كلّها. إسقاط البند
     * أهون من شاشة إعدادات لا تُفتح.
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

        // بوابة خاصّة بمساعد اللغات — دالّة عامّة قائمة في المشروع
        if (($item['gate'] ?? null) === 'languages' && function_exists('canLanguages') && ! canLanguages('view')) {
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
