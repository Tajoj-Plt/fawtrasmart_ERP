<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\BackupSetting;
use App\Models\CronJob;
use App\Models\CronJobExecution;
use App\Services\Backup\DataSectionCatalog;
use App\Services\Cron\CronJobService;
use App\Services\Cron\CronScheduleParser;
use App\Support\OperationLog;
use Modules\Sitting\Http\Requests\BackupScheduleRequest;

/**
 * جداول النسخ الاحتياطي — أكثر من مهمة مجدولة لا واحدة.
 *
 * كانت شاشة الإعدادات تحكم صفّاً واحداً اسمه `scheduled_backup`، فكان
 * النظام يقبل جدولة واحدة فقط: «كل يوم الساعة كذا» ولا شيء غيرها. وهذا
 * لا يكفي لحالة واقعية شائعة — نسخة كاملة أسبوعياً ونسخة سريعة لجداول
 * الحركات كل ساعة — لأن الجدولتين تتنازعان إعداداً واحداً.
 *
 * الحلّ هنا ليس جدولاً جديداً في قاعدة البيانات: كل جدول صفٌّ في
 * `cron_jobs` مُعالِجه `backup:scheduled-run`، ونطاقه في `parameters`.
 * فيبقى مسار تنفيذ واحد (`CronJobRunner`)، وسجلّ تنفيذ واحد، وقائمة
 * أوامر بيضاء واحدة — والشاشة هنا واجهةٌ بلغة النسخ الاحتياطي فوقها.
 *
 * ما يبقى في شاشة الإعدادات: الجدول الأساسي (`scheduled_backup`) لأنه
 * مربوط بحقول `backup_settings` نفسها، ومفتاح «تفعيل الحفظ التلقائي»
 * الذي يحكم كل الجداول معاً.
 */
class BackupScheduleController extends Controller
{
    /** المُعالِج الوحيد الذي تديره هذه الشاشة. */
    public const HANDLER = 'backup:scheduled-run';

    /** مفتاح الجدول الأساسي المربوط بشاشة الإعدادات. */
    public const PRIMARY_KEY = 'scheduled_backup';

    public function __construct(protected CronJobService $service)
    {
        $this->middleware('permission:settings.backup.view')->only('index');
        $this->middleware('permission:settings.backup.settings')
            ->only(['create', 'store', 'edit', 'update', 'destroy', 'toggle']);
        $this->middleware('permission:settings.backup.create')->only('run');
    }

    // ==================== العرض ====================

    public function index()
    {
        $jobs = CronJob::where('handler', self::HANDLER)
            // الأساسي أولاً ثم النشط قبل المعطَّل: القائمة تُقرأ من أعلاها
            ->orderByRaw('CASE WHEN `key` = ? THEN 0 ELSE 1 END', [self::PRIMARY_KEY])
            ->orderByRaw("FIELD(status, 'active', 'paused', 'disabled')")
            ->orderBy('next_run_at')
            ->get();

        return view('sitting::backup.schedules.index', [
            'jobs' => $jobs,
            'settings' => BackupSetting::current(),
            'primaryKey' => self::PRIMARY_KEY,
            'descriptions' => $jobs->mapWithKeys(
                fn (CronJob $job) => [$job->id => CronScheduleParser::describe($job->cron_expression)]
            ),
            'scopes' => $jobs->mapWithKeys(
                fn (CronJob $job) => [$job->id => $this->scopeLabel($job)]
            ),
            // «آخر نسخة أنتجتها هذه المهمة» هو ما يُثبت أنها تعمل فعلاً —
            // أوضح من «آخر تنفيذ ناجح» الذي قد ينجح بلا إنتاج نسخة
            'lastBackups' => Backup::whereIn('cron_job_id', $jobs->pluck('id'))
                ->available()
                ->latest('completed_at')
                ->get()
                ->unique('cron_job_id')
                ->keyBy('cron_job_id'),
        ]);
    }

    public function create()
    {
        return view('sitting::backup.schedules.form', $this->formData(
            new CronJob([
                'handler' => self::HANDLER,
                'type' => 'backup',
                'schedule_type' => 'weekly',
                'schedule_day' => 5,
                'schedule_time' => '02:00',
                'status' => CronJob::STATUS_ACTIVE,
                'timeout' => 1800,
                'prevent_overlap' => true,
                'notify_on_failure' => true,
            ])
        ));
    }

    public function edit(CronJob $cronJob)
    {
        $this->assertManageable($cronJob);

        return view('sitting::backup.schedules.form', $this->formData($cronJob));
    }

    // ==================== الحفظ ====================

