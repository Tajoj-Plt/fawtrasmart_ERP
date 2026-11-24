<?php

namespace App\Helpers;

use App\Models\TaskSettings;
use App\Support\TaskManager\RequestMemo;
use App\Support\TaskManager\TaskVocabulary;
use Illuminate\Support\Facades\Cache;

class TaskSettingsHelper
{
    /**
     * Get setting with fallback to global and default values
     */
    public static function getSetting($projectId = null, $key = null, $default = null)
    {
        // مرّةً لكل (مشروع، مفتاح) في الطلب: اللوحة تسأل عن الحالات
        // والأولويات والأنواع مراراً، وكل سؤالٍ استعلامان
        $found = RequestMemo::remember('setting.'.($projectId ?: 0).'.'.$key, fn () => self::lookup($projectId, $key));

        return $found ?? $default;
    }

    private static function lookup($projectId, $key)
    {
        $default = null;

        // If no project ID, get global setting
        if (! $projectId) {
            $setting = TaskSettings::where('is_global', true)
                ->where('setting_key', $key)
                ->first();

            return $setting ? $setting->setting_value : $default;
        }

        // Try project-specific setting first
        $setting = TaskSettings::where('project_id', $projectId)
            ->where('setting_key', $key)
            ->where('is_global', false)
            ->first();

        if ($setting) {
            return $setting->setting_value;
        }

        // Try global setting if no project setting
        $globalSetting = TaskSettings::where('is_global', true)
            ->where('setting_key', $key)
            ->first();

        if ($globalSetting) {
            return $globalSetting->setting_value;
        }

        return $default;
    }

    /**
     * Get project settings with global fallback
     */
    public static function getProjectSettings($projectId = null)
    {
        if (! $projectId) {
            return TaskSettings::where('is_global', true)->get();
        }

        return TaskSettings::where(function ($query) use ($projectId) {
            $query->where('project_id', $projectId)
                ->where('is_global', false);
        })->orWhere(function ($query) {
            $query->where('is_global', true)
                ->whereNull('project_id');
        })->get();
    }

    /**
     * Apply settings across all task modules
     */
    public static function applySettingsToModules($projectId)
    {
        // من الكاش وحده. كان يسأل القاعدة ثم يكتب المجموعة كاملةً في الجلسة
        // وفي الكاش عند **كل طلب** (من `EnsureProjectSettings`) — ولا أحد
        // يقرأ نسخة الجلسة
        return self::getCachedSettings($projectId);
    }

    /**
     * Get cached settings for a project
     */
    public static function getCachedSettings($projectId)
    {
        return Cache::remember("task_settings_{$projectId}", now()->addHours(1), function () use ($projectId) {
            return self::getProjectSettings($projectId);
        });
    }

    /**
     * Get global statuses
     */
    public static function getGlobalStatuses()
    {
        return Cache::remember('global_statuses', now()->addHours(1), function () {
            return TaskVocabulary::merge('statuses', TaskSettings::where('setting_key', 'statuses')
                ->where('is_global', true)
                ->whereNull('project_id')
                ->value('setting_value'));
        });
    }

    /**
     * Get global task types
     */
    public static function getGlobalTypes()
    {
        return Cache::remember('global_types', now()->addHours(1), function () {
            return TaskVocabulary::merge('types', TaskSettings::where('setting_key', 'types')
                ->where('is_global', true)
                ->whereNull('project_id')
                ->value('setting_value'));
        });
    }

    /**
     * Get global priorities
     */
    public static function getGlobalPriorities()
    {
        return Cache::remember('global_priorities', now()->addHours(1), function () {
            return TaskVocabulary::merge('priorities', TaskSettings::where('setting_key', 'priorities')
                ->where('is_global', true)
                ->whereNull('project_id')
                ->value('setting_value'));
        });
    }

    /**
     * Get priorities for a project
     */
    public static function getPriorities($projectId = null)
    {
        return TaskVocabulary::merge('priorities', self::getSetting($projectId, 'priorities'));
    }

    /**
     * Get statuses for a project
     */
    public static function getStatuses($projectId = null)
    {
        return TaskVocabulary::merge('statuses', self::getSetting($projectId, 'statuses'));
    }

