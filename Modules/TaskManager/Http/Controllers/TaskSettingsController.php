<?php

namespace Modules\TaskManager\Http\Controllers;

use App\Helpers\TaskSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TaskSettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Modules\TaskManager\Services\ProjectAccess;
use Throwable;

/**
 * إعدادات مشروعٍ واحد — تغلب الإعدادات العامّة ولا تلغيها.
 *
 * ─────────────────────── لماذا أُعيدت كتابته؟ ───────────────────────
 *
 * **كان يكتب في أعمدةٍ لا وجود لها.**
 *
 * النموذج القديم `Modules\TaskManager\Models\TaskSettings` كان يعرّف جدول `task_settings`
 * بأعمدة `team_config` و`sprint_config` و`notification_config` وستّةٍ
 * غيرها. والجدول الحقيقي — من ترحيلة
 * `2025_11_01_144831_create_task_settings_table` وحدها، ولا ترحيلة غيرها
 * تذكر تلك الأعمدة في المشروع كلّه — أعمدتُه:
 * `project_id, setting_key, setting_value, setting_type, description`
 * (وأُضيف `is_global` لاحقاً).
 *
 * فكلّ حفظٍ من هذه الشاشة كان ينتهي باستثناء SQL يُبتلع في
 * `Log::error` ويُعرض للمستخدم «حدث خطأ أثناء حفظ الإعدادات» — الشاشة
 * تعمل، والزرّ يُضغط، ولا يُحفظ شيءٌ أبداً.
 *
 * فصار الحفظ إلى مفاتيح/قيم كما الجدول فعلاً، وهو نفسه المخزن الذي يقرأ
 * منه [\App\Helpers\TaskSettingsHelper] — أي أن ما يُحفظ هنا يُقرأ في
 * بقيّة الوحدة، وهو ما لم يكن يحدث قبلُ.
 *
 * (`Modules\TaskManager\Models\TaskSettings` و`TaskSettingsService` حُذفا
 * لاحقاً: لا مستدعيَ لهما، وأعمدتُهما لا وجود لها.)
 *
 * ─────────────────────── الطبقات الثلاث ───────────────────────
 *
 *   إعداد المشروع (هنا)  ←  الإعداد العامّ  ←  القيمة في الشيفرة
 *
 * وهو ترتيب `TaskSettingsHelper::getSetting()` نفسه. فالمشروع الذي لم
 * يُضبط يرث العامَّ تلقائياً، ولا يحتاج كلُّ مشروعٍ جديد أن يُملأ بيده.
 */
class TaskSettingsController extends Controller
{
    /** وحدة السجلّ — بها يربط `logs`/`event_logs` أسطرَ هذه الشاشة. */
    private const LOG_MODULE = 'TaskProjectSettings';

    /** المفاتيح الثلاثة التي تحفظها هذه الشاشة، بتسمياتها في السجلّ. */
    private const KEYS = [
        'team_config' => 'إعدادات الفريق',
        'sprint_config' => 'إعدادات السبرنت',
        'notification_config' => 'إعدادات التنبيهات',
    ];

    public function __construct()
    {
        /*
            الحارس هنا فوق حارس المسار لا بدلاً منه — كما في
            [\Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController].
            المتحكّم قد يُستدعى من مسارٍ جديدٍ يُنسى حارسُه، والصلاحية
            صفةُ الإجراء لا صفةُ المسار.
        */
        $this->middleware('auth');

        // صلاحية `tasks.settings.*` تقول «يعدّل إعدادات مشاريع» — وهذا
        // يقول «إعدادات هذا المشروع»: القراءة لأعضائه والتعديل لمديريه
        $this->middleware(function ($request, $next) {
            $projectId = (int) $request->route('projectId');

            abort_unless(Project::whereKey($projectId)->exists(), 404);

            $request->isMethodSafe()
                ? ProjectAccess::authorizeView($projectId)
                : ProjectAccess::authorizeManage($projectId);

            return $next($request);
        });
        $this->middleware('permission.role:tasks.settings.view', [
            'only' => ['index', 'getTeamConfig', 'getSprintConfig', 'getNotificationConfig'],
        ]);
        $this->middleware('permission.role:tasks.settings.edit', [
            'only' => ['store', 'updateTeamConfig', 'updateSprintConfig', 'updateNotificationConfig'],
        ]);
    }

