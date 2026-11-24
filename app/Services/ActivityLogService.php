<?php

namespace App\Services;

use App\Models\Log as ModelsLog;

class ActivityLogService
{
    /**
     * تسجيل نشاط مع تفاصيل كاملة.
     *
     * **ما تغيّر:** كانت الأعمدة `browser` و`os` و`device` و`route`
     * و`old_values` و`new_values` و`ip_address` معطَّلة بالتعليق رغم وجودها
     * في الجدول وفي `$fillable` — فكان كل سجلّ نشاط يُكتب بوصفٍ نصّي فقط،
     * وشاشة تفاصيل السجلّ تعرض خاناتٍ فارغة أبداً. صارت تُملأ فعلاً.
     *
     * `type_log` يبقى `'log'` افتراضاً لأن شاشة «سجلّ النشاطات» تصفّي عليه،
     * فأي قيمة أخرى تُخفي السجلّ عن الشاشة. نوعُ العملية يُميَّز بالأيقونة
     * وبالوصف وبفرق القيم.
     */
    public function log(
        string $action,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $type = 'setting',
        ?int $typeId = null,
        ?string $icon = null,
        string $typeLog = 'log'
    ): ModelsLog {
        $request = request();
        $userAgent = $request->userAgent() ?? '';

        return ModelsLog::create([
            'type' => $type,
            'type_id' => $typeId,
            'type_log' => $typeLog,
            'description' => $description,
            'created_by' => auth()->id(),
            'icon' => $icon ?: $this->iconFor($action),
            'ip_address' => $request->ip(),
            'browser' => $this->parseBrowser($userAgent),
            'os' => $this->parseOS($userAgent),
            'device' => $this->parseDevice($userAgent),
            'route' => $request->route() ? $request->route()->getName() : null,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
        ]);
    }

    /**
     * تسجيل تغيير مع مقارنة القيم القديمة والجديدة تلقائياً
     */
    public function logChanges(
        string $description,
        $model,
        array $newData,
        ?string $type = 'setting'
    ): ?ModelsLog {
        $oldValues = [];
        $newValues = [];

        foreach ($newData as $key => $value) {
            $original = $model->getOriginal($key);
            if ($original != $value) {
                $oldValues[$key] = $original;
                $newValues[$key] = $value;
            }
        }

        if (empty($newValues)) {
            return null;
        }

        return $this->log($type, $description, $oldValues, $newValues, $type, $model->id ?? null);
    }

    /**
     * أيقونة تناسب نوع العملية — تُعرض في شاشة تفاصيل السجلّ وفي الخطّ
     * الزمني داخل المستند. المجهول يأخذ أيقونة النشاط العامّة.
     */
    protected function iconFor(string $action): string
    {
        return match ($action) {
            'created', 'create', 'store' => 'feather icon-plus-circle',
            'updated', 'update', 'edit' => 'feather icon-edit-2',
            'deleted', 'delete', 'destroy' => 'feather icon-trash-2',
            'approved', 'approve' => 'feather icon-check-circle',
            'rejected', 'reject' => 'feather icon-x-circle',
            'restored', 'restore', 'undo' => 'feather icon-rotate-ccw',
            'printed', 'print', 'export' => 'feather icon-printer',
            'viewed', 'view' => 'feather icon-eye',
            'paid', 'payment' => 'feather icon-credit-card',
            'signed', 'sign' => 'feather icon-edit-3',
            default => 'feather icon-activity',
        };
    }

    /**
     * استخراج اسم المتصفح من User-Agent
     */
    protected function parseBrowser(string $userAgent): string
    {
        if (preg_match('/Edg[\/\s]([\d.]+)/i', $userAgent)) {
            return 'Edge';
        }
        if (preg_match('/OPR[\/\s]([\d.]+)/i', $userAgent)) {
            return 'Opera';
        }
        if (preg_match('/Chrome[\/\s]([\d.]+)/i', $userAgent)) {
            return 'Chrome';
        }
        if (preg_match('/Firefox[\/\s]([\d.]+)/i', $userAgent)) {
            return 'Firefox';
        }
        if (preg_match('/Safari[\/\s]([\d.]+)/i', $userAgent)) {
            if (! preg_match('/Chrome/i', $userAgent)) {
                return 'Safari';
            }
        }
        if (preg_match('/MSIE|Trident/i', $userAgent)) {
            return 'Internet Explorer';
        }

        return 'غير معروف';
    }

    /**
     * استخراج نظام التشغيل من User-Agent
     */
    protected function parseOS(string $userAgent): string
    {
        if (preg_match('/Windows NT 10/i', $userAgent)) {
            return 'Windows 10/11';
        }
        if (preg_match('/Windows NT/i', $userAgent)) {
            return 'Windows';
        }
        if (preg_match('/Mac OS X/i', $userAgent)) {
            return 'macOS';
        }
        if (preg_match('/Linux/i', $userAgent)) {
            if (preg_match('/Android/i', $userAgent)) {
                return 'Android';
            }

            return 'Linux';
        }
        if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            return 'iOS';
        }

        return 'غير معروف';
    }

    /**
     * استخراج نوع الجهاز من User-Agent
     */
    protected function parseDevice(string $userAgent): string
    {
        if (preg_match('/Mobile|Android.*Mobile|iPhone/i', $userAgent)) {
            return 'جوال';
        }
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $userAgent)) {
            return 'لوحي';
        }

        return 'كمبيوتر';
    }
}
