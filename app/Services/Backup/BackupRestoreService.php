<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\BackupSetting;
use App\Services\SystemAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * استرجاع قاعدة البيانات من نسخة احتياطية.
 *
 * هذه العملية الوحيدة في النظام التي تكتب فوق قاعدة البيانات كاملة، فهي
 * مبنيّة على ثلاث قواعد لا تُخترَق:
 *
 * 1. **نسخة أمان إجبارية قبل أي استرجاع.** إن فشل أخذها توقّف العملية —
 *    استرجاع بلا طريق عودة ليس عملية يجوز تشغيلها.
 * 2. **لا يُقرأ إلا ملف نسخة مسجَّل في الجدول.** لا مسار يصل من الطلب.
 * 3. **يُفضَّل عميل `mysql`** لأنه يفهم الـ dump كما كتبه `mysqldump`
 *    حرفياً. وعند غيابه يُنفَّذ عبر PHP مع احترام `DELIMITER`.
 */
class BackupRestoreService
{
    /** أقصى مدّة للاسترجاع. قاعدة كبيرة تحتاج وقتاً، والقطع في المنتصف كارثة. */
    private const TIMEOUT_SECONDS = 3600;

    public function __construct(private BackupService $backups) {}

    /**
     * استرجاع قاعدة البيانات من نسخة.
     *
     * @throws \RuntimeException إذا كانت النسخة غير صالحة للاسترجاع أو فشلت نسخة الأمان
     */
    public function restore(Backup $backup, ?int $userId = null, ?string $ip = null): BackupRestore
    {
        if (! $backup->isRestorable()) {
            throw new \RuntimeException('هذه النسخة غير قابلة للاسترجاع: إمّا أنها ملفات فقط أو أن ملفها غير موجود.');
        }

        $archivePath = $this->backups->localPath($backup);

        if ($archivePath === null || ! is_file($archivePath)) {
            throw new \RuntimeException('تعذّر الوصول إلى ملف النسخة على القرص. الاسترجاع مدعوم من التخزين المحلي فقط.');
        }

        $restore = BackupRestore::create([
            'backup_id' => $backup->id,
            'status' => BackupRestore::STATUS_RUNNING,
            'started_at' => now(),
            'executed_by' => $userId,
            'ip_address' => $ip,
        ]);

        $startedAt = microtime(true);
        $workDir = null;
        $auditTrail = null;

        try {
            if (! app()->runningInConsole()) {
                @set_time_limit(self::TIMEOUT_SECONDS);
            }

            // عميل `mysql` عملية ابنة كـ`mysqldump`، ويصيبه العطل نفسه
            // تحت Apache — انظر `BackupService::ensureProcessEnvironment`
            BackupService::ensureProcessEnvironment();

            // ---- 1. نسخة الأمان: طريق العودة ----
            $safety = $this->backups->run('database', Backup::TRIGGER_PRE_RESTORE, $userId);

            if ($safety->status !== Backup::STATUS_COMPLETED) {
                throw new \RuntimeException(
                    'فشل أخذ نسخة الأمان قبل الاسترجاع، فأُلغيت العملية. السبب: '
                    .($safety->error_message ?: 'غير معروف')
                );
            }

            $restore->update(['safety_backup_id' => $safety->id]);

            // ---- 2. استخراج ملف الـ dump ----
            $workDir = $this->extract($archivePath);
            $dumpFile = $this->findDump($workDir);

            // ---- 3. حفظ أثر النسخ قبل أن يمحوه الاسترجاع ----
            // الـ dump يحوي جدولَي `backups` و`backup_restores` كما كانا
            // لحظة أخذه، أي بلا هذا الاسترجاع ولا نسخة الأمان التي سبقته
            // مباشرة. بدون هذه الخطوة يمحو الاسترجاع سجلّ نفسه، ويصبح ملف
            // نسخة الأمان — طريق العودة — يتيماً على القرص بلا صفّ يدلّ عليه
            $auditTrail = $this->captureAuditTrail();

            // ---- 4. التنفيذ ----
            [$statements, $output] = $this->import($dumpFile);

            // ---- 5. إعادة ما مُحي من الأثر ----
            $this->restoreAuditTrail($auditTrail);

            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            $restore->update([
                'status' => BackupRestore::STATUS_COMPLETED,
                'completed_at' => now(),
                'duration_ms' => $duration,
                'statements_executed' => $statements,
                'output' => $this->trim($output),
                'error_message' => null,
            ]);

            Log::warning('تم استرجاع قاعدة البيانات من نسخة احتياطية', [
                'backup_id' => $backup->id,
                'safety_backup_id' => $safety->id,
                'user_id' => $userId,
            ]);

            SystemAlertService::send(
                SystemAlertService::BACKUP_RESTORE_COMPLETED,
                'تم استرجاع قاعدة البيانات',
                'من النسخة «'.$backup->file_name.'» بتاريخ '.$backup->created_at?->format('Y-m-d H:i')
                    .'. نسخة الأمان قبل الاسترجاع: #'.$safety->id
            );

            return $restore->fresh() ?? $restore;
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            Log::error('فشل استرجاع قاعدة البيانات', [
                'backup_id' => $backup->id,
                'restore_id' => $restore->id,
                'error' => $e->getMessage(),
            ]);

            // الانهيار قد يقع *بعد* أن محا الاستيراد نصف الجداول، فيختفي
            // صفّ الاسترجاع نفسه ولا يبقى ما يُحدَّث. إعادته هنا شرط أن
            // يبقى للفشل أثر يُقرأ — وهو أهمّ ما يُقرأ بعد استرجاع متعثّر
            if ($auditTrail !== null) {
                $this->restoreAuditTrail($auditTrail);
            }

            $restore->update([
                'status' => BackupRestore::STATUS_FAILED,
                'completed_at' => now(),
                'duration_ms' => $duration,
                'error_message' => $this->trim($e->getMessage(), 2000),
                'output' => $this->trim($e->getTraceAsString()),
            ]);

            SystemAlertService::send(
                SystemAlertService::BACKUP_RESTORE_FAILED,
                'فشل استرجاع قاعدة البيانات',
                'النسخة «'.$backup->file_name.'» — السبب: '.$this->trim($e->getMessage(), 300)
                    .($restore->safety_backup_id
                        ? ' — نسخة الأمان #'.$restore->safety_backup_id.' متاحة للعودة.'
                        : '')
            );

            return $restore->fresh() ?? $restore;
        } finally {
            if ($workDir) {
                $this->removeDirectory($workDir);
            }
        }
    }