    /**
     * Get task types for a project
     */
    public static function getTaskTypes($projectId = null)
    {
        // الإعدادات العامّة تحفظه باسم `types` وإعدادات المشروع باسم `task_types`
        return TaskVocabulary::merge('types', self::getSetting($projectId, 'task_types') ?? self::getSetting(null, 'types'));
    }

    /**
     * Get custom fields for a project
     */
    public static function getCustomFields($projectId)
    {
        return self::getSetting($projectId, 'custom_fields', []);
    }

    /**
     * Get workflows for a project
     */
    public static function getWorkflows($projectId)
    {
        return self::getSetting($projectId, 'workflows', [
            [
                'id' => 'default',
                'name' => 'سير العمل الافتراضي',
                'transitions' => [
                    ['from' => 'todo', 'to' => 'in_progress'],
                    ['from' => 'in_progress', 'to' => 'in_review'],
                    ['from' => 'in_review', 'to' => 'done'],
                    ['from' => 'in_review', 'to' => 'in_progress'],
                    ['from' => 'any', 'to' => 'blocked'],
                ],
            ],
        ]);
    }

    /**
     * Get notification settings for a project
     */
    public static function getNotificationSettings($projectId)
    {
        return self::getSetting($projectId, 'notifications', [
            'task_created' => true,
            'task_assigned' => true,
            'task_updated' => true,
            'comment_added' => true,
            'status_changed' => true,
            'deadline_approaching' => true,
        ]);
    }

    /**
     * Get permission settings for a project
     */
    public static function getPermissionSettings($projectId)
    {
        return self::getSetting($projectId, 'permissions', [
            'roles' => [
                [
                    'id' => 'admin',
                    'name' => 'مدير المشروع',
                    'permissions' => [
                        'view_tasks' => true,
                        'create_tasks' => true,
                        'edit_tasks' => true,
                        'delete_tasks' => true,
                        'assign_tasks' => true,
                        'change_status' => true,
                    ],
                ],
                [
                    'id' => 'member',
                    'name' => 'عضو الفريق',
                    'permissions' => [
                        'view_tasks' => true,
                        'create_tasks' => true,
                        'edit_tasks' => false,
                        'delete_tasks' => false,
                        'assign_tasks' => false,
                        'change_status' => false,
                    ],
                ],
                [
                    'id' => 'observer',
                    'name' => 'مراقب',
                    'permissions' => [
                        'view_tasks' => true,
                        'create_tasks' => false,
                        'edit_tasks' => false,
                        'delete_tasks' => false,
                        'assign_tasks' => false,
                        'change_status' => false,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get reporting settings for a project
     */
    public static function getReportingSettings($projectId)
    {
        return self::getSetting($projectId, 'reporting', [
            'enabled' => true,
            'auto_reports' => true,
            'report_frequency' => 'weekly',
            'report_recipients' => [],
        ]);
    }

    /**
     * Get integration settings for a project
     */
    public static function getIntegrationSettings($projectId)
    {
        return self::getSetting($projectId, 'integrations', [
            'slack' => ['enabled' => false],
            'email' => ['enabled' => true],
            'github' => ['enabled' => false],
            'gitlab' => ['enabled' => false],
        ]);
    }

    /**
     * Get a specific setting value by key
     */
    public static function getSettingValue($projectId, $key, $default = null)
    {
        $settings = self::getCachedSettings($projectId);

        // Find the setting by key
        foreach ($settings as $setting) {
            if ($setting->setting_key === $key) {
                return $setting->setting_value;
            }
        }

        // If not found and default provided, create it
        if ($default !== null) {
            TaskSettings::setSetting($projectId, $key, $default);

            return $default;
        }

        return $default;
    }

    /**
     * Check if a user has a specific permission
     */
    public static function userHasPermission($projectId, $userId, $permission)
    {
        $permissions = self::getPermissionSettings($projectId);

        // Get user role (this would need to be implemented based on your user/role system)
        $userRole = 'member'; // Default role

        // Find role permissions
        foreach ($permissions['roles'] as $role) {
            if ($role['id'] === $userRole) {
                return isset($role['permissions'][$permission]) ? $role['permissions'][$permission] : false;
            }
        }

        return false;
    }

    /**
     * Get all settings as an associative array
     */
    public static function getAllSettingsAsArray($projectId)
    {
        $settings = self::getCachedSettings($projectId);
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->setting_key] = $setting->setting_value;
        }

        return $result;
    }
}
