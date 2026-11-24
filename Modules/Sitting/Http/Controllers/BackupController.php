<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\BackupSetting;
use App\Models\CronJob;
use App\Services\Backup\BackupCleanupService;
use App\Services\Backup\BackupRestoreService;
use App\Services\Backup\BackupService;
use App\Services\Backup\DataExportService;
use App\Services\Backup\DataSectionCatalog;
use App\Services\Backup\ExportCatalog;
use App\Services\Cron\CronJobService;
use App\Services\Cron\CronScheduleParser;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Sitting\Http\Requests\BackupSettingRequest;

/**
 * شاشات النسخ الاحتياطي.
 *
 * الصلاحيات مفروضة لكل إجراء على حدة: عرض قائمة النسخ شيء، وتنزيل قاعدة
 * البيانات كاملة أو الكتابة فوقها شيء آخر. تقسيمها هنا يطابق ما يزرعه
 * `SystemMaintenancePermissionsSeeder` — الثلاثة الخطرة للمالك وحده.
 */
class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backups,
        // ليس `$cleanup` — الاسم مأخوذ لإجراء في هذا المتحكّم، وتطابقهما
        // يجعل `$this->cleanup` و`$this->cleanup()` متجاورين بلا داع
        protected BackupCleanupService $cleaner,
    ) {
        $this->middleware('permission:settings.backup.view')->only(['index', 'history', 'restores', 'show']);
        $this->middleware('permission:settings.backup.create')->only(['run', 'reconcile']);
        $this->middleware('permission:settings.backup.settings')->only(['settings', 'updateSettings']);
        $this->middleware('permission:settings.backup.download')->only('download');
        $this->middleware('permission:settings.backup.delete')->only(['destroy', 'cleanup', 'toggleLock']);
        $this->middleware('permission:settings.backup.restore')->only('restore');
    }

    // ==================== العرض ====================

    /** لوحة النسخ الاحتياطي: الحالة العامّة وآخر النسخ. */
    public function index()
    {
        $settings = BackupSetting::current();
        $job = $this->scheduledJob();

        return view('sitting::backup.index', [
            'settings' => $settings,
            'job' => $job,
            'scheduleDescription' => $job ? CronScheduleParser::describe($job->cron_expression) : null,
            'latest' => Backup::with('creator')->latest('created_at')->limit(8)->get(),
            'lastRestore' => BackupRestore::with(['backup', 'executor'])->latest('started_at')->first(),
            'stats' => $this->stats($settings),
            // لاختيار أقسام النسخة اليدوية من نافذة «نسخة الآن»
            'sections' => DataSectionCatalog::forPicker(),
            // اختيار حقول بعينها يُخرِج ملفّ بيانات لا نسخة قابلة للاسترجاع،
            // فينفّذه محرّك التصدير. النافذة تعرض الخيارين معاً والمستخدم لا
            // يحتاج أن يعرف أن خلفهما محرّكين
            'exportGroups' => hasPermission('settings.backup.export') ? ExportCatalog::groupedAvailable() : [],
            'exportFormats' => DataExportService::formats(),
        ]);
    }

    /** سجلّ النسخ الكامل مع المرشّحات. */
    public function history(Request $request)
    {
        $query = Backup::with(['creator', 'cronJob']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%");
            });
        }

        foreach (['status', 'type', 'trigger'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter)->toString());
            }
        }

        // «المتاحة فقط» أكثر ما يُبحث عنه: النسخة التي ما زال ملفها موجوداً
        if ($request->boolean('available')) {
            $query->available();
        }

        // النطاق شامل لليوم المحدَّد — `date_to` وحده يعني منتصف ليله
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        return view('sitting::backup.history', [
            'backups' => $query->latest('created_at')->paginate(\Modules\Sitting\Support\PageSize::get(25))->withQueryString(),
            'statuses' => Backup::statuses(),
            'triggers' => Backup::triggers(),
            'types' => BackupSetting::types(),
            'settings' => BackupSetting::current(),
            'stats' => $this->stats(),
        ]);
    }

    public function show(Backup $backup)
    {
        $backup->load(['creator', 'cronJob', 'restores.executor']);

        return view('sitting::backup.show', [
            'backup' => $backup,
            // الصفّ يقول «مكتملة» والملف قد يكون حُذف من خارج النظام —
            // الفحص هنا يمنع عرض زرّ تنزيل لملف غير موجود
            'fileExists' => $backup->fileExists(),
        ]);
    }

    /** سجلّ عمليات الاسترجاع — أخطر ما يُنفَّذ في النظام، فله شاشته. */
    public function restores(Request $request)
    {
        $query = BackupRestore::with(['backup', 'safetyBackup', 'executor']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('sitting::backup.restores', [
            'restores' => $query->latest('started_at')->paginate(\Modules\Sitting\Support\PageSize::get(20))->withQueryString(),
            'statuses' => BackupRestore::statuses(),
        ]);
    }

    // ==================== الإعدادات ====================

    public function settings()
    {
        $job = $this->scheduledJob();
        $settings = BackupSetting::current();

        return view('sitting::backup.settings', [
            'settings' => $settings,
            'job' => $job,
            'scheduleDescription' => $job ? CronScheduleParser::describe($job->cron_expression) : null,
            'types' => BackupSetting::types(),
            'tableModes' => BackupSetting::tableModes(),
            'sections' => DataSectionCatalog::forPicker(),
            // الإعدادات تحفظ أسماء جداول؛ الشاشة تعمل بالأقسام. الاستنتاج
            // هنا يجعل إعداداً محفوظاً يعود مؤشَّراً كما تركه صاحبه
            'includedSections' => DataSectionCatalog::keysForTables($settings->included_tables),
            'excludedSections' => DataSectionCatalog::keysForTables($settings->excluded_tables),
            // إعداد قديم أُنشئ بالاختيار التفصيلي السابق: يُقال صراحةً بدل
            // أن يُمحى صامتاً عند أوّل حفظ من هذه الشاشة
            'uncoveredIncluded' => DataSectionCatalog::uncoveredCount($settings->included_tables),
            'uncoveredExcluded' => DataSectionCatalog::uncoveredCount($settings->excluded_tables),
            'frequencies' => BackupSetting::frequencies(),
            'strategies' => BackupSetting::retentionStrategies(),
            'weekDays' => CronJob::weekDays(),
            'disks' => array_keys((array) config('filesystems.disks', [])),
        ]);
    }

    public function updateSettings(BackupSettingRequest $request, CronJobService $cronJobs)
    {
        $settings = BackupSetting::current();
        $data = $request->validated();

        // حقل الكلمة فارغاً = «أبقِ المحفوظة». وهي لا تُمحى عند إطفاء
        // التعشية أيضاً: الأرشيفات المأخوذة وهي مفعَّلة لا تُفَكّ بدونها،
        // فمحوها يحوّل كل نسخة سابقة إلى ملف لا يُقرأ
        if (($data['archive_password'] ?? '') === '') {
            unset($data['archive_password']);
        }

        $settings->fill($data);

        // المجلَّد يُثبَّت عند أوّل ضبط ولا يتبع الاسم بعدها: تغييره يترك
        // النسخ السابقة في مجلَّد لا يقرأه النظام
        $settings->rememberEstablishmentFolder($settings->establishment_name);

        $settings->updated_by = auth()->id();
        $settings->save();

        BackupSetting::flush();

        // تفعيل النسخ الدوري من هنا يجب أن يفعّل مهمته فعلاً، وإلا بقيت
        // الشاشة تقول «مفعَّل» ولا شيء يُنفَّذ
        $syncMessage = $this->syncScheduledJob($settings->refresh(), $cronJobs);

        return redirect()->route('Backup.settings')
            ->with('success', __('settings.backup.settings_saved').($syncMessage ? ' '.$syncMessage : ''));
    }

    // ==================== الإجراءات ====================

    /**
     * نسخة يدوية الآن.
     *
     * تُنفَّذ داخل الطلب نفسه — قد تستغرق وقتاً، وهذا مقصود: الغاية من
     * الزرّ رؤية نتيجة النسخة الآن لا جدولتها.
     */
    public function run(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:database,files,both'],
            // `settings` تعني «اتّبع الإعدادات» — الافتراضي حين لا يُرسَل شيء
            'table_mode' => ['nullable', 'in:settings,all,include,exclude'],
            'sections' => ['array', 'max:50'],
            'sections.*' => ['string', 'max:64'],
        ]);

        $mode = $data['table_mode'] ?? 'settings';
        // ما لا يطابق مفتاح قسم يسقط صامتاً: المفاتيح ليست مدخلاً حرّاً بل
        // اختياراً من قائمة عرضناها، ووصول غيرها يعني طلباً معدَّلاً
        $tables = DataSectionCatalog::tablesForKeys($data['sections'] ?? []);

        if (in_array($mode, ['include', 'exclude'], true) && $tables === []) {
            return back()->with('error', __('settings.backup.pick_one_section'));
        }

        $selection = match ($mode) {
            'all' => ['mode' => 'all'],
            'include' => ['mode' => 'include', 'included' => $tables],
            'exclude' => ['mode' => 'exclude', 'excluded' => $tables],
            default => null,
        };

        $backup = $this->backups->run($data['type'] ?? null, Backup::TRIGGER_MANUAL, auth()->id(), null, $selection);

        if ($backup->status !== Backup::STATUS_COMPLETED) {
            return back()->with('error', __('settings.backup.create_failed', ['error' => $backup->error_message]));
        }

        return redirect()->route('Backup.show', $backup->id)->with(
            'success',
            __('settings.backup.created', [
                'file' => $backup->file_name,
                'size' => $backup->humanSize(),
                'duration' => $backup->humanDuration(),
            ]).($backup->isPartial()
                ? __('settings.backup.created_scope', ['scope' => $backup->tableScopeLabel()])
                : '')
        );
    }

    /** مطابقة السجلّ مع ما هو على القرص فعلاً. */
    public function reconcile()
    {
        $result = $this->backups->reconcile();

        if ($result['adopted'] === 0 && $result['missing'] === 0) {
            return back()->with('success', __('settings.backup.sync_clean'));
        }

        return back()->with('success', __('settings.backup.sync_result', [
            'adopted' => $result['adopted'],
            'missing' => $result['missing'],
        ]));
    }

    /** تطبيق سياسة الاحتفاظ يدوياً. */
    public function cleanup()
    {
        $result = $this->cleaner->run();

        $message = __('settings.backup.cleanup_result', [
            'deleted' => $result['deleted'],
            'freed' => Backup::formatBytes($result['freed_bytes']),
            'kept' => $result['kept'],
        ]);

        return back()->with(
            $result['errors'] === [] ? 'success' : 'error',
            $result['errors'] === []
                ? $message
                : $message.__('settings.backup.cleanup_errors', [
                    'count' => count($result['errors']),
                    'first' => $result['errors'][0],
                ])
        );
    }

    public function download(Backup $backup)
    {
        if (! $backup->isAvailable()) {
            return back()->with('error', __('settings.backup.not_downloadable'));
        }

        try {
            $disk = Storage::disk($backup->disk);

            if (! $disk->exists($backup->path)) {
                // الصفّ يدّعي وجود ملف لا وجود له — يُصحَّح فوراً بدل أن
                // يبقى يَعِد المسؤول بنسخة غير موجودة
                $backup->update(['file_deleted_at' => now()]);

                return back()->with('error', __('settings.backup.file_gone'));
            }

            Log::info('تنزيل نسخة احتياطية', [
                'backup_id' => $backup->id,
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
            ]);

            return $disk->download($backup->path, $backup->file_name ?: basename($backup->path));
        } catch (\Throwable $e) {
            Log::error('تعذّر تنزيل نسخة احتياطية', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', __('settings.backup.download_failed', ['error' => $e->getMessage()]));
        }
    }

    /** حماية نسخة من الحذف التلقائي، أو رفعها. */
    public function toggleLock(Backup $backup)
    {
        $backup->update(['is_locked' => ! $backup->is_locked]);

        return back()->with('success', $backup->is_locked
            ? __('settings.backup.locked_msg', ['file' => $backup->file_name])
            : __('settings.backup.unlocked_msg', ['file' => $backup->file_name]));
    }

    /**
     * استرجاع قاعدة البيانات من نسخة.
     *
     * أخطر إجراء في النظام: يكتب فوق قاعدة البيانات كاملة. لذلك يُطلَب
     * تأكيد مكتوب لا مجرّد ضغطة — ونسخة الأمان يأخذها المُنفِّذ إجبارياً.
     */
    public function restore(Backup $backup, Request $request, BackupRestoreService $service)
    {
        $request->validate([
            'confirm' => ['required', 'string', Rule::in([__('settings.backup.confirm_word')])],
        ], [
            'confirm.required' => __('settings.backup.errors.confirm_required'),
            'confirm.in' => __('settings.backup.errors.confirm_mismatch'),
        ]);

        try {
            $restore = $service->restore($backup, auth()->id(), $request->ip());
        } catch (\RuntimeException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.Backup', 'restore');

            return back()->with('error', $e->getMessage());
        }

        if ($restore->status !== BackupRestore::STATUS_COMPLETED) {
            return redirect()->route('Backup.restores')
                ->with('error', __('settings.backup.restore_failed', ['error' => $restore->error_message])
                    .($restore->safety_backup_id
                        ? __('settings.backup.restore_safety_hint', ['id' => $restore->safety_backup_id])
                        : ''));
        }

        return redirect()->route('Backup.restores')->with(
            'success',
            __('settings.backup.restore_done', [
                'file' => $backup->file_name,
                'seconds' => number_format(($restore->duration_ms ?? 0) / 1000, 1),
                'safety' => $restore->safety_backup_id,
            ])
        );
    }

    public function destroy(Backup $backup)
    {
        try {
            $name = $backup->file_name ?: '#'.$backup->id;
            $this->backups->delete($backup);
        } catch (\RuntimeException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.Backup', 'destroy');

            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('Backup.history')->with('success', __('settings.backup.deleted', ['file' => $name]));
    }

    // ==================== الداخل ====================

    /** مهمة النسخ المجدول — الصفّ الذي يربط الإعدادات بالتنفيذ الفعلي. */
    protected function scheduledJob(): ?CronJob
    {
        return CronJob::where('key', 'scheduled_backup')->first();
    }

    /**
     * مزامنة مهمة النسخ المجدول مع الإعدادات المحفوظة.
     *
     * @return string|null رسالة إضافية تُعرَض للمسؤول حين يحتاج الأمر توضيحاً
     */
    protected function syncScheduledJob(BackupSetting $settings, CronJobService $cronJobs): ?string
    {
        $job = $this->scheduledJob();

        if (! $job) {
            return __('settings.backup.job_missing_warning').'php artisan db:seed --class=CronJobsSeeder';
        }

        // «يدوي فقط» لا جدولة له: تُعطَّل المهمة وتبقى جدولتها كما هي حتى
        // يعود التفعيل فيجدها كما تركها
        if (! $settings->is_enabled || $settings->frequency === 'manual') {
            $cronJobs->disable($job, auth()->id());

            return $settings->is_enabled
                ? __('settings.backup.job_manual')
                : __('settings.backup.job_off');
        }

        $scheduleType = $settings->frequency === 'custom' ? CronJob::SCHEDULE_CRON : $settings->frequency;

        try {
            $cronJobs->update($job, [
                'schedule_type' => $scheduleType,
                // العمود من نوع `time` فيصل بصيغة H:i:s — والبنّاء يقبلها
                'schedule_time' => $settings->run_time,
                'schedule_day' => $settings->run_day,
                'cron_expression' => $settings->cron_expression,
                'status' => CronJob::STATUS_ACTIVE,
            ], auth()->id());
        } catch (\InvalidArgumentException $e) {
            // الإعدادات حُفظت بالفعل؛ فشل المزامنة يجب أن يُقال صراحةً
            // وإلا ظنّ المسؤول أن الجدولة سرت وهي لم تتغيّر
            Log::warning('تعذّرت مزامنة مهمة النسخ المجدول', ['error' => $e->getMessage()]);

            return __('settings.backup.job_sync_failed', ['error' => $e->getMessage()]);
        }

        return __('settings.backup.job_schedule_now', [
            'schedule' => CronScheduleParser::describe($job->refresh()->cron_expression),
        ]);
    }

    /**
     * إحصاءات لوحة النسخ الاحتياطي.
     *
     * @return array<string, mixed>
     */
    protected function stats(?BackupSetting $settings = null): array
    {
        $settings ??= BackupSetting::current();

        $lastSuccessful = Backup::available()->latest('completed_at')->first();
        $usedBytes = $this->backups->storageUsedBytes();
        $limitBytes = $settings->max_storage_mb ? $settings->max_storage_mb * 1048576 : null;

        return [
            'total' => Backup::count(),
            'available' => Backup::available()->count(),
            'failed' => Backup::where('status', Backup::STATUS_FAILED)->count(),
            'last_successful' => $lastSuccessful,
            // «آخر نسخة منذ كذا» هو السؤال الوحيد الذي يهمّ عند وقوع عطل
            'age_hours' => $lastSuccessful?->completed_at
                ? (int) $lastSuccessful->completed_at->diffInHours(now())
                : null,
            'last_failed' => Backup::where('status', Backup::STATUS_FAILED)->latest('created_at')->first(),
            'used_bytes' => $usedBytes,
            'used_human' => Backup::formatBytes($usedBytes),
            'limit_bytes' => $limitBytes,
            'usage_percent' => $limitBytes ? min(100, (int) round($usedBytes / $limitBytes * 100)) : null,
            'restores' => BackupRestore::count(),
        ];
    }
}
