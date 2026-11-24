<?php

namespace Modules\Installer\Services;

use App\Services\ActivityLogService;
use App\Services\ErrorLogService;
use App\Services\EventLogService;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * سجلّ التثبيت — يُكتب على ملف أولاً ثم يُفرَّغ إلى سجلّات النظام.
 *
 * **لماذا الملف قبل الجداول؟** لأن أغلب التثبيت يجري قبل وجود قاعدة بيانات
 * أصلاً: فحص المتطلبات، وكتابة `.env`، وأول الهجرات. فالكتابة المباشرة على
 * `event_logs` تعني إمّا استثناءً في كل سطر، أو سجلّاً يبدأ من منتصف
 * العملية — وهو أسوأ من لا سجلّ، لأن أول ما يريد المرء معرفته حين يتعثّر
 * التثبيت هو **ما وقع قبل التعثّر**.
 *
 * فيُكتب كل شيء على `storage/app/installer-journal.jsonl` — سطرٌ لكل حدث،
 * يعمل بلا قاعدة ولا إعداد. ثم تُفرَّغ الدفعة كلّها إلى الجداول بمجرّد وجودها
 * (مهمّة `journal` في المنفّذ)، فيقرأ المسؤول تاريخ التثبيت كاملاً من شاشات
 * «سجلّ الأحداث» و«سجلّ الأخطاء» و«سجلّ النشاط» المعتادة.
 *
 * والملف يبقى بعد التفريغ عمداً: هو الأثر الوحيد الباقي لو فشل التثبيت قبل
 * الهجرات، ولا يحوي أسراراً — بيانات الاعتماد لا تدخله.
 */
class InstallationJournal
{
    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_ERROR = 'error';

    /** بادئة كل حدث في سجلّ الأحداث — يميّز أحداث التثبيت عن غيرها. */
    public const ACTION_PREFIX = 'installer.';

    public static function path(): string
    {
        return storage_path('app/installer-journal.jsonl');
    }

    /**
     * تسجيل حدث.
     *
     * لا يرمي أبداً مهما وقع: سجلٌّ يُسقط العملية التي يوثّقها عديم الفائدة.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, string $message, string $level = self::LEVEL_INFO, array $context = []): void
    {
        $entry = [
            'at' => now()->toIso8601String(),
            'action' => $action,
            'level' => $level,
            'message' => $message,
            'context' => $this->scrub($context),
        ];

        $this->append($entry);

        // الكتابة المباشرة حين تكون الجداول موجودة: التفريغ اللاحق يتخطّى ما
        // كُتب فعلاً، فلا يتكرّر الحدث
        if ($this->tablesReady()) {
            $this->persist($entry);
            $this->markPersisted();
        }
    }

    /** اختصار للأخطاء — يقبل استثناءً أو نصّاً. */
    public function error(string $action, Throwable|string $error, array $context = []): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $this->record($action, $message, self::LEVEL_ERROR, $context);

        if (! $this->tablesReady() || ! Schema::hasTable('error_logs')) {
            return;
        }

