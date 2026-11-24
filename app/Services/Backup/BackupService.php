<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\BackupSetting;
use App\Models\CronJob;
use App\Services\SystemAlertService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * تنفيذ النسخ الاحتياطي وتسجيله.
 *
 * `spatie/laravel-backup` يقرأ كل شيء من `config/backup.php`، وهو ملف
 * ساكن. هذه الطبقة تسقط إعدادات `backup_settings` فوق الإعداد المنشور
 * قبل كل تشغيل، فيصبح ما يختاره المسؤول من الواجهة هو ما يُنفَّذ فعلاً —
 * دون تعديل ملف ولا `.env` عند كل تغيير.
 *
 * ما يبقى في `.env` عمداً: `BACKUP_ARCHIVE_PASSWORD` و مسار `mysqldump`
 * وبيانات اعتماد التخزين السحابي. أسرار ومسارات خادم، لا إعدادات نظام.
 */
class BackupService
{
    /** يمنع تشغيلين متزامنين — نسختان معاً تُفرغان القرص وتتعارضان. */
    private const LOCK_KEY = 'backup:running';

    private const LOCK_SECONDS = 3600;

    /** تشغيلٌ لم يكتب في مجلّده المؤقّت طوالَ هذه المدّة يُعدّ ميّتاً. */
    private const STALE_SECONDS = 300;

    /**
     * إنشاء نسخة احتياطية الآن.
     *
     * @param  string|null  $type  database|files|both — أو null لاستعمال المضبوط في الإعدادات
     * @param  string  $trigger  manual|scheduled|pre_restore
     * @param  array{mode?:string,included?:array<int,string>,excluded?:array<int,string>}|null  $tableSelection
     *                                                                                                            اختيار جداول يخصّ هذه النسخة وحدها — null يعني اتّباع الإعدادات
     */
    public function run(
        ?string $type = null,
        string $trigger = Backup::TRIGGER_MANUAL,
        ?int $userId = null,
        ?CronJob $cronJob = null,
        ?array $tableSelection = null
    ): Backup {
        $settings = BackupSetting::current();
        $type = $this->resolveType($type ?? $settings->backup_type);

        // نسخة الأمان قبل الاسترجاع كاملة دائماً ولا تقبل تخصيصاً: هي
        // طريق العودة الوحيد، ونسخة أمان ناقصة جداول لا تُعيد ما محاه
        // الاسترجاع. يُفرَض هنا لا في المستدعي كي لا يسقط سهواً
        if ($trigger === Backup::TRIGGER_PRE_RESTORE) {
            $tableSelection = ['mode' => 'all'];
        }

        // نسخة الملفات وحدها لا جداول فيها — لولا هذا لوُسمت «جزئية» في
        // السجلّ بسبب اختيار لم يُطبَّق أصلاً
        if ($type === 'files') {
            $tableSelection = ['mode' => 'all'];
        }

        $scope = $this->resolveTableScope($settings, $tableSelection);

        // جدول السجلّ نفسه قد يكون معدوماً: قاعدةٌ مستوردة من نظام أقدم من
        // ميزة النسخ الاحتياطي تصل إلى شاشة تحديث النظام وترحيلاتُ النسخ بين
        // المعلَّق، وأوّلُ ما تطلبه تلك الشاشة نسخةٌ قبل الترحيل. فلو توقّف
        // النسخُ على وجود سجلّه لتعذّر في اللحظة الوحيدة التي لا يجوز أن
        // يتعذّر فيها — ولبقيت القاعدة القديمة تُرحَّل بلا طريق عودة.
        //
        // الأرشيف يُكتب على القرص في الحالين؛ الفارق أنّ الصفّ لا يُحفظ،
        // ويُتبنّى الملفُّ بعد الترحيل بـ`reconcile()`.
        $bookkeeping = Schema::hasTable((new Backup)->getTable());

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get() && ! $this->reclaimOrphanedLock($bookkeeping, $lock)) {
            // يُسجَّل صفّ فاشل لا استثناء صامت: المسؤول ضغط زراً ويجب أن
            // يرى لماذا لم يحدث شيء
            return $this->newRecord($bookkeeping, [
                'type' => $type,
                'table_mode' => $scope['mode'],
                'tables' => $scope['tables'],
                'trigger' => $trigger,
                'disk' => $settings->disk ?: 'local',
                'status' => Backup::STATUS_FAILED,
                'started_at' => now(),
                'completed_at' => now(),
                'duration_ms' => 0,
                'error_message' => 'هناك عملية نسخ احتياطي جارية بالفعل — انتظر انتهاءها.',
                'created_by' => $userId,
                'cron_job_id' => $cronJob?->id,
            ]);
        }

