<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CronJob;
use App\Models\CronJobExecution;
use App\Services\Cron\CronJobService;
use App\Services\Cron\CronMonitoringService;
use App\Services\Cron\CronRegistry;
use App\Services\Cron\CronScheduleParser;
use App\Services\Cron\SchedulerTick;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\Sitting\Http\Requests\CronJobRequest;

/**
 * شاشة المهام المجدولة.
 *
 * الصلاحيات مفروضة لكل إجراء على حدة، لا بصلاحية إعدادات عامة واحدة:
 * عرض المهام شيء، وتشغيل أمر على الخادم شيء آخر.
 */
class CronJobController extends Controller
{
    public function __construct(
        protected CronJobService $service,
        protected CronMonitoringService $monitor,
    ) {
        $this->middleware('permission:settings.cron.view')->only(['index', 'show']);
        $this->middleware('permission:settings.cron.create')->only(['create', 'store']);
        $this->middleware('permission:settings.cron.update')->only(['edit', 'update', 'toggle', 'pause', 'resume']);
        $this->middleware('permission:settings.cron.delete')->only('destroy');
        $this->middleware('permission:settings.cron.run')->only('run');
    }

    /**
     * رفض أي عملية على مهمّة محميّة — بـ404 لا 403.
     *
     * المهمّة مخفيّة من الشاشة أصلاً، فمن يصل إلى مسارها يكون قد خمّن
     * معرّفها. و404 لا تُثبت له أنها موجودة.
     */
    protected function guardProtected(CronJob $cronJob): void
    {
        if ($cronJob->isProtected()) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        // `visible()` يستثني المحميّة — مزامنة الاشتراك تُنفَّذ ولا تُدار،
        // وعرضُها بأزرارٍ معطَّلة يدعو إلى المحاولة وتوليد الأسئلة
        $query = CronJob::query()->visible();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")
                    ->orWhere('handler', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        // «الفاشلة فقط» أكثر ما يُبحث عنه في شاشة مهام، فله مرشّح مستقلّ
        if ($request->boolean('failing')) {
            $query->where('consecutive_failures', '>', 0);
        }

        $jobs = $query->orderByRaw("FIELD(status, 'active', 'paused', 'disabled')")
            ->orderBy('next_run_at')
            ->orderBy('name')
            ->get();

        return view('sitting::cron_jobs.index', [
            'jobs' => $jobs,
            'stats' => $this->monitor->dashboard(),
            // المجدول الداخلي: يعمل من الزيارات حين لا Cron — ورابطُ النبض
            // لخدمة مراقبة خارجية تُوقظه في ساعات الخلوّ
            'webTick' => ['enabled' => SchedulerTick::enabled(), 'url' => SchedulerTick::url()],
            'types' => CronJob::types(),
            'statuses' => CronJob::statuses(),
            // الوصف المقروء يُحسَب هنا لا في القالب — القوالب لا تُستدعى
            // فيها خدمات
            'descriptions' => $jobs->mapWithKeys(
                fn (CronJob $job) => [$job->id => CronScheduleParser::describe($job->cron_expression)]
            ),
        ]);
    }

    public function show(CronJob $cronJob, Request $request)
    {
        $this->guardProtected($cronJob);

        $executions = $cronJob->executions()
            ->with('executor')
            ->latest('started_at')
            ->paginate(\Modules\Sitting\Support\PageSize::get(20))
            ->withQueryString();

        return view('sitting::cron_jobs.show', [
            'job' => $cronJob,
            'executions' => $executions,
            'description' => CronScheduleParser::describe($cronJob->cron_expression),
            'nextRuns' => CronScheduleParser::nextRuns($cronJob->cron_expression, 5, $cronJob->timezone),
            'meta' => CronRegistry::find($cronJob->handler),
            'stats' => [
                'total' => $cronJob->executions()->count(),
                'success' => $cronJob->executions()->succeeded()->count(),
                'failed' => $cronJob->executions()->failed()->count(),
                'avg_duration' => (int) $cronJob->executions()->succeeded()->avg('duration_ms'),
            ],
        ]);
    }

    public function create()
    {
        return view('sitting::cron_jobs.form', [
            'job' => new CronJob(['schedule_type' => 'daily', 'status' => CronJob::STATUS_ACTIVE, 'timeout' => 300]),
            'handlers' => CronRegistry::grouped(),
            'scheduleTypes' => CronJob::scheduleTypes(),
            'statuses' => CronJob::statuses(),
            'types' => CronJob::types(),
            'weekDays' => CronJob::weekDays(),
        ]);
    }

    public function store(CronJobRequest $request)
    {
        try {
            $job = $this->service->create($request->validated(), auth()->id());
        } catch (\InvalidArgumentException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.CronJob', 'store');

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('CronJobs.show', $job->id)
            ->with('success', __('settings.cron.created_msg', ['name' => $job->name]));
    }

    public function edit(CronJob $cronJob)
    {
        $this->guardProtected($cronJob);

        return view('sitting::cron_jobs.form', [
            'job' => $cronJob,
            'handlers' => CronRegistry::grouped(),
            'scheduleTypes' => CronJob::scheduleTypes(),
            'statuses' => CronJob::statuses(),
            'types' => CronJob::types(),
            'weekDays' => CronJob::weekDays(),
        ]);
    }

    public function update(CronJobRequest $request, CronJob $cronJob)
    {
        $this->guardProtected($cronJob);

        try {
            $this->service->update($cronJob, $request->validated(), auth()->id());
        } catch (\InvalidArgumentException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.CronJob', 'update');

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('CronJobs.show', $cronJob->id)
            ->with('success', __('settings.cron.updated_msg', ['name' => $cronJob->name]));
    }

    /** تبديل بين نشطة ومعطّلة. */
    public function toggle(CronJob $cronJob)
    {
        $this->guardProtected($cronJob);

        $cronJob->isActive()
            ? $this->service->disable($cronJob, auth()->id())
            : $this->service->enable($cronJob, auth()->id());

        return back()->with('success', $cronJob->isActive()
            ? __('settings.cron.enabled_msg', ['name' => $cronJob->name])
            : __('settings.cron.disabled_msg', ['name' => $cronJob->name]));
    }

    public function pause(CronJob $cronJob)
    {
        $this->guardProtected($cronJob);

        $this->service->pause($cronJob, auth()->id());

        return back()->with('success', __('settings.cron.paused_msg', ['name' => $cronJob->name]));
    }

    public function resume(CronJob $cronJob)
    {
        $this->guardProtected($cronJob);

        $this->service->resume($cronJob, auth()->id());

        return back()->with('success', __('settings.cron.resumed_msg', ['name' => $cronJob->name]));
    }

    /**
     * تشغيل فوري.
     *
     * ينفَّذ داخل الطلب نفسه (الطابور مضبوط على `sync` في هذا المشروع)،
     * فقد يستغرق وقتاً — وهذا مقصود: الغاية من الزرّ رؤية النتيجة الآن.
     */
    public function run(CronJob $cronJob)
    {
        $this->guardProtected($cronJob);

        $execution = $this->service->runNow($cronJob, auth()->id());

        $message = match ($execution->status) {
            CronJobExecution::STATUS_SUCCESS => __('settings.cron.ran_ok', ['name' => $cronJob->name, 'duration' => $execution->humanDuration()]),
            CronJobExecution::STATUS_SKIPPED => __('settings.cron.ran_skipped', ['error' => $execution->error_message]),
            default => __('settings.cron.ran_failed', ['error' => $execution->error_message]),
        };

        return back()->with(
            $execution->status === CronJobExecution::STATUS_SUCCESS ? 'success' : 'error',
            $message
        );
    }

    public function destroy(CronJob $cronJob)
    {
        $this->guardProtected($cronJob);

        try {
            $name = $cronJob->name;
            $this->service->delete($cronJob);
        } catch (\RuntimeException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.CronJob', 'destroy');

            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('CronJobs.index')->with('success', __('settings.cron.deleted_msg', ['name' => $name]));
    }
}