    // ==================== أثر النسخ الاحتياطي ====================

    /**
     * لقطة من جدولَي أثر النسخ قبل الاسترجاع.
     *
     * @return array{backups:array<int,array<string,mixed>>,restores:array<int,array<string,mixed>>}
     */
    protected function captureAuditTrail(): array
    {
        return [
            'backups' => DB::table('backups')->get()->map(fn ($r) => (array) $r)->all(),
            'restores' => DB::table('backup_restores')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    /**
     * إعادة صفوف الأثر التي محاها الاسترجاع.
     *
     * `insertOrIgnore` لا `upsert`: الصفوف التي جاءت من الـ dump نفسه صحيحة
     * كما هي، والمطلوب فقط إضافة ما استُحدث بعد أخذ النسخة.
     */
    protected function restoreAuditTrail(array $trail): void
    {
        try {
            foreach (['backups' => $trail['backups'], 'backup_restores' => $trail['restores']] as $table => $rows) {
                if ($rows === []) {
                    continue;
                }

                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insertOrIgnore($chunk);
                }
            }
        } catch (\Throwable $e) {
            // الاسترجاع نفسه نجح؛ فقدان الأثر مشكلة تُسجَّل ولا تُفشل
            // العملية — لكنها تُذكر صراحةً حتى لا تمرّ صامتة
            Log::error('تعذّرت إعادة سجلّ النسخ الاحتياطية بعد الاسترجاع', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ==================== الخطوات ====================

    /**
     * فكّ الأرشيف إلى مجلَّد مؤقّت خاصّ بهذه العملية.
     *
     * تُجرَّب كلمات التعشية المحتملة بالترتيب لا كلمةٌ واحدة: كلمة الأرشيف
     * قابلة للتغيير من الشاشة، والنسخ المأخوذة قبل التغيير معشّاة بالقديمة.
     * محاولةٌ واحدة تعني أن كل تغيير لكلمة التعشية يُسقِط قابلية استرجاع
     * كل ما سبقه — وهو ما يجعل النسخ القديمة عديمة القيمة بلا إنذار.
     */
    protected function extract(string $archivePath): string
    {
        $workDir = storage_path('app/backup-temp/restore-'.bin2hex(random_bytes(8)));

        if (! mkdir($workDir, 0755, true) && ! is_dir($workDir)) {
            throw new \RuntimeException('تعذّر إنشاء مجلَّد مؤقّت للاسترجاع: '.$workDir);
        }

        foreach ($this->passwordCandidates() as $password) {
            $zip = new ZipArchive;
            $opened = $zip->open($archivePath);

            if ($opened !== true) {
                throw new \RuntimeException('تعذّر فتح أرشيف النسخة (رمز '.$opened.').');
            }

            if ($password !== null) {
                $zip->setPassword($password);
            }

            // `@` مقصود: كلمة خاطئة تُخرِج تنبيهاً من `ZipArchive` لكل ملف
            // في الأرشيف، وهي محاولة متوقّعة لا خلل يُبلَّغ عنه
            $extracted = @$zip->extractTo($workDir);
            $zip->close();

            if ($extracted) {
                return $workDir;
            }

            // محاولة فاشلة قد تكون كتبت ملفات ناقصة — تُمحى قبل التالية
            // حتى لا يُلتقَط `db-dumps/*.sql` مبتوراً على أنه سليم
            $this->emptyDirectory($workDir);
        }

        throw new \RuntimeException(
            'تعذّر فكّ ضغط الأرشيف. إن كان معشّى فتأكّد من كلمة التعشية في '
            .'إعدادات النسخ الاحتياطي — النسخ القديمة قد تكون معشّاة بكلمة سابقة.'
        );
    }

    /**
     * كلمات التعشية المحتملة، مرتّبةً من الأرجح إلى الأبعد.
     *
     * null آخرها ويعني «بلا كلمة»: الأرشيفات المأخوذة قبل تفعيل التعشية
     * مفتوحة، ويجب أن تبقى قابلة للاسترجاع بعد تفعيلها.
     *
     * @return array<int, string|null>
     */
    protected function passwordCandidates(): array
    {
        $candidates = [
            BackupSetting::current()->archivePassword(),
            config('backup.backup.password') ?: null,
        ];

        // الترتيب محفوظ و null يبقى أخيراً: `array_unique` على مصفوفة فيها
        // null يقارن بالنصّ، فتُمرَّر القيم الصريحة أولاً ثم يُضاف null مرّة
        $unique = array_values(array_unique(array_filter(
            $candidates,
            fn ($password) => is_string($password) && $password !== ''
        )));

        $unique[] = null;

        return $unique;
    }

    /** تفريغ مجلَّد من محتواه مع إبقائه — بين محاولتَي فكّ الأرشيف. */
    protected function emptyDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob(rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'*') ?: [] as $item) {
            is_dir($item) ? $this->removeDirectory($item) : @unlink($item);
        }
    }

    /** العثور على ملف الـ dump داخل الأرشيف المفكوك. */
    protected function findDump(string $workDir): string
    {
        $candidates = array_merge(
            glob($workDir.'/db-dumps/*.sql') ?: [],
            glob($workDir.'/db-dumps/*.SQL') ?: [],
            glob($workDir.'/*.sql') ?: []
        );

        if ($candidates === []) {
            throw new \RuntimeException('لا يحتوي الأرشيف على ملف قاعدة بيانات (db-dumps/*.sql).');
        }

        // الأكبر حجماً هو ملف قاعدة البيانات الفعلي حين تتعدّد الاتصالات
        usort($candidates, fn ($a, $b) => filesize($b) <=> filesize($a));

        return $candidates[0];
    }

    /**
     * استيراد الـ dump.
     *
     * @return array{0:int,1:string} [عدد الجمل, المخرجات]
     */
    protected function import(string $dumpFile): array
    {
        $client = $this->mysqlClientPath();

        if ($client !== null) {
            return $this->importWithClient($client, $dumpFile);
        }

        Log::info('عميل mysql غير متاح — الاسترجاع عبر PHP.');

        return $this->importWithPhp($dumpFile);
    }

    /**
     * الاستيراد عبر عميل `mysql`.
     *
     * كلمة المرور تُمرَّر في ملف إعدادات مؤقّت لا في سطر الأوامر: أي
     * مستخدم على الخادم يستطيع قراءة سطور أوامر العمليات الجارية.
     *
     * @return array{0:int,1:string}
     */
    protected function importWithClient(string $client, string $dumpFile): array
    {
        $config = config('database.connections.'.config('database.default'));

        $iniFile = tempnam(sys_get_temp_dir(), 'mysqlcnf');
        $ini = "[client]\n"
            ."user=\"{$config['username']}\"\n"
            ."password=\"{$config['password']}\"\n"
            ."host=\"{$config['host']}\"\n"
            ."port={$config['port']}\n";

        file_put_contents($iniFile, $ini);
        @chmod($iniFile, 0600);

        try {
            $process = new Process([
                $client,
                '--defaults-extra-file='.$iniFile,
                '--default-character-set=utf8mb4',
                $config['database'],
            ]);

            $process->setTimeout(self::TIMEOUT_SECONDS);
            $process->setInput(fopen($dumpFile, 'rb'));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(
                    'فشل عميل mysql أثناء الاستيراد: '.trim($process->getErrorOutput() ?: $process->getOutput())
                );
            }

            return [0, trim($process->getOutput()) ?: 'اكتمل الاستيراد عبر عميل mysql.'];
        } finally {
            @unlink($iniFile);
        }
    }

    /**
     * الاستيراد عبر PHP سطراً سطراً.
     *
     * البديل حين لا يتوفّر عميل `mysql`. يحترم `DELIMITER` لأن الـ dump قد
     * يحوي إجراءات ومحفّزات تنتهي جملها بغير الفاصلة المنقوطة.
     *
     * @return array{0:int,1:string}
     */
    protected function importWithPhp(string $dumpFile): array
    {
        $handle = fopen($dumpFile, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('تعذّر فتح ملف قاعدة البيانات للقراءة.');
        }

        $pdo = DB::connection()->getPdo();
        $executed = 0;
        $buffer = '';
        $delimiter = ';';

        // المفاتيح الأجنبية تُعطَّل أثناء الاستيراد: ترتيب الجداول في الـ
        // dump لا يراعي التبعيات، وتركها مفعّلة يُفشل نصف الجمل
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);

                if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                    continue;
                }