        // الإدراج بعد أخذ القفل وخارج `try` أدناه، فلو سقط ولم يُفلَت القفلُ
        // هنا لبقي ساعةً كاملة ورُفضت كلُّ نسخةٍ بعده بـ«جارية بالفعل» ولا
        // نسخةَ جارية — وهكذا حُبست شاشةُ تحديث النظام على الخادم
        try {
            $record = $this->newRecord($bookkeeping, [
                'type' => $type,
                'table_mode' => $scope['mode'],
                'tables' => $scope['tables'],
                'trigger' => $trigger,
                'disk' => $settings->disk ?: 'local',
                // يُسجَّل قبل التشغيل لا بعده: لو انهارت العملية بعد كتابة
                // الأرشيف وقبل تحديث الصفّ، بقي على القرص ملف معشّى وصفٌّ يقول
                // إنه مفتوح — فيفشل الاسترجاع بسبب لا يدلّ عليه شيء
                'is_encrypted' => $this->encryptionPassword($settings) !== null,
                'status' => Backup::STATUS_RUNNING,
                'started_at' => now(),
                'created_by' => $userId,
                'cron_job_id' => $cronJob?->id,
            ]);
        } catch (\Throwable $e) {
            $lock->release();

            throw $e;
        }

        $startedAt = microtime(true);

        // انقطاعُ الطلب من الخادم (مهلة، خطأ قاتل) لا يمرّ بـ`finally`، فيبقى
        // القفلُ محجوزاً والصفُّ «قيد التنفيذ» ساعةً ويُرفض كلُّ تحديثٍ بعده.
        // دالّةُ الإغلاق تقع في الحالين، فتُغلق ما تركه الانقطاع بسببه
        $settled = false;
        register_shutdown_function(function () use (&$settled, $record, $lock, $startedAt) {
            if ($settled) {
                return;
            }

            $reason = error_get_last()['message'] ?? 'أُوقفت العملية قبل انتهائها.';

            try {
                $this->persist($record, [
                    'status' => Backup::STATUS_FAILED,
                    'completed_at' => now(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error_message' => $this->trim('انقطع تنفيذ النسخة قبل انتهائها: '.$reason, 2000),
                ]);
                Log::error('انقطع تنفيذ نسخة احتياطية', ['backup_id' => $record->id, 'reason' => $reason]);
            } finally {
                $lock->release();
            }
        });

        try {
            if (! app()->runningInConsole()) {
                // النسخ الاحتياطي أطول بكثير من مهلة الطلب الافتراضية
                @set_time_limit(self::LOCK_SECONDS);
                // انقطاعُ المتصفّح أو الوسيط لا يوقف تفريغاً في منتصفه
                ignore_user_abort(true);
            }

            // قبل أي عملية ابنة: `mysqldump` يُشغَّل داخل `backup:run`
            static::ensureProcessEnvironment();

            $this->applyConfig($settings, $type, $scope);

            $before = $this->archiveIndex($settings->disk ?: 'local');

            $buffer = new BufferedOutput;
            $exitCode = Artisan::call('backup:run', $this->flagsFor($type), $buffer);
            $output = $buffer->fetch();

            if ($exitCode !== 0) {
                throw new \RuntimeException(
                    $this->extractError($output) ?: 'انتهى أمر النسخ الاحتياطي بشفرة خروج '.$exitCode
                );
            }

            $archive = $this->newArchiveSince($before, $settings->disk ?: 'local');

            // الأمر نجح لكن لم يُنتج ملفاً جديداً — نجاح ظاهري لا يجوز
            // تسجيله نجاحاً، وإلا اطمأنّ المسؤول إلى نسخة غير موجودة
            if ($archive === null) {
                throw new \RuntimeException('لم يُنشأ ملف نسخة احتياطية جديد رغم انتهاء الأمر بنجاح.');
            }

            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            $this->persist($record, [
                'status' => Backup::STATUS_COMPLETED,
                'completed_at' => now(),
                'duration_ms' => $duration,
                'path' => $archive['path'],
                'file_name' => basename($archive['path']),
                'size_bytes' => $archive['size'],
                'output' => $this->trim($output),
                'error_message' => null,
            ]);

            $this->notifySuccess($settings, $record->refresh());

            return $record;
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            Log::error('فشل إنشاء نسخة احتياطية', [
                'backup_id' => $record->id,
                'type' => $type,
                'trigger' => $trigger,
                'error' => $e->getMessage(),
            ]);

            $this->persist($record, [
                'status' => Backup::STATUS_FAILED,
                'completed_at' => now(),
                'duration_ms' => $duration,
                'error_message' => $this->trim($e->getMessage(), 2000),
                'output' => $this->trim($e->getTraceAsString()),
            ]);

            if ($settings->notify_on_failure) {
                SystemAlertService::send(
                    SystemAlertService::BACKUP_FAILED,
                    'فشلت النسخة الاحتياطية',
                    'النوع: '.$record->typeLabel().' — السبب: '.$this->trim($e->getMessage(), 300)
                );
            }

            return $record->refresh();
        } finally {
            $settled = true;
            $lock->release();
        }
    }