    public function index($projectId)
    {
        try {
            // كائنٌ لا مصفوفة: القالب يقرأ `$settings->team_config[...]`
            // منذ كُتب، وتغييرُ شكل المتغيّر يعني إعادة كتابته كلَّه بلا
            // فائدة
            $settings = (object) [
                'team_config' => $this->read($projectId, 'team_config'),
                'sprint_config' => $this->read($projectId, 'sprint_config'),
                'notification_config' => $this->read($projectId, 'notification_config'),
            ];

            return view('taskmanager::task-settings.index', compact('settings', 'projectId'));
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'index', (int) $projectId);

            return redirect()->back()->with('error', 'حدث خطأ أثناء تحميل الإعدادات');
        }
    }

    public function store(Request $request, $projectId)
    {
        $validator = Validator::make($request->all(), [
            'team_config.max_team_size' => 'required|integer|min:1',
            'team_config.require_team_approval' => 'nullable|boolean',
            'sprint_config.sprint_duration' => 'required|integer|in:7,14,30',
            'sprint_config.sprint_start_day' => 'required|string|max:40',
            'notification_config.notification_types' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $changes = ['old' => [], 'new' => []];

            foreach (array_keys(self::KEYS) as $key) {
                $before = $this->read($projectId, $key);
                $after = $this->normalize($key, (array) $request->input($key, []));

                if ($before !== $after) {
                    $changes['old'][$key] = $before;
                    $changes['new'][$key] = $after;
                }

                $this->write($projectId, $key, $after);
            }

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                (int) $projectId,
                sprintf('عُدّلت إعدادات المهام للمشروع **#%d**', $projectId),
                $changes['new'] === [] ? [] : $changes,
            );

            return redirect()->route('taskmanager.settings.index', $projectId)
                ->with('success', 'تم حفظ الإعدادات بنجاح');
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store', (int) $projectId);

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء حفظ الإعدادات')
                ->withInput();
        }
    }

    /* ==================== إعدادات الفريق ==================== */

    public function getTeamConfig($projectId)
    {
        return $this->readJson($projectId, 'team_config');
    }

    public function updateTeamConfig(Request $request, $projectId)
    {
        return $this->updateJson($request, $projectId, 'team_config', [
            'max_team_size' => 'required|integer|min:1',
            'require_team_approval' => 'nullable|boolean',
            'auto_assign_tasks' => 'nullable|boolean',
            'workload_balancing_enabled' => 'nullable|boolean',
        ]);
    }

    /* ==================== إعدادات السبرنت ==================== */

    /*
        الأربعة أدناه كانت مُسجَّلةً في ملف المسارات وغيرَ موجودةٍ في
        المتحكّم: أربعة مساراتٍ تنتهي كلٌّ منها بـ
        `BadMethodCallException`. وهي تخدم شاشة الإعدادات نفسها عبر
        AJAX، فغيابُها يعني أن تبويبَي السبرنت والتنبيهات لا يحفظان شيئاً.
    */

    public function getSprintConfig($projectId)
    {
        return $this->readJson($projectId, 'sprint_config');
    }

    public function updateSprintConfig(Request $request, $projectId)
    {
        return $this->updateJson($request, $projectId, 'sprint_config', [
            'sprint_duration' => 'required|integer|in:7,14,30',
            'sprint_start_day' => 'required|string|max:40',
            'auto_create_next_sprint' => 'nullable|boolean',
            'sprint_review_enabled' => 'nullable|boolean',
        ]);
    }

    /* ==================== إعدادات التنبيهات ==================== */

    public function getNotificationConfig($projectId)
    {
        return $this->readJson($projectId, 'notification_config');
    }

    public function updateNotificationConfig(Request $request, $projectId)
    {
        return $this->updateJson($request, $projectId, 'notification_config', [
            'notification_types' => 'nullable|array',
            'notification_types.*' => 'nullable|boolean',
            'active_channels' => 'nullable|array',
            'notification_frequency' => 'nullable|string|in:immediate,hourly,daily',
        ]);
    }

    /* ==================== الداخل ==================== */

    /**
     * قراءة مفتاحٍ بوراثته: إعداد المشروع ← الإعداد العامّ ← الافتراضي.
     *
     * @return array<string, mixed>
     */
    private function read($projectId, string $key): array
    {
        $value = TaskSettingsHelper::getSetting($projectId, $key, $this->defaults()[$key]);

        return is_array($value) ? $value : $this->defaults()[$key];
    }

    /** @param  array<string, mixed>  $value */
    private function write($projectId, string $key, array $value): void
    {
        TaskSettings::setSetting($projectId, $key, $value, 'json', self::KEYS[$key], false);

        // `TaskSettingsHelper::getCachedSettings()` يحفظ ساعةً كاملة —
        // وبلا إسقاطها يحفظ المستخدم ثم يرى ما قبل الحفظ فيظنّه لم يُحفظ
        Cache::forget('task_settings_'.$projectId);
        session()->forget('task_settings_'.$projectId);
    }

    private function readJson($projectId, string $key)
    {
        try {
            return response()->json(['success' => true, 'data' => $this->read($projectId, $key)]);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'read:'.$key, (int) $projectId);

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء جلب '.self::KEYS[$key]], 500);
        }
    }

    /** @param  array<string, string>  $rules */
    private function updateJson(Request $request, $projectId, string $key, array $rules)
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $before = $this->read($projectId, $key);
            $after = $this->normalize($key, $validator->validated());

            $this->write($projectId, $key, $after);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                (int) $projectId,
                sprintf('عُدّلت «%s» للمشروع **#%d**', self::KEYS[$key], $projectId),
                $before === $after ? [] : ['old' => [$key => $before], 'new' => [$key => $after]],
            );

            return response()->json(['success' => true, 'message' => 'تم تحديث '.self::KEYS[$key].' بنجاح']);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update:'.$key, (int) $projectId);

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء تحديث '.self::KEYS[$key]], 500);
        }
    }

    /**
     * دمج الوارد فوق الافتراضي مع تثبيت أنواع المفاتيح المنطقية.
     *
     * مربّع اختيارٍ غير مؤشَّر لا يصل في الطلب أصلاً — فبلا الدمج يختفي
     * المفتاح من الإعداد المحفوظ، ويعود عند القراءة التالية إلى قيمته
     * الافتراضية `true`. أي أن إطفاء الخيار كان يُعيد تشغيله.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(string $key, array $input): array
    {
        $merged = array_merge($this->defaults()[$key], $input);

        foreach ($merged as $field => $value) {
            if (is_bool($this->defaults()[$key][$field] ?? null)) {
                $merged[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        if ($key === 'notification_config' && isset($merged['notification_types']) && is_array($merged['notification_types'])) {
            $merged['notification_types'] = array_map(
                fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                $merged['notification_types']
            );
        }

        return $merged;
    }

    /** @return array<string, array<string, mixed>> */
    private function defaults(): array
    {
        return [
            'team_config' => [
                'max_team_size' => 10,
                'require_team_approval' => false,
                'auto_assign_tasks' => false,
                'workload_balancing_enabled' => true,
            ],
            'sprint_config' => [
                'sprint_duration' => 14,
                'sprint_start_day' => 'الأحد',
                'auto_create_next_sprint' => true,
                'sprint_review_enabled' => true,
            ],
            'notification_config' => [
                'notification_types' => [
                    'task_assigned' => true,
                    'task_completed' => true,
                    'task_overdue' => true,
                ],
                'active_channels' => ['email', 'in_app'],
                'notification_frequency' => 'immediate',
            ],
        ];
    }
}
