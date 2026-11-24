<?php

namespace Modules\TaskManager\Http\Controllers\Settings;

use App\Helpers\TaskSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\TaskSettings;
use App\Support\OperationLog;
use App\Support\TaskManager\TaskVocabulary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * الإعدادات العامّة لوحدة المهام — شاشتُها في لوحة إعدادات النظام.
 *
 * ─────────────────────── لماذا شاشةٌ عامّة أصلاً؟ ───────────────────────
 *
 * كان في الوحدة `TaskSettingsController` واحد، ومسارُه
 * `projects/{projectId}/settings` — أي لا يُفتح إلا من داخل مشروع. فالحالات
 * والأنواع والأولويات، وهي بيانات النظام المرجعية التي تتشاركها المشاريع
 * كلّها، لم يكن لها موضعٌ تُدار منه: تُقرأ من `TaskSettingsHelper` بقيمٍ
 * مكتوبةٍ في الشيفرة، وتُكتب من لا شيء. ووجدت في الوحدة شاشةُ
 * `settings/global.blade.php` تُشير إلى أربعة مساراتٍ لا وجود لها
 * (`settings.add-global-status` وأخواتها) وإلى متحكّمٍ لم يكن موجوداً —
 * ملفٌّ ميّت لا يُفتح.
 *
 * فصار لها هذا المتحكّم، وبندُه في [\App\Services\Settings\SettingsNavigation]
 * فيظهر في «لوحة الإعدادات» و«الشريط الجانبي» معاً.
 *
 * ─────────────────────── أين تُحفظ؟ ───────────────────────
 *
 * في `task_settings` نفسه بـ`is_global = true` و`project_id = null` — وهو
 * المخزن الذي يقرأ منه [\App\Helpers\TaskSettingsHelper] أصلاً حين لا
 * يجد إعداداً خاصّاً بالمشروع. فلا جدولَ جديد ولا مخزنَ ثانٍ: إعدادُ
 * المشروع يغلب العامّ، والعامُّ يغلب القيمة المكتوبة في الشيفرة.
 *
 * ─────────────────────── الكاش ───────────────────────
 *
 * `TaskSettingsHelper` يخزّن `global_statuses` و`global_types`
 * و`global_priorities` ساعةً كاملة. فكل كتابةٍ هنا تُسقط الثلاثة — وإلّا
 * حفظ المستخدم إعداداً وظلّت الشاشات ساعةً تعرض ما قبله، وهو أسوأ من
 * ألّا يُحفظ: لا يظهر عطلاً بل «لم يُحفظ» فيُعاد الحفظ مراراً.
 */
class GlobalSettingsController extends Controller
{
    /** وحدة السجلّ — بها يربط `logs`/`event_logs` أسطرَ هذه الشاشة. */
    private const LOG_MODULE = 'TaskGlobalSettings';

    /**
     * مفاتيح الإعدادات الثلاثة وحقولُها — مصدرٌ واحد للتحقّق وللحفظ وللحذف.
     *
     * القيمة الافتراضية تُقرأ من `TaskSettingsHelper` لا تُكرَّر هنا: نسخةٌ
     * ثانية منها تعني شاشةً تعرض غير ما تقرؤه بقيّة الوحدة.
     */
    private const GROUPS = [
        'statuses' => ['label' => 'حالات المهام', 'fields' => ['name', 'color', 'type']],
        'types' => ['label' => 'أنواع المهام', 'fields' => ['name', 'icon', 'color']],
        'priorities' => ['label' => 'أولويات المهام', 'fields' => ['name', 'color', 'level']],
    ];

    public function __construct()
    {
        // حارسٌ في المتحكّم فوق حارس المسار — الصلاحية صفةُ الإجراء لا
        // صفةُ المسار
        $this->middleware('permission.role:tasks.settings.global.view', ['only' => ['index']]);
        $this->middleware('permission.role:tasks.settings.global.edit', [
            'only' => ['storeStatus', 'storeType', 'storePriority', 'destroy', 'updateNotifications'],
        ]);
    }

    public function index()
    {
        return view('taskmanager::settings.global', [
            'statuses' => $this->read('statuses'),
            'types' => $this->read('types'),
            'priorities' => $this->read('priorities'),
            'notifications' => TaskSettingsHelper::getSetting(null, 'notifications', [
                'task_created' => true,
                'task_assigned' => true,
                'task_updated' => true,
                'comment_added' => true,
                'status_changed' => true,
                'deadline_approaching' => true,
            ]),
        ]);
    }

    public function storeStatus(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', Rule::in(TaskVocabulary::statusIds())],
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [], ['id' => 'الحالة', 'name' => 'الاسم', 'color' => 'اللون']);