    /**
     * صفُّ السجلّ — محفوظاً إن وُجد جدولُه، وإلّا في الذاكرة وحدها.
     *
     * المستدعي يقرأ `status` و`file_name` من العائد في الحالين، ويعرف
     * بـ`exists` أنّ النسخة أُخذت ولم تُسجَّل.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function newRecord(bool $bookkeeping, array $attributes): Backup
    {
        if ($bookkeeping) {
            try {
                return Backup::create($attributes);
            } catch (QueryException $e) {
                // 1452: المفتاحُ الأجنبي يرفض المُنشئ أو مهمّةَ الجدولة — على
                // قاعدةٍ مستوردة رفض `created_by` مستخدماً مسجَّلَ الدخول فعلاً.
                // مَن ضغط الزرّ معلومةٌ للسجلّ، لا شرطٌ لأخذ النسخة
                if (($e->errorInfo[1] ?? null) !== 1452) {
                    throw $e;
                }

                Log::warning('سُجّلت النسخة الاحتياطية بلا مُنشئ: رفضه المفتاح الأجنبي', [
                    'created_by' => $attributes['created_by'] ?? null,
                    'cron_job_id' => $attributes['cron_job_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);

                return Backup::create(['created_by' => null, 'cron_job_id' => null] + $attributes);
            }
        }

        // `creating` لا يقع بلا حفظ، وعليه يُولَّد المعرّف — وهو ما تعرضه
        // الرسائل ويُعرَف به الملفّ
        return new Backup($attributes + ['uuid' => (string) Str::uuid()]);
    }

    /**
     * استعادةُ قفلٍ لا نسخةَ خلفه.
     *
     * كلُّ نسخةٍ جارية تكتب صفَّها `running` فورَ أخذ القفل، فقفلٌ محجوز
     * بلا صفٍّ جارٍ في مدّته هو قفلٌ تسرّب من تشغيلٍ سقط قبل أن يكتب صفَّه —
     * ولولا هذا لبقي ساعةً يرفض كلَّ نسخة، ومعها كلَّ تحديثٍ للنظام.
     *
     * وصفٌّ «قيد التنفيذ» ليس دليلاً على الحياة: تشغيلٌ قتله الخادم بـSIGKILL
     * لا تلحقه دالّةُ الإغلاق فيترك صفَّه كما هو. الحيُّ يكتب في مجلّد
     * `backup-temp` بلا انقطاع (التفريغ ثم الضغط) ويحذفه حين ينتهي، فصفٌّ
     * تجاوز `STALE_SECONDS` ولم يُكتب في ذلك المجلّد شيءٌ طوالَها تشغيلٌ ميّت.
     *
     * بلا جدول سجلٍّ لا دليلَ على شيء، فيبقى القفلُ حاكماً.
     */
    private function reclaimOrphanedLock(bool $bookkeeping, Lock $lock): bool
    {
        if (! $bookkeeping) {
            return false;
        }

        $running = Backup::query()
            ->whereIn('status', [Backup::STATUS_PENDING, Backup::STATUS_RUNNING])
            ->where('created_at', '>=', now()->subSeconds(self::LOCK_SECONDS))
            ->get();

        $lastWrite = $this->lastTemporaryWrite();

        foreach ($running as $row) {
            $age = now()->getTimestamp() - ($row->started_at ?? $row->created_at)->getTimestamp();
            $idle = $lastWrite === null ? $age : time() - $lastWrite;

            if ($age < self::STALE_SECONDS || $idle < self::STALE_SECONDS) {
                return false;
            }
        }

        foreach ($running as $row) {
            $row->update([
                'status' => Backup::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => 'انقطعت النسخة قبل انتهائها — أوقف الخادمُ العمليةَ على الأرجح (مهلة الطلب)، ولم يُكتب منها شيءٌ منذ '
                    .intdiv(self::STALE_SECONDS, 60).' دقائق.',
            ]);
        }

        Log::warning('أُفلت قفل نسخ احتياطي بلا نسخة جارية خلفه', ['key' => self::LOCK_KEY]);

        $lock->forceRelease();

        return $lock->get();
    }