                // تعليقات mysqldump الشرطية `/*!40101 … */;` تعليمات فعلية
                if (str_starts_with($trimmed, '/*') && str_ends_with($trimmed, '*/;') && ! str_starts_with($trimmed, '/*!')) {
                    continue;
                }

                if (stripos($trimmed, 'DELIMITER ') === 0) {
                    $delimiter = trim(substr($trimmed, 10));
                    $buffer = '';

                    continue;
                }

                $buffer .= $line;

                if (str_ends_with(rtrim($buffer), $delimiter)) {
                    $statement = trim(rtrim(rtrim($buffer), $delimiter));

                    if ($statement !== '') {
                        $pdo->exec($statement);
                        $executed++;
                    }

                    $buffer = '';
                }
            }

            if (trim($buffer) !== '') {
                $pdo->exec(trim($buffer));
                $executed++;
            }
        } finally {
            fclose($handle);
            // تُعاد دائماً: تركها معطَّلة يفتح الباب لبيانات غير متّسقة
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return [$executed, 'نُفِّذت '.$executed.' جملة عبر PHP.'];
    }

    /**
     * مسار عميل `mysql` إن كان متاحاً.
     *
     * يُبحَث عنه في المسار المضبوط لـ `mysqldump` أولاً — من ثبّت أحدهما
     * ثبّت الآخر معه في المجلَّد نفسه.
     */
    protected function mysqlClientPath(): ?string
    {
        // `proc_open` معطَّل على بعض الاستضافات المشتركة، وبدونه لا عميل
        if (! function_exists('proc_open')) {
            return null;
        }

        $binaryPath = config('database.connections.'.config('database.default').'.dump.dump_binary_path');
        $isWindows = str_starts_with(strtoupper(PHP_OS_FAMILY), 'WIN');
        $executable = $isWindows ? 'mysql.exe' : 'mysql';

        if ($binaryPath) {
            $candidate = rtrim($binaryPath, '/\\').DIRECTORY_SEPARATOR.$executable;

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // متاح في PATH؟
        try {
            $probe = new Process([$executable, '--version']);
            $probe->setTimeout(10);
            $probe->run();

            return $probe->isSuccessful() ? $executable : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function removeDirectory(string $dir): void
    {
        try {
            if (! is_dir($dir)) {
                return;
            }

            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }

            @rmdir($dir);
        } catch (\Throwable $e) {
            // بقايا مجلَّد مؤقّت مزعجة لا خطيرة — تُسجَّل ولا تُخفى
            Log::warning('تعذّر حذف مجلَّد الاسترجاع المؤقّت', [
                'dir' => $dir,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function trim(?string $value, int $limit = 60000): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit)."\n… (اقتُطع)" : $value;
    }
}