    public function store(BackupScheduleRequest $request)
    {
        try {
            $job = $this->service->create($this->attributes($request), auth()->id());
        } catch (\InvalidArgumentException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.BackupSchedule', 'store');

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('Backup.schedules.index')
            ->with('success', __('settings.backup.schedules.created', [
                'name' => $job->name,
                'schedule' => CronScheduleParser::describe($job->cron_expression),
            ]));
    }

    public function update(BackupScheduleRequest $request, CronJob $cronJob)
    {
        $this->assertManageable($cronJob);

        try {
            $this->service->update($cronJob, $this->attributes($request), auth()->id());
        } catch (\InvalidArgumentException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.BackupSchedule', 'update');

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('Backup.schedules.index')
            ->with('success', __('settings.backup.schedules.updated', [
                'name' => $cronJob->name,
                'schedule' => CronScheduleParser::describe($cronJob->refresh()->cron_expression),
            ]));
    }

    // ==================== التحكّم ====================

    public function toggle(CronJob $cronJob)
    {
        $this->assertBackupJob($cronJob);

        $cronJob->isActive()
            ? $this->service->disable($cronJob, auth()->id())
            : $this->service->enable($cronJob, auth()->id());

        return back()->with('success', $cronJob->isActive()
            ? __('settings.cron.enabled_msg', ['name' => $cronJob->name])
            : __('settings.cron.disabled_msg', ['name' => $cronJob->name]));
    }

    /** تشغيل جدول الآن — للتأكّد من أنه ينتج ما يُتوقَّع منه قبل انتظار موعده. */
    public function run(CronJob $cronJob)
    {
        $this->assertBackupJob($cronJob);

        // الأمر ينتهي بنجاح ولا يفعل شيئاً حين يكون الحفظ التلقائي مطفأً —
        // سلوكٌ سليم للمجدول (فشلٌ كل ساعة يغرق المسؤول بتنبيهات لا معنى
        // لها) وخادعٌ للزرّ: ضغطة تُنتج «تم بنجاح» بلا نسخة. الفحص هنا
        // يقول السبب قبل التشغيل بدل أن يُخفيه نجاحٌ ظاهري
        if (! BackupSetting::current()->is_enabled) {
            return back()->with('error', __('settings.backup.schedules.master_off_run'));
        }

        $execution = $this->service->runNow($cronJob, auth()->id());

        $message = match ($execution->status) {
            CronJobExecution::STATUS_SUCCESS => __('settings.cron.ran_ok', [
                'name' => $cronJob->name,
                'duration' => $execution->humanDuration(),
            ]),
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
        $this->assertManageable($cronJob);

        try {
            $name = $cronJob->name;
            $this->service->delete($cronJob);
        } catch (\RuntimeException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.BackupSchedule', 'destroy');

            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('Backup.schedules.index')
            ->with('success', __('settings.cron.deleted_msg', ['name' => $name]));
    }

    // ==================== الداخل ====================

    /**
     * ترجمة مدخلات الشاشة إلى صفّ مهمة.
     *
     * `parameters` هي كل ما يميّز جدولاً عن آخر: بدونها ينفّذ كل جدول ما
     * في `backup_settings` نفسه، فتصبح الجداول المتعدّدة نسخاً مكرّرة من
     * الشيء ذاته.
     *
     * @return array<string, mixed>
     */
    protected function attributes(BackupScheduleRequest $request): array
    {
        return [
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'handler' => self::HANDLER,
            'type' => 'backup',
            'parameters' => $this->parameters($request),
            'schedule_type' => $request->validated('schedule_type'),
            'schedule_time' => $request->validated('schedule_time'),
            'schedule_day' => $request->validated('schedule_day'),
            'cron_expression' => $request->validated('cron_expression'),
            'status' => $request->validated('status'),
            'timeout' => $request->validated('timeout'),
            // نسختان متزامنتان تتنازعان القفل في `BackupService` وتنتهي
            // إحداهما صفّاً فاشلاً — المنع هنا يوفّر ذلك الفشل أصلاً
            'prevent_overlap' => true,
            'notify_on_failure' => $request->validated('notify_on_failure'),
            'notify_on_success' => $request->validated('notify_on_success'),
        ];
    }

    /**
     * معاملات الأمر المقابلة للاختيارات.
     *
     * القيم المنطقية تُمرَّر `true` لا `'1'`: خيارات `VALUE_NONE` في
     * Symfony ترفض القيم، فنصٌّ هنا يُفشل التنفيذ برسالة لا صلة لها بالنسخ.
     *
     * @return array<string, mixed>
     */
    protected function parameters(BackupScheduleRequest $request): array
    {
        $parameters = [];

        if ($request->validated('backup_type') !== 'settings') {
            $parameters['type'] = $request->validated('backup_type');
        }

        $tables = DataSectionCatalog::tablesForKeys((array) $request->validated('sections', []));

        match ($request->validated('scope_mode')) {
            'all' => $parameters['all-tables'] = true,
            'include' => $parameters['tables'] = implode(',', $tables),
            'exclude' => $parameters['exclude-tables'] = implode(',', $tables),
            // settings: بلا خيار نطاق — الأمر يتّبع الإعدادات المحفوظة
            default => null,
        };

        if ($request->validated('skip_cleanup')) {
            $parameters['skip-cleanup'] = true;
        }

        return $parameters;
    }

    /**
     * البيانات المشتركة بين شاشتَي الإنشاء والتعديل.
     *
     * @return array<string, mixed>
     */
    protected function formData(CronJob $job): array
    {
        $parameters = $this->readParameters($job);

        return [
            'job' => $job,
            'scheduleTypes' => CronJob::scheduleTypes(),
            'statuses' => CronJob::statuses(),
            'weekDays' => CronJob::weekDays(),
            'types' => BackupSetting::types(),
            'settings' => BackupSetting::current(),
            'sections' => DataSectionCatalog::forPicker(),
            'currentType' => $parameters['type'] ?? 'settings',
            'currentScope' => $parameters['scope'],
            'currentSections' => $parameters['sections'],
            'skipCleanup' => $parameters['skip_cleanup'],
            'isPrimary' => $job->key === self::PRIMARY_KEY,
        ];
    }

    /**
     * قراءة معاملات صفّ قائم بلغة الشاشة.
     *
     * المفاتيح محفوظة ببادئة `--` كما تُمرَّر إلى الأمر، وقد تصل بلا بادئة
     * من صفّ زرعه الـ seeder — فتُقرأ الصورتان.
     *
     * @return array{type:?string,scope:string,sections:array<int,string>,skip_cleanup:bool}
     */
    protected function readParameters(CronJob $job): array
    {
        $raw = (array) ($job->parameters ?? []);
        $get = function (string $name) use ($raw) {
            return $raw['--'.$name] ?? $raw[$name] ?? null;
        };

        $included = $get('tables');
        $excluded = $get('exclude-tables');

        $scope = match (true) {
            (bool) $get('all-tables') => 'all',
            ! empty($included) => 'include',
            ! empty($excluded) => 'exclude',
            default => 'settings',
        };

        $names = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($included ?: $excluded))
        ), fn ($name) => $name !== ''));

        return [
            'type' => $get('type'),
            'scope' => $scope,
            // الأسماء المحفوظة جداولُ، والشاشة تعمل بالأقسام
            'sections' => DataSectionCatalog::keysForTables($names),
            'skip_cleanup' => (bool) $get('skip-cleanup'),
        ];
    }