        try {
            app(ErrorLogService::class)->log($error, url('/install'), null);
        } catch (Throwable) {
            // سجلّ الأخطاء نفسه تعثّر — الملف يحتفظ بالحدث
        }
    }

    /**
     * تفريغ ما لم يُكتب بعد إلى جداول النظام.
     *
     * @return array{written:int, skipped:int}
     */
    public function flush(): array
    {
        if (! $this->tablesReady() || ! file_exists(self::path())) {
            return ['written' => 0, 'skipped' => 0];
        }

        $written = 0;
        $skipped = 0;

        foreach ($this->entries() as $entry) {
            // ما كُتب مباشرةً وقت وقوعه لا يُكتب ثانيةً
            if ($entry['persisted'] ?? false) {
                $skipped++;

                continue;
            }

            try {
                $this->persist($entry);
                $written++;
            } catch (Throwable) {
                $skipped++;
            }
        }

        $this->markAllPersisted();

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * قراءة السجلّ — لعرضه في شاشة الإتمام.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entries(): array
    {
        if (! file_exists(self::path())) {
            return [];
        }

        $lines = file(self::path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_values(array_filter(array_map(
            fn ($line) => json_decode($line, true),
            $lines
        ), 'is_array'));
    }

    /** بدء سجلّ جديد — يُستدعى عند بناء خطة التنفيذ. */
    public function start(array $context = []): void
    {
        @unlink(self::path());

        $this->record('started', 'بدأ تنفيذ التثبيت', self::LEVEL_INFO, $context);
    }

    /* ==================== الداخل ==================== */

    protected function append(array $entry): void
    {
        try {
            $dir = dirname(self::path());

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents(
                self::path(),
                json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (Throwable) {
            // لا شيء يُفعل: السجلّ لا يُسقط ما يوثّقه
        }
    }

    /**
     * كتابة حدث في جداول النظام.
     *
     * ثلاثة سجلّات لثلاثة أغراض: `event_logs` لما وقع، و`logs` لأثر النشاط
     * الذي تعرضه شاشة سجلّ النشاط، و`error_logs` للأخطاء وحدها.
     */
    protected function persist(array $entry): void
    {
        $action = self::ACTION_PREFIX.$entry['action'];
        $description = $entry['message'];

        if (($entry['context'] ?? []) !== []) {
            $description .= ' — '.json_encode($entry['context'], JSON_UNESCAPED_UNICODE);
        }

        if (Schema::hasTable('event_logs')) {
            app(EventLogService::class)->log($action, $description, null);
        }

        // سجلّ النشاط للأحداث المهمّة وحدها: تسجيل خمسمئة هجرة فيه يُغرق
        // الشاشة التي يقرأ فيها المسؤول ما فعله المستخدمون.
        //
        // والوصف يحمل اسم الحدث بادئةً لأن `ActivityLogService::log` **يتجاهل
        // معامل `$action`** ولا يخزّن إلا `description` — فبدون هذه البادئة
        // يتعذّر تمييز أحداث التثبيت في تلك الشاشة أو البحث عنها
        if ($entry['level'] !== self::LEVEL_INFO || $this->isMilestone($entry['action'])) {
            if (Schema::hasTable('logs')) {
                app(ActivityLogService::class)->log($action, '['.$action.'] '.$description, null, null, 'setting');
            }
        }

        if ($entry['level'] === self::LEVEL_ERROR && Schema::hasTable('error_logs')) {
            app(ErrorLogService::class)->log($description, url('/install'), null);
        }
    }

    /** الأحداث التي تستحقّ الظهور في سجلّ النشاط. */
    protected function isMilestone(string $action): bool
    {
        return in_array($action, [
            'started', 'finished', 'migrations.done', 'seeders.done',
            'settings.applied', 'subscription.linked', 'subscription.synced',
            'compatibility.passed', 'compatibility.failed', 'verification.passed',
        ], true);
    }

    /**
     * حذف ما لا يجوز أن يُسجَّل.
     *
     * كلمات المرور والأسرار تمرّ في حمولة المعالج، وسجلٌّ يحفظها يجعل قراءة
     * سجلّ الأحداث كافيةً لانتحال شخصية المدير.
     */
    protected function scrub(array $context): array
    {
        $sensitive = ['password', 'password_confirmation', 'secret', 'mail_password', 'db_password'];

        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $context[$key] = '••••••';

                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->scrub($value);
            }
        }

        return $context;
    }

    protected function tablesReady(): bool
    {
        try {
            return Schema::hasTable('event_logs');
        } catch (Throwable) {
            return false;
        }
    }

    /** وسم آخر سطر بأنه كُتب في الجداول. */
    protected function markPersisted(): void
    {
        $entries = $this->entries();

        if ($entries === []) {
            return;
        }

        $entries[count($entries) - 1]['persisted'] = true;

        $this->rewrite($entries);
    }

    protected function markAllPersisted(): void
    {
        $entries = array_map(function ($entry) {
            $entry['persisted'] = true;

            return $entry;
        }, $this->entries());

        $this->rewrite($entries);
    }

    protected function rewrite(array $entries): void
    {
        try {
            $lines = array_map(
                fn ($e) => json_encode($e, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $entries
            );

            file_put_contents(self::path(), implode(PHP_EOL, $lines).PHP_EOL, LOCK_EX);
        } catch (Throwable) {
            // لا شيء يُفعل
        }
    }
}