    /** آخرُ كتابةٍ في مجلّد spatie المؤقّت — null إن لم يبقَ فيه شيء. */
    private function lastTemporaryWrite(): ?int
    {
        $directory = config('backup.backup.temporary_directory') ?: storage_path('app/backup-temp');

        if (! is_dir($directory)) {
            return null;
        }

        clearstatcache();

        $latest = null;
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($entries as $entry) {
            $latest = max($latest ?? 0, $entry->getMTime());
        }

        return $latest;
    }

    /**
     * كتابة نتيجة التشغيل في الصفّ — حفظاً أو ملءاً حسب وجود الجدول.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persist(Backup $record, array $attributes): void
    {
        $record->exists ? $record->update($attributes) : $record->fill($attributes);
    }

    /**
     * ضمان وصول متغيّرات نظام ويندوز الأساسية إلى العمليات الابنة.
     *
     * `mysqldump` و`mysql` يُشغَّلان عبر `Symfony\Component\Process`، وهو
     * على ويندوز يبني بيئة الابن هكذا:
     *
     *     $env = getenv();
     *     $env = array_intersect_ukey($env, $_SERVER, 'strcasecmp') ?: $env;
     *
     * أي أنه يُبقي من بيئة العملية ما كان مفتاحه موجوداً في `$_SERVER`
     * أيضاً. وفي الطرفية يحمل `$_SERVER` البيئة كاملة فيمرّ `SystemRoot`،
     * أمّا تحت Apache/mod_php فـ`$_SERVER` مملوء بمتغيّرات الطلب وحدها،
     * فيسقط `SystemRoot` من بيئة الابن.
     *
     * وبدونه لا يستطيع Winsock تحميل مزوّداته (تُحلّ مساراتها نسبةً إلى
     * `%SystemRoot%\System32`)، فيفشل `mysqldump` عند أول محاولة اتصال:
     *
     *     mysqldump: Got error: 2004: "Can't create TCP/IP socket (10106)"
     *
     * وهذا هو سبب نجاح النسخ من الطرفية وفشله من الواجهة على الخادم نفسه.
     * التصحيح هنا لا في `php.ini` لأنه يخصّ عمليات هذا النظام الابنة، ولا
     * يجوز أن يتوقّف عمل النسخ الاحتياطي على ضبط بيئة الخادم يدوياً.
     */
    public static function ensureProcessEnvironment(): void
    {
        if (! str_starts_with(strtoupper(PHP_OS_FAMILY), 'WIN')) {
            return;
        }

        // `PATHEXT` و`COMSPEC` تلزمان لتشغيل الأوامر أصلاً، و`TEMP`/`TMP`
        // يكتب فيهما العميل ملفاته المؤقّتة
        foreach (['SystemRoot', 'windir', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP'] as $name) {
            $value = getenv($name);

            if ($value === false || $value === '') {
                continue;
            }

            // المقارنة بلا حساسية لحالة الأحرف: ويندوز يخلط بين
            // `SystemRoot` و`SYSTEMROOT`، و`Process` يقارن بـ`strcasecmp`
            foreach (array_keys($_SERVER) as $existing) {
                if (strcasecmp((string) $existing, $name) === 0) {
                    continue 2;
                }
            }

            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
        }
    }

    /**
     * إسقاط إعدادات قاعدة البيانات فوق إعداد الحزمة قبل التشغيل.
     *
     * @param  array{mode:string,tables:array<int,string>,excluded:array<int,string>}|null  $scope
     */
    public function applyConfig(BackupSetting $settings, string $type, ?array $scope = null): void
    {
        $disk = $settings->disk ?: 'local';

        $password = $this->encryptionPassword($settings);

        $overrides = [
            'backup.backup.destination.disks' => [$disk],

            // ---- مجلَّد المنشأة ----
            // الحزمة تكتب داخل مجلَّد باسم `backup.name`. إسقاطه هنا يجعل
            // نسخ كل منشأة في مجلَّدها الخاص بدل خلطها في مجلَّد واحد
            'backup.backup.name' => $settings->archiveFolder(),

            // ---- تعشية الأرشيف ----
            // `EncryptBackupArchive` يقرأ هذين المفتاحين من الإعداد لحظة
            // إنشاء الملف، فإسقاطهما هنا يجعل ما يختاره المسؤول سارياً في
            // التشغيلة الجارية. القيمة null تُطفئ التعشية صراحةً حتى لا
            // تتسرّب كلمةٌ من تشغيلة سابقة في العملية نفسها
            'backup.backup.password' => $password,
            'backup.backup.encryption' => $password === null ? false : 'default',
        ];

        // ---- اختيار الجداول ----
        // `DbDumperFactory` يحوّل كل مفتاح تحت `dump` إلى نداء دالّة على
        // المُفرِّغ، فـ `includeTables` هنا تصبح `->includeTables([...])`.
        // المفتاحان يُصفَّران دائماً حتى لا يتسرّب اختيارٌ من تشغيلة سابقة
        // في العملية نفسها إلى نسخة يُفترض أنها كاملة
        $connection = config('database.default');
        $dumpKey = 'database.connections.'.$connection.'.dump';
        $dump = (array) config($dumpKey, []);
        unset($dump['includeTables'], $dump['excludeTables']);

        if ($scope !== null && $type !== 'files') {
            if ($scope['mode'] === 'include' && $scope['tables'] !== []) {
                $dump['includeTables'] = $scope['tables'];
            } elseif ($scope['mode'] === 'exclude' && ($scope['excluded'] ?? []) !== []) {
                $dump['excludeTables'] = $scope['excluded'];
            }
        }

        $overrides[$dumpKey] = $dump;

        if ($settings->includesFiles()) {
            $include = $this->resolvePaths($settings->included_paths) ?: $this->defaultIncludes();
            $exclude = $this->resolvePaths($settings->excluded_paths) ?: $this->defaultExcludes();

            $overrides['backup.backup.source.files.include'] = $include;
            $overrides['backup.backup.source.files.exclude'] = $exclude;
        }

        // نسخة قاعدة البيانات وحدها يجب ألا تجرّ ملفات معها
        if ($type === 'database') {
            $overrides['backup.backup.source.files.include'] = [];
        }

        config($overrides);
    }

    /**
     * كلمة تعشية الأرشيف السارية، أو null إن كانت التعشية مطفأة.
     *
     * الإعدادات أولاً ثم `.env`: الشاشة هي المصدر المعتمَد، ويبقى متغيّر
     * البيئة يعمل للمنشآت التي ضبطته قبل وجودها. التعشية تشمل الأرشيف
     * كلّه — تفريغ قاعدة البيانات والمرفقات معاً — لأنها تقع على ملف
     * الـ ZIP بعد بنائه لا على محتوى بعينه.
     *
     * الخوارزمية AES-256 حين يدعمها `ZipArchive`؛ ودونه يمتنع
     * `EncryptBackupArchive` عن التعشية بدل أن يستعمل خوارزمية أضعف.
     */
    public function encryptionPassword(?BackupSetting $settings = null): ?string
    {
        $settings ??= BackupSetting::current();

        $password = $settings->archivePassword();

        if ($password !== null && $password !== '') {
            return $password;
        }

        // `?: null` مقصود: `BACKUP_ARCHIVE_PASSWORD=` فارغاً في `.env` يعطي
        // نصّاً فارغاً، وتعشية بكلمة فارغة تنهار عند `ZipArchive::close()`
        // فيبقى الأرشيف ناقصاً
        $fromEnv = config('backup.backup.password');

        return $fromEnv ?: null;
    }

    /**
     * حسم الجداول التي ستُنسَخ فعلاً.
     *
     * الأسماء تُصفَّى عبر `BackupTableRegistry` لا تُقبَل كما وردت: أمر
     * `mysqldump` يُبنى نصّاً ويُشغَّل عبر الصدفة، فاسم غير مطابق لجدول
     * قائم لا يجوز أن يصل إليه. التصفية تُعاد هنا وقت التنفيذ حتى لو
     * صُفّيت وقت الحفظ — الجدول قد يكون حُذف من القاعدة بينهما.
     *
     * @param  array{mode?:string,included?:array<int,string>,excluded?:array<int,string>}|null  $override
     * @return array{mode:string,tables:array<int,string>,excluded:array<int,string>}
     */
    protected function resolveTableScope(BackupSetting $settings, ?array $override = null): array
    {
        $mode = $override['mode'] ?? $settings->table_mode ?? 'all';

        if (! array_key_exists($mode, BackupSetting::TABLE_MODES)) {
            $mode = 'all';
        }

        $included = $override['included'] ?? $settings->included_tables ?? [];
        $excluded = $override['excluded'] ?? $settings->excluded_tables ?? [];

        $registry = app(BackupTableRegistry::class);
        $included = $registry->filter($included);
        $excluded = $registry->filter($excluded);

        // اختيار فارغ يعني «لا جدول» — وهو نسخة بلا محتوى. يُرجَع إلى
        // «الكل» لا يُنفَّذ فارغاً: نسخة كاملة زائدة أهون من نسخة خاوية
        // يظنّها المسؤول سليمة
        if ($mode === 'include' && $included === []) {
            Log::warning('اختيار جداول فارغ في النسخ الاحتياطي — رُجِع إلى نسخة كاملة.');
            $mode = 'all';
        }

        if ($mode === 'exclude' && $excluded === []) {
            $mode = 'all';
        }

        return [
            'mode' => $mode,
            // `tables` هو ما سيُنسَخ فعلاً في الحالتين — فقارئ السجلّ لاحقاً
            // يعرف محتوى النسخة دون أن يعيد حساب الفرق
            'tables' => $mode === 'all' ? [] : $registry->resolve($mode, $included, $excluded),
            'excluded' => $excluded,
        ];
    }

    /**
     * أعلام أمر `backup:run` المقابلة للنوع.
     *
     * `--disable-notifications` ثابت في كل تشغيلة: إشعارات هذا النظام
     * تُرسَل من `notifySuccess` وممرّ الفشل أدناه عبر `SystemAlertService`،
     * فإشعارات الحزمة تكرار. والأهمّ أنها تُطلَق داخل عملية النسخ، وأي
     * خلل فيها — بوابة بريد معطّلة أو إعداد إشعارات ناقص — يخرج استثناءً
     * من `backup:run` يحلّ محلّ سبب الفشل الحقيقي فيضيع. العَلَم يمنع
     * إطلاق الأحداث أصلاً، فلا يبقى للإعداد أثر على نتيجة النسخ.
     *
     * @return array<string, bool>
     */
    protected function flagsFor(string $type): array
    {
        $flags = ['--disable-notifications' => true];

        return match ($type) {
            'database' => $flags + ['--only-db' => true],
            'files' => $flags + ['--only-files' => true],
            default => $flags,
        };
    }

    /**
     * فهرس الأرشيفات الموجودة على القرص: المسار ← الحجم.
     *
     * تُقرأ من وجهة الحزمة نفسها لا بمسح المجلَّد يدوياً — فتبقى صحيحة
     * مهما تغيّرت طريقة الحزمة في تسمية الملفات.
     *
     * @return array<string, int>
     */
    protected function archiveIndex(string $disk): array
    {
        try {
            $index = [];

            // كل المجلَّدات لا المجلَّد الجاري: نسخةٌ أُخذت قبل ضبط المنشأة
            // ما زالت على القرص، وإغفالها يجعلها ملفاً لا يعرفه أحد
            foreach (BackupSetting::current()->archiveFolders() as $folder) {
                foreach (BackupDestination::create($disk, $folder)->backups() as $archive) {
                    $index[$archive->path()] = (int) $archive->sizeInBytes();
                }
            }

            return $index;
        } catch (\Throwable $e) {
            Log::warning('تعذّرت قراءة وجهة النسخ الاحتياطية', [
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * الملف الذي أنتجه التشغيل الأخير — بفارق المجموعات لا بـ«الأحدث».
     *
     * `BackupCollection::newest()` يرتّب باسم الملف، والحزمة تسمّيه بساعة
     * البيئة وقت التشغيل. وهذا التطبيق يغيّر المنطقة الزمنية داخل الطلب
     * (`LanguageSettings::apply()` يستدعي `date_default_timezone_set` حسب
     * لغة النظام)، بينما تبقى الطرفية على `app.timezone`. فينتج فارق ساعات
     * بين اسم أرشيف أُخذ من الواجهة وآخر أُخذ من الطرفية، فلا يكون الأحدث
     * إنشاءً هو الأحدث اسماً — وتُسجَّل نسخة سليمة تماماً فاشلةً بحجّة
     * «لم يُنشأ ملف جديد».
     *
     * فارق المسارات لا يعتمد على ترتيب ولا تسمية ولا ساعة.
     *
     * @param  array<string, int>  $before
     * @return array{path:string,size:int}|null
     */
    protected function newArchiveSince(array $before, string $disk): ?array
    {
        $after = $this->archiveIndex($disk);
        $new = array_diff_key($after, $before);

        if ($new === []) {
            return null;
        }

        // تشغيلة واحدة تنتج ملفاً واحداً؛ وإن ظهر أكثر (تشغيل متوازٍ من
        // خارج النظام) فالأكبر حجماً أقربها إلى أن يكون نسختنا الكاملة
        arsort($new);
        $path = (string) array_key_first($new);

        return ['path' => $path, 'size' => $new[$path]];
    }

    // ==================== إدارة النسخ ====================

    /**
     * حذف نسخة: الملف من القرص والصفّ يبقى موسوماً بأنه حُذف.
     */
    public function delete(Backup $backup): void
    {
        if ($backup->isRunning()) {
            throw new \RuntimeException('لا يمكن حذف نسخة قيد التنفيذ.');
        }

        if ($backup->is_locked) {
            throw new \RuntimeException('هذه النسخة محميّة من الحذف — أزِل الحماية أولاً.');
        }

        if ($backup->path) {
            try {
                Storage::disk($backup->disk)->delete($backup->path);
            } catch (\Throwable $e) {
                // الملف قد يكون حُذف من خارج النظام؛ يُسجَّل ويستمرّ الوسم
                // حتى لا يبقى الصفّ يدّعي وجود ملف غير موجود
                Log::warning('تعذّر حذف ملف النسخة الاحتياطية', [
                    'backup_id' => $backup->id,
                    'path' => $backup->path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $backup->update(['file_deleted_at' => now()]);
    }

    /**
     * المسار المطلق للملف على نظام الملفات — للتنزيل والاسترجاع.
     *
     * الأقراص البعيدة (S3) لا مسار محلياً لها، فتُرجَع null ويتكفّل
     * المستدعي بالبثّ بدل التنزيل المباشر.
     */
    public function localPath(Backup $backup): ?string
    {
        if (! $backup->isAvailable()) {
            return null;
        }

        try {
            $disk = Storage::disk($backup->disk);

            if (! $disk->exists($backup->path)) {
                return null;
            }

            $prefix = config('filesystems.disks.'.$backup->disk.'.root');

            return $prefix ? rtrim($prefix, '/\\').DIRECTORY_SEPARATOR.$backup->path : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** إجمالي المساحة التي تشغلها النسخ الموجودة فعلاً. */
    public function storageUsedBytes(): int
    {
        return (int) Backup::available()->sum('size_bytes');
    }

    /**
     * مطابقة القرص مع السجلّ.
     *
     * القرص والجدول يفترقان في حالات واقعية: نسخة أُخذت بـ `backup:run`
     * مباشرة من الطرفية، أو ملف حُذف يدوياً من الخادم، أو تشغيل انهار بعد
     * كتابة الملف وقبل تحديث صفّه. الفارق خطر ملموس — ملف لا يعرفه النظام
     * لا يشمله التنظيف فيبقى يستهلك المساحة أبداً، وصفّ بلا ملف يَعِد
     * المسؤول بنسخة لا وجود لها.
     *
     * @return array{adopted:int,missing:int}
     */
    public function reconcile(?string $disk = null): array
    {
        $disk ??= BackupSetting::current()->disk ?: 'local';
        $result = ['adopted' => 0, 'missing' => 0];

        try {
            $onDisk = collect();

            foreach (BackupSetting::current()->archiveFolders() as $folder) {
                $onDisk = $onDisk->concat(BackupDestination::create($disk, $folder)->backups()->toArray());
            }
        } catch (\Throwable $e) {
            Log::warning('تعذّرت مطابقة النسخ الاحتياطية مع القرص', [
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);

            return $result;
        }

        $knownPaths = Backup::whereNull('file_deleted_at')
            ->where('disk', $disk)
            ->pluck('path')
            ->filter()
            ->all();

        // ---- ملفات لا يعرفها الجدول ----
        foreach ($onDisk as $archive) {
            if (in_array($archive->path(), $knownPaths, true)) {
                continue;
            }

            Backup::create([
                'type' => 'database',
                'trigger' => Backup::TRIGGER_MANUAL,
                'disk' => $disk,
                'path' => $archive->path(),
                'file_name' => basename($archive->path()),
                'size_bytes' => (int) $archive->sizeInBytes(),
                'status' => Backup::STATUS_COMPLETED,
                // تاريخ الملف لا تاريخ الاكتشاف — وإلا بدت نسخة قديمة جديدة
                'started_at' => $archive->date(),
                'completed_at' => $archive->date(),
                'created_at' => $archive->date(),
                'updated_at' => now(),
                'output' => 'صفّ أُنشئ بمطابقة القرص — لم يكن للملف سجلّ.',
            ]);

            $result['adopted']++;
        }

        // ---- صفوف بلا ملفات ----
        $diskPaths = $onDisk->map(fn ($a) => $a->path())->all();

        foreach (Backup::available()->where('disk', $disk)->get() as $record) {
            if (! in_array($record->path, $diskPaths, true)) {
                $record->update(['file_deleted_at' => now()]);
                $result['missing']++;
            }
        }

        return $result;
    }

    // ==================== الداخل ====================

    protected function notifySuccess(BackupSetting $settings, Backup $record): void
    {
        if ($settings->notify_on_success) {
            SystemAlertService::send(
                SystemAlertService::BACKUP_COMPLETED,
                'اكتملت النسخة الاحتياطية',
                'النوع: '.$record->typeLabel().' — الحجم: '.$record->humanSize()
                    .' — المدّة: '.$record->humanDuration()
            );
        }

        // تنبيه المساحة مستقلّ عن تنبيه النجاح: قرص شارف على الامتلاء
        // يجب أن يُعرَف حتى لو أُسكتت إشعارات النجاح
        if ($settings->notify_on_storage_full && $settings->max_storage_mb) {
            $usedMb = (int) round($this->storageUsedBytes() / 1048576);

            if ($usedMb >= $settings->max_storage_mb) {
                SystemAlertService::send(
                    SystemAlertService::BACKUP_STORAGE_FULL,
                    'مساحة النسخ الاحتياطية تجاوزت الحدّ',
                    'المستخدَم '.$usedMb.' م.ب من أصل '.$settings->max_storage_mb
                        .' م.ب. راجع سياسة الاحتفاظ أو احذف نسخاً قديمة.'
                );
            }
        }
    }

    /** @param  array<int, string>|null  $paths */
    protected function resolvePaths(?array $paths): array
    {
        if (empty($paths)) {
            return [];
        }

        $resolved = [];

        foreach ($paths as $path) {
            $path = trim((string) $path);

            if ($path === '') {
                continue;
            }

            // المسارات نسبية لجذر المشروع دائماً: مسار مطلق من الواجهة
            // يعني قراءة أي مكان على الخادم
            $absolute = base_path(ltrim(str_replace('\\', '/', $path), '/'));

            if (! str_starts_with($this->realish($absolute), $this->realish(base_path()))) {
                Log::warning('رُفض مسار نسخ احتياطي خارج جذر المشروع', ['path' => $path]);

                continue;
            }

            if (file_exists($absolute)) {
                $resolved[] = $absolute;
            }
        }

        return $resolved;
    }

    /** @return array<int, string> */
    protected function defaultIncludes(): array
    {
        // الافتراضي هو ما يملكه المستخدمون فعلاً: المرفقات والمرفوعات —
        // لا جذر المشروع كلّه كما في إعداد الحزمة الافتراضي
        return array_values(array_filter([
            storage_path('app/public'),
            public_path('uploads'),
        ], 'file_exists'));
    }

    /** @return array<int, string> */
    protected function defaultExcludes(): array
    {
        return [
            base_path('vendor'),
            base_path('node_modules'),
            storage_path('framework'),
            storage_path('logs'),
            storage_path('app/backup-temp'),
        ];
    }

    protected function resolveType(?string $type): string
    {
        return in_array($type, ['database', 'files', 'both'], true) ? $type : 'database';
    }

    /** انتزاع سطر الخطأ من مخرجات الأمر — أوضح من «شفرة خروج 1». */
    protected function extractError(string $output): ?string
    {
        // كان النمط ينتهي عند أول سطر (`$` مع العَلَم `m`)، فلا يصل إلى
        // المسؤول إلا «The dump process failed with a none successful
        // exitcode» — جملة صحيحة وعديمة الفائدة. السبب الفعلي يأتي بعدها
        // بأسطر تحت عنوان `Error Output`
        if (! preg_match('/Backup failed because:?\s*(.+)/si', $output, $m)) {
            return null;
        }

        $body = trim($m[1]);

        // سطر `mysqldump: ...` أو ما تحت `Error Output` هو الرسالة التي
        // تدلّ على ما يجب فعله فعلاً
        if (preg_match('/Error Output\s*\n=+\s*\n(.+?)(?:\n\s*\n|$)/s', $body, $err)) {
            $detail = trim($err[1]);

            if ($detail !== '') {
                $headline = trim(strtok($body, "\n") ?: '');

                return $this->collapse($headline === '' ? $detail : $headline.' — '.$detail);
            }
        }

        return $this->collapse($body);
    }

    /**
     * ضغط رسالة متعدّدة الأسطر إلى سطر واحد صالح للعرض في تنبيه.
     *
     * أثر المكدّس يُقطع: مكانه `output` وسجلّ الأخطاء، لا تنبيهٌ يقرأه
     * المسؤول ليعرف ما يفعل. إبقاؤه يدفن السطر المفيد خلف عشرة مسارات ملفّات.
     */
    private function collapse(string $message, int $limit = 400): string
    {
        // `#0 ` أو `#12 ` في أول سطر = بداية أثر المكدّس
        $message = (string) preg_replace('/^#\d+\s.*$/ms', '', $message);
        $message = trim((string) preg_replace('/\s*\n\s*/', ' ', $message));
        $message = trim($message, " .\t");

        return mb_strlen($message) > $limit ? mb_substr($message, 0, $limit).' …' : $message;
    }

    private function realish(string $path): string
    {
        return rtrim(str_replace('\\', '/', realpath($path) ?: $path), '/');
    }

    private function trim(?string $value, int $limit = 60000): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit)."\n… (اقتُطع)"
            : $value;
    }
}