        return $this->customize('statuses', $data);
    }

    public function storeType(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', Rule::in(TaskVocabulary::typeIds())],
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9 _-]+$/'],
        ], [], ['id' => 'النوع', 'name' => 'الاسم', 'color' => 'اللون', 'icon' => 'الأيقونة']);

        return $this->customize('types', array_filter($data, fn ($value) => $value !== null));
    }

    public function storePriority(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', Rule::in(TaskVocabulary::priorityIds())],
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [], ['id' => 'الأولوية', 'name' => 'الاسم', 'color' => 'اللون']);

        return $this->customize('priorities', $data);
    }

    /**
     * إعادة بندٍ إلى اسمه ولونه الافتراضيين.
     *
     * لا «حذف» هنا: المعرّفات هي قيم أعمدة `tasks` نفسها
     * ([TaskVocabulary])، وحذفُ واحدٍ منها يُخفي من اللوحة مهامَّ ما زالت
     * تحمله.
     */
    public function destroy(string $group, string $id)
    {
        if (! isset(self::GROUPS[$group])) {
            return $this->fail('مجموعة إعداداتٍ غير معروفة.', 422);
        }

        try {
            $before = $this->read($group);
            $overrides = array_values(array_filter(
                $this->overrides($group),
                fn ($item) => (string) ($item['id'] ?? '') !== $id,
            ));

            $this->write($group, $overrides);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                null,
                sprintf('أُعيد **%s** في «%s» إلى قيمته الافتراضية', $id, self::GROUPS[$group]['label']),
                ['old' => [$group => $before], 'new' => [$group => $this->read($group)]],
            );

            return response()->json(['success' => true, 'message' => 'أُعيدت القيمة الافتراضية']);
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy');

            return $this->fail('تعذّرت إعادة القيمة الافتراضية.', 500);
        }
    }

    /** تفضيلات التنبيهات الافتراضية لكل المشاريع. */
    public function updateNotifications(Request $request)
    {
        $keys = ['task_created', 'task_assigned', 'task_updated', 'comment_added', 'status_changed', 'deadline_approaching'];

        try {
            $before = TaskSettingsHelper::getSetting(null, 'notifications', []);
            $after = [];

            foreach ($keys as $key) {
                $after[$key] = $request->boolean($key);
            }

            TaskSettings::setSetting(null, 'notifications', $after, 'json', 'تفضيلات تنبيهات المهام الافتراضية', true);
            $this->flushCaches();

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                null,
                'عُدّلت تفضيلات التنبيهات الافتراضية للمهام',
                ['old' => ['notifications' => $before], 'new' => ['notifications' => $after]],
            );

            return back()->with('success', 'تم حفظ تفضيلات التنبيهات');
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'updateNotifications');

            return back()->with('error', 'تعذّر حفظ تفضيلات التنبيهات.');
        }
    }

    /* ==================== الداخل ==================== */

    /**
     * تخصيص اسم بندٍ قائمٍ ولونه — لا إضافةُ بندٍ جديد.
     *
     * كانت الشاشة تضيف بنداً بمعرّفٍ مشتقٍّ من الاسم، والعمود `enum` لا
     * يقبله: يظهر عموداً في اللوحة ثم يرفض الحفظُ كل مهمّةٍ تُسحب إليه.
     *
     * @param  array<string, mixed>  $item
     */
    private function customize(string $group, array $item)
    {
        try {
            $before = $this->read($group);
            $overrides = array_values(array_filter(
                $this->overrides($group),
                fn ($existing) => (string) ($existing['id'] ?? '') !== (string) $item['id'],
            ));
            $overrides[] = $item;

            $this->write($group, $overrides);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                null,
                sprintf('خُصّص **%s** في «%s» في الإعدادات العامّة للمهام', $item['name'], self::GROUPS[$group]['label']),
                ['old' => [$group => $before], 'new' => [$group => $this->read($group)]],
            );

            return back()->with('success', 'تم الحفظ بنجاح');
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'customize:'.$group);

            return back()->with('error', 'تعذّر الحفظ.')->withInput();
        }
    }

    /**
     * ما خصّصه المستخدم فعلاً — بلا القيم الافتراضية التي يضيفها `read()`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function overrides(string $group): array
    {
        $value = TaskSettings::where('setting_key', $group)
            ->where('is_global', true)
            ->whereNull('project_id')
            ->value('setting_value');

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * قراءة مجموعةٍ بقيمها الافتراضية — من نفس الموضع الذي تقرأ منه الوحدة.
     *
     * @return array<int, array<string, mixed>>
     */
    private function read(string $group): array
    {
        $value = match ($group) {
            'statuses' => TaskSettingsHelper::getGlobalStatuses(),
            'types' => TaskSettingsHelper::getGlobalTypes(),
            'priorities' => TaskSettingsHelper::getGlobalPriorities(),
            default => [],
        };

        return is_array($value) ? array_values($value) : [];
    }

    /** @param  array<int, array<string, mixed>>  $items */
    private function write(string $group, array $items): void
    {
        TaskSettings::setSetting(null, $group, $items, 'json', self::GROUPS[$group]['label'], true);
        $this->flushCaches();
    }

    /**
     * إسقاط ما يخزّنه `TaskSettingsHelper` ساعةً كاملة.
     *
     * الثلاثة تُسقط معاً لا المتغيّر وحده: الشاشة تعرضها في صفحةٍ واحدة،
     * وإسقاطُ واحدٍ يترك الصفحة نصفَها محدَّثاً ونصفَها لا.
     */
    private function flushCaches(): void
    {
        foreach (['global_statuses', 'global_types', 'global_priorities'] as $key) {
            Cache::forget($key);
        }
    }

    private function fail(string $message, int $status)
    {
        if (request()->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        return back()->with('error', $message);
    }
}