    /** وصف نطاق جدول بجملة واحدة للقائمة. */
    protected function scopeLabel(CronJob $job): string
    {
        $parameters = $this->readParameters($job);

        $type = $parameters['type']
            ? (BackupSetting::types()[$parameters['type']]['label'] ?? $parameters['type'])
            : __('settings.backup.schedules.type_from_settings');

        $scope = match ($parameters['scope']) {
            'all' => __('settings.backup.schedules.scope_all'),
            'include' => __('settings.backup.scope_include', ['count' => count($parameters['sections'])]),
            'exclude' => __('settings.backup.scope_exclude', ['count' => count($parameters['sections'])]),
            default => __('settings.backup.schedules.scope_from_settings'),
        };

        return $type.' — '.$scope;
    }

    /**
     * صفّ ليس جدولَ نسخ لا تلمسه هذه الشاشة.
     *
     * ربط المسار بـ`CronJob` يقبل أي معرّف مهمة، ولولا هذا الفحص لأمكن
     * تعديل مهمة رواتب أو حذفها من شاشة النسخ الاحتياطي بصلاحياتها.
     */
    protected function assertBackupJob(CronJob $job): void
    {
        abort_unless($job->handler === self::HANDLER, 404);
    }

    /**
     * الجدول الأساسي يُقرأ هنا ويُعدَّل من شاشة الإعدادات وحدها.
     *
     * جدولته مشتقّة من `backup_settings` ويعيد `syncScheduledJob` كتابتها
     * عند كل حفظ للإعدادات — فتعديله من هنا تغييرٌ يُمحى عند أوّل حفظ،
     * وهو أسوأ من منعه.
     */
    protected function assertManageable(CronJob $job): void
    {
        $this->assertBackupJob($job);

        abort_if($job->key === self::PRIMARY_KEY, 403, __('settings.backup.schedules.primary_locked'));
    }
}
