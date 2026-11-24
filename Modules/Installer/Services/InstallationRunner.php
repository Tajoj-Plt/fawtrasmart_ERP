<?php

namespace Modules\Installer\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * تنفيذ التثبيت على نبضات قصيرة بدل طلبٍ واحد طويل.
 *
 * **لماذا؟** التثبيت الكامل يتجاوز الدقيقة بكثير — أكثر من 470 هجرة وأربعين
 * بذرة. وطلبُ HTTP واحد يحمله يصطدم بمهلة PHP أو بمهلة الوسيط أو بمهلة
 * المتصفّح، فينقطع في منتصفه تاركاً قاعدةً نصف مبنية وشاشةً بيضاء لا تقول
 * أين توقّف.
 *
 * الحل هنا: تُبنى **خطة** من مهام ذرّية (هجرة واحدة، أو بذرة واحدة)، ويطلب
 * المتصفّح تنفيذ مهمة واحدة في كل نداء. فلا يتجاوز أي طلبٍ ثوانيَ معدودة،
 * ويرى المستخدم كل خطوة لحظة وقوعها، ويتوقّف التنفيذ عند أول خطأ عارضاً
 * نصّه كاملاً.
 *
 * **الحالة في ملف لا في الجلسة**: الجلسة قد تُفقد بين النداءات (كوكي،
 * انتهاء صلاحية، تبديل تبويب)، وفقدُها في منتصف التثبيت يعني إعادته من
 * الصفر. والملف يبقى، فيمكن استئناف ما توقّف.
 */
class InstallationRunner
{
    /**
     * ميزانية النبضة الواحدة بالثواني.
     *
     * ‏٣ ثوانٍ تجمع عشرات الهجرات في نداءٍ واحد وتبقى بعيدةً جداً عن مهلة
     * PHP الافتراضية (٦٠) وعن أي وسيط. والقياس على هذا النظام: التثبيت كان
     * ‎+٥٣٠‎ رحلة HTTP، وصار نحو خمسين.
     */
    protected const BATCH_SECONDS = 3.0;

    /** حدٌّ أعلى لعدد الهجرات في النبضة — كي يبقى السجلّ مقروءاً. */
    protected const BATCH_MAX = 40;

    public function __construct(protected InstallationJournal $journal) {}

    /** مسار ملف الحالة — يُحذف عند تمام التثبيت. */
    public static function statePath(): string
    {
        return storage_path('app/installer-progress.json');
    }

    /**
     * بناء خطة التنفيذ.
     *
     * الهجرات تُعدّ ملفاً ملفاً لا دفعةً واحدة: `migrate` بلا وسائط يشغّلها
     * كلها في نداءٍ واحد — وهو بالضبط ما نتجنّبه. و`--path` لملفٍ واحد يجعل
     * كل هجرة مهمةً مستقلة يظهر اسمها ومدّتها.
     *
     * @param  array  $payload  مدخلات المعالج
     * @return array الخطة المحفوظة
     */
    public function plan(array $payload): array
    {
        $tasks = [];

        $fresh = (bool) ($payload['options']['fresh'] ?? false);

        // إسقاط الجداول مهمةٌ واحدة تسبق كل شيء
        if ($fresh) {
            $tasks[] = ['type' => 'fresh', 'title' => 'إسقاط الجداول القائمة', 'ref' => null];
        }

        foreach ($this->pendingMigrations() as $name => $path) {
            $tasks[] = ['type' => 'migration', 'title' => $name, 'ref' => $path];
        }

        foreach (DatabaseInstaller::CORE_SEEDERS as $seeder) {
            $tasks[] = ['type' => 'seeder', 'title' => class_basename($seeder), 'ref' => $seeder];
        }

        if ($payload['options']['demo_data'] ?? false) {
            foreach (DatabaseInstaller::DEMO_SEEDERS as $seeder) {
                $tasks[] = ['type' => 'seeder', 'title' => class_basename($seeder).' (تجريبي)', 'ref' => $seeder];
            }
        }

        $tasks[] = ['type' => 'settings', 'title' => 'تجهيز الإعدادات الأساسية', 'ref' => null];

        // الربط بلوحة فوترة — مهمّتان لأنهما عمليتان مختلفتان: كتابة بيانات
        // الاعتماد لا تحتاج شبكة، والمزامنة تحتاجها. فصلُهما يجعل تعثّر
        // الشبكة لا يمحو مفاتيح صحيحة كُتبت بنجاح
        if (! empty($payload['fawtra'])) {
            $tasks[] = ['type' => 'link', 'title' => 'ربط النظام بلوحة فوترة', 'ref' => null];
            $tasks[] = ['type' => 'subscription', 'title' => 'جلب بيانات الاشتراك وحدود الاستخدام', 'ref' => null];
        }
        $tasks[] = ['type' => 'env', 'title' => 'كتابة ملف البيئة', 'ref' => null];
        $tasks[] = ['type' => 'storage', 'title' => 'الربط الرمزي للتخزين', 'ref' => null];
        $tasks[] = ['type' => 'verify', 'title' => 'التحقّق من الإعدادات', 'ref' => null];

        // فحص التوافق مع الاشتراك — بوّابة لا تنبيه: لا يُسجَّل تمام التثبيت
        // قبل اجتيازها. تُدرَج دائماً لا فقط عند الربط، لأنها تُخبر أيضاً أن
        // النظام غير مربوط وهو ما يستحقّ أن يُقال صريحاً في السجلّ
        $tasks[] = ['type' => 'compatibility', 'title' => 'فحص التوافق مع الاشتراك', 'ref' => null];

        // تفريغ سجلّ التثبيت إلى جداول النظام — بعد كل شيء لأن السجلّ يوثّقه
        $tasks[] = ['type' => 'journal', 'title' => 'ترحيل سجلّ التثبيت إلى سجلّات النظام', 'ref' => null];

        $tasks[] = ['type' => 'finish', 'title' => 'تسجيل تمام التثبيت', 'ref' => null];

        $state = [
            'started_at' => now()->toIso8601String(),
            'cursor' => 0,
            'failed' => false,
            'tasks' => $tasks,
            'payload' => $payload,
        ];

        $this->save($state);

        $this->journal->start([
            'tasks' => count($tasks),
            'migrations' => count(array_filter($tasks, fn ($t) => $t['type'] === 'migration')),
            'seeders' => count(array_filter($tasks, fn ($t) => $t['type'] === 'seeder')),
            'fresh' => $fresh,
            'demo_data' => (bool) ($payload['options']['demo_data'] ?? false),
            'linked' => ! empty($payload['fawtra']),
            'database' => $payload['database']['database'] ?? null,
        ]);

        return $state;
    }

    /**
     * تنفيذ المهمة التالية.
     *
     * @return array{done:bool, ok:bool, index:int, total:int, type:string, title:string, message:string, detail:?string, duration:float}
     */
    /**
     * نبضة تنفيذ واحدة — محروسةً بقفل.
     *
     * **لماذا القفل؟** لأن الحالة ملفٌّ على القرص لا معاملة قاعدة بيانات: لسانا
     * متصفّح مفتوحان على شاشة التنفيذ (أو تحديثٌ للصفحة أثناء العمل) يقرآن
     * المؤشّر نفسه فينفّذان المهمة نفسها مرّتين — فتخرج «الجدول موجود مسبقاً»
     * أو مفتاحٌ أجنبي على جدولٍ نصف مبنيّ. القفل يجعل الثانية تنتظر لا تُكرّر.
     */
    public function step(): array
    {
        $handle = @fopen(self::lockPath(), 'c');

        if ($handle === false) {
            // تعذّر إنشاء ملف القفل — يُمضى بلا قفل بدل إيقاف التثبيت
            return $this->stepLocked();
        }

        // محاولات قصيرة قبل الاستسلام: على ويندوز يتأخّر تحرير القفل لحظةً
        // بعد إغلاق المقبض، فرفضٌ من أول محاولة يُنتج «مشغول» كاذباً بين
        // نبضتين متتاليتين من المتصفّح نفسه
        $acquired = false;

        for ($attempt = 0; $attempt < 25; $attempt++) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $acquired = true;
                break;
            }

            usleep(100_000);
        }

        if (! $acquired) {
            fclose($handle);

            return $this->result(
                done: false, ok: true, index: 0, total: 0, type: 'busy',
                title: 'نبضة أخرى قيد التنفيذ', message: 'تُنفَّذ مهمة الآن — انتظر لحظة.',
                detail: null, duration: 0,
            );
        }

        try {
            return $this->stepLocked();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function lockPath(): string
    {
        return storage_path('app/installer-progress.lock');
    }

    /**
     * إعادة بناء سياق التنفيذ من الخطة المحفوظة قبل كل نبضة.
     *
     * **كل نبضة طلبٌ مستقل، وذاكرة الطلب السابق ذهبت معه.** وبيانات القاعدة
     * ومفاتيح فوترة لا تُكتب في `.env` إلا في آخر التنفيذ (كي لا يُعاد تشغيل
     * الخادم في منتصف المعالج)، فلو لم تُعَد هنا لعملت كل نبضة على إعداد
     * `.env` القديم: الهجرات على قاعدةٍ غير المختارة، ومهمّة الاشتراك بلا
     * مفاتيح فتُرجع «غير مربوط» — فيسقط فحص التوافق في آخر خطوة بعد أن مضى
     * التثبيت كلّه بنجاح. (وقع هذا فعلاً.)
     *
     * والخطة هي المصدر لا الجلسة: النبضة قد تأتي من الطرفية بلا جلسة أصلاً.
     */
    protected function applyRuntimeContext(array $payload): void
    {
        if ($database = $payload['database'] ?? null) {
            app(EnvironmentManager::class)->applyRuntimeDatabase($database);
        }

        if ($fawtra = $payload['fawtra'] ?? null) {
            app(\App\Services\Fawtra\FawtraCredentials::class)->applyRuntime($fawtra);
        }
    }

    /**
     * هل هناك تنفيذ قائم الآن؟ — فحصُ ملفٍ لا قراءةَ حالة.
     *
     * يُستدعى من `InstallationState` على كل طلب، ومن قبل أن تكون هناك قاعدة
     * بيانات، فلا يجوز أن يفكّ تشفيراً ولا أن يلمس اتصالاً.
     */
    public static function isRunning(): bool
    {
        return file_exists(self::statePath());
    }

    protected function stepLocked(): array
    {
        $state = $this->load();

        if ($state === null) {
            return $this->result(true, false, 0, 0, 'error', 'الحالة مفقودة', 'انتهت جلسة التثبيت — ابدأ من جديد.', null, 0);
        }

        // سياق التنفيذ يُعاد بناؤه في كل نبضة قبل أي عمل — انظر
        // `applyRuntimeContext` لسبب ذلك
        $this->applyRuntimeContext($state['payload'] ?? []);

        $total = count($state['tasks']);
        $index = $state['cursor'];

        if ($index >= $total) {
            return $this->result(true, true, $total, $total, 'done', 'اكتمل', 'تمّ التثبيت بنجاح.', null, 0);
        }

        $task = $state['tasks'][$index];

        // المهلة تُرفع لكل نبضة على حدة: بعض الهجرات (المتجر الإلكتروني مثلاً
        // تُنشئ عشرين جدولاً) تتجاوز الثواني المعدودة وحدها
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        $start = microtime(true);
        $thrown = null;
        $batched = [];

        try {
            [$message, $detail] = $this->execute($task, $state);
            $ok = true;

            // **الهجرات تُنفَّذ زُمَراً لا واحدةً في كل نداء.** خمسمئة رحلة
            // HTTP تكلّف من الزمن أضعاف ما تكلّفه الهجرات نفسها (الرحلة
            // ‎~120ms‎ والهجرة ‎~40ms‎)، فيبدو التثبيت بطيئاً وهو ينتظر الشبكة.
            // والزمرة محدودة بميزانية ثوانٍ لا بعدد، فلا تقترب نبضة من مهلة
            // الخادم مهما ثقلت هجرةٌ بعينها
            if ($task['type'] === 'migration') {
                [$index, $batched, $message] = $this->drainMigrations($state, $index, $total, $start, $message);
                $task = $state['tasks'][$index];
            }
        } catch (Throwable $e) {
            $ok = false;
            $thrown = $e;
            $message = $this->summarise($e->getMessage());
            $detail = $e->getMessage()."\n\n".$e->getFile().':'.$e->getLine();
        }

        $duration = round(microtime(true) - $start, 2);

        $this->journalTask($task, $state, $index, $total, $ok, $message, $duration, $thrown);

        if ($ok) {
            $state['cursor'] = $index + 1;
        } else {
            // لا يتقدّم المؤشّر: التوقّف عند الخطأ يسمح بإصلاح السبب
            // وإعادة المحاولة من المهمة نفسها لا من أول الخطة
            $state['failed'] = true;
        }

        // الحذف بعد آخر مهمة لا داخلها.
        //
        // كان `runFinish` يحذف الملف بنفسه، ثم يعود التنفيذ إلى هنا فيحفظ
        // الحالة من جديد — أي يُعاد إنشاء ملفٍ **فيه كلمة مرور المدير صريحة**
        // ويبقى على القرص بعد انتهاء التثبيت. الحذف هنا هو آخر ما يقع.
        if ($ok && $state['cursor'] >= $total) {
            $this->clear();
        } else {
            $this->save($state);
        }

        $result = $this->result(
            done: $ok && $state['cursor'] >= $total,
            ok: $ok,
            index: $index + 1,
            total: $total,
            type: $task['type'],
            title: $task['title'],
            message: $message,
            detail: $detail,
            duration: $duration,
        );

        // أسماء الزمرة تُعاد كاملةً: الشاشة تعرض سطراً لكل هجرة كما كانت،
        // والتجميع في النقل لا في العرض
        if ($batched !== []) {
            $result['titles'] = $batched;
        }

        return $result;
    }

    /**
     * تنفيذ ما أمكن من الهجرات المتتالية ضمن ميزانية زمنية.
     *
     * تعود بـ[المؤشّر الأخير المنفَّذ، أسماء ما نُفِّذ، الرسالة المختصرة].
     *
     * @return array{0:int, 1:array<int, string>, 2:string}
     */
    protected function drainMigrations(array &$state, int $index, int $total, float $start, string $message): array
    {
        $names = [$state['tasks'][$index]['title']];

        while (
            $index + 1 < $total
            && $state['tasks'][$index + 1]['type'] === 'migration'
            && (microtime(true) - $start) < self::BATCH_SECONDS
            && count($names) < self::BATCH_MAX
        ) {
            $next = $state['tasks'][$index + 1];

            // الاستثناء يخرج إلى `stepLocked` فيتوقّف التنفيذ عند الهجرة
            // المتعثّرة نفسها — والمؤشّر لم يتقدّم إليها بعد
            $this->execute($next, $state);

            $index++;
            $names[] = $next['title'];

            // المؤشّر يتقدّم **داخل** الزمرة لا بعدها: لو انفجرت الهجرة
            // التالية بقي المحفوظ مشيراً إليها وحدها، فيستأنف التنفيذ منها
            // لا من أوّل الزمرة — وإعادة تنفيذ هجرةٍ تمّت تُخرج «الجدول موجود»
            $state['cursor'] = $index + 1;
            $this->save($state);
        }

        return [$index, $names, count($names) > 1 ? count($names).' هجرة' : $message];
    }

    /**
     * تسجيل نتيجة نبضة في سجلّ التثبيت.
     *
     * **الهجرات والبذور الناجحة تُسجَّل مجمَّعةً لا فرادى.** خمسمئة سطر في
     * `event_logs` تُغرق الشاشة التي يقرأ فيها المسؤول ما وقع، فتُفقد الفائدة
     * التي وُجد السجلّ لأجلها. أمّا الإخفاق فيُسجَّل دائماً مهما كان نوعه —
     * فهو بالضبط ما يُبحث عنه لاحقاً.
     */
    protected function journalTask(
        array $task,
        array $state,
        int $index,
        int $total,
        bool $ok,
        string $message,
        float $duration,
        ?Throwable $thrown,
    ): void {
        if (! $ok) {
            $this->journal->error(
                $task['type'].'.failed',
                $thrown ?? $message,
                ['task' => $task['title'], 'index' => $index + 1, 'of' => $total]
            );

            return;
        }

        $bulk = in_array($task['type'], ['migration', 'seeder'], true);

        if (! $bulk) {
            $this->journal->record(
                $task['type'].'.done',
                $task['title'].' — '.$message,
                InstallationJournal::LEVEL_INFO,
                ['duration' => $duration]
            );

            return;
        }

        // علامةٌ مجمَّعة عند آخر عنصر من نوعه
        $next = $state['tasks'][$index + 1]['type'] ?? null;

        if ($task['type'] !== $next) {
            $count = count(array_filter($state['tasks'], fn ($t) => $t['type'] === $task['type']));

            $this->journal->record(
                $task['type'] === 'migration' ? 'migrations.done' : 'seeders.done',
                $task['type'] === 'migration' ? 'اكتملت الهجرات' : 'اكتملت البذور',
                InstallationJournal::LEVEL_INFO,
                ['count' => $count]
            );
        }
    }

    /**
     * تنفيذ مهمة واحدة حسب نوعها.
     *
     * @return array{0:string, 1:?string} [الرسالة، التفصيل]
     */
    protected function execute(array $task, array $state): array
    {
        return match ($task['type']) {
            'fresh' => $this->runFresh(),
            'migration' => $this->runMigration($task['ref']),
            'seeder' => $this->runSeeder($task['ref']),
            'settings' => $this->runSettings($state['payload']),
            'link' => $this->runLink($state['payload']),
            'subscription' => $this->runSubscriptionSync(),
            'env' => $this->runEnvironment($state['payload']),
            'storage' => $this->runStorageLink(),
            'verify' => $this->runVerification(),
            'compatibility' => $this->runCompatibility(),
            'journal' => $this->runJournalFlush(),
            'finish' => $this->runFinish($state['payload']),
            default => ['نوع مهمة غير معروف', null],
        };
    }

    protected function runFresh(): array
    {
        Artisan::call('db:wipe', ['--force' => true]);

        return ['أُسقطت كل الجداول', trim(Artisan::output())];
    }

    protected function runMigration(string $path): array
    {
        // هجرةٌ سُجّلت من قبل لا تُعاد: قد تكون نُفِّذت ثم انقطع الاتصال قبل
        // أن تصل نتيجتها، فيعيد المتصفّح المحاولة. وإعادتها تعني
        // «الجدول موجود مسبقاً» وتوقّفاً لا سبب حقيقيّ له
        try {
            $name = basename($path, '.php');

            if (Schema::hasTable('migrations') && DB::table('migrations')->where('migration', $name)->exists()) {
                return ['سبق تنفيذها — تُخطّى', null];
            }
        } catch (Throwable) {
            // لا اتصال بعد: تُترك للأمر نفسه ليُبلّغ الخطأ بوضوح
        }

        Artisan::call('migrate', ['--path' => $path, '--force' => true, '--realpath' => false]);

        $output = trim(Artisan::output());

        return ['تمّت', $output !== '' ? $output : null];
    }

    protected function runSeeder(string $seeder): array
    {
        // بذرة أُعيدت تسميتها أو حُذفت لا تُسقط التثبيت
        if (! class_exists($seeder)) {
            return ['الصنف غير موجود — تُخطّي', null];
        }

        Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);

        $output = trim(Artisan::output());

        return ['تمّت', $output !== '' ? $output : null];
    }

    protected function runSettings(array $payload): array
    {
        $installer = app(SettingsInstaller::class);
        $applied = $installer->run($payload);

        $lines = array_map(fn ($r) => ($r['ok'] ? '✔ ' : '⚠ ').$r['message'], $applied);

        return [count($applied).' إعداد', implode("\n", $lines)];
    }

    /**
     * كتابة بيانات الربط في `.env`.
     *
     * لا تُلمس قاعدة البيانات: السرّ يُوقَّع به ولا يُعرض، ووضعُه في جدول
     * يعني نسخَه مع كل نسخة احتياطية وكل تصدير.
     */
    protected function runLink(array $payload): array
    {
        // ضبطٌ في الذاكرة لا كتابة: المزامنة التالية تعمل بها فوراً، والملف
        // يُكتب مرّةً واحدة في مهمة `.env` قرب النهاية
        app(\App\Services\Fawtra\FawtraCredentials::class)->applyRuntime($payload['fawtra']);

        return ['جُهّزت بيانات الربط', 'العميل: '.($payload['fawtra']['client_id'] ?? '—')];
    }

    /**
     * أول مزامنة — تملأ الاشتراك والباقة وحدود الاستخدام والتنبيهات.
     *
     * تعثّرها **لا يُفشل التثبيت**: الوثيقة تنصّ ألّا يتوقّف نظام العميل لعطل
     * شبكة، والمهمة المجدولة ستعيد المحاولة كل ربع ساعة. فتُرجع ملاحظةً لا
     * استثناء.
     */
    protected function runSubscriptionSync(): array
    {
        $result = app(\App\Services\Fawtra\SubscriptionSync::class)->run(force: true);

        if (! $result['ok']) {
            return [
                'تعذّرت المزامنة الأولى — ستُعاد تلقائياً كل ربع ساعة',
                $result['message'],
            ];
        }

        $record = \App\Models\FawtraSubscription::current();

        return [
            'جُلبت بيانات الاشتراك',
            $record
                ? sprintf(
                    'الباقة: %s
الحالة: %s
ينتهي في: %s
حدود مُتابَعة: %d',
                    $record->plan_name ?: '—',
                    $record->statusLabel(),
                    $record->end_date?->toDateString() ?: '—',
                    count($record->usage())
                )
                : null,
        ];
    }

    protected function runEnvironment(array $payload): array
    {
        $database = $payload['database'] ?? [];

        app(EnvironmentManager::class)->write([
            // بيانات القاعدة تُكتب **هنا** لا في خطوة القاعدة: الكتابة في
            // `.env` تُعيد تشغيل خادم التطوير (`artisan serve` يراقب الملف)،
            // فتنقطع الاستجابة في منتصف المعالج ويبدو كأنه علّق. وطوال
            // المعالج يكفي ضبط الإعداد في الذاكرة — والملف يُكتب مرّةً في
            // نهاية التنفيذ حيث انقطاعُ الاستجابة لا يضرّ
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['host'] ?? config('database.connections.mysql.host'),
            'DB_PORT' => $database['port'] ?? config('database.connections.mysql.port'),
            'DB_DATABASE' => $database['database'] ?? config('database.connections.mysql.database'),
            'DB_USERNAME' => $database['username'] ?? config('database.connections.mysql.username'),
            'DB_PASSWORD' => $database['password'] ?? config('database.connections.mysql.password'),

            // **تثبيت اسم كعكة الجلسة قبل تغيير اسم التطبيق.**
            // اسم الكعكة في `config/session.php` مشتقٌّ من `APP_NAME`، فتغييرُ
            // الاسم إلى اسم المنشأة يجعل المتصفّح يحمل كعكةً باسمٍ لم يعد
            // مُتوقَّعاً: تضيع الجلسة، وتسقط بقيّة النبضات بـ«CSRF token
            // mismatch» في آخر خطوة من التثبيت. وتثبيتُه هنا يحفظ الجلسة
            // القائمة ويمنع خروج المستخدمين عند أي تغيير لاسم النظام لاحقاً
            'SESSION_COOKIE' => config('session.cookie'),

            'APP_NAME' => $payload['company']['name'] ?? config('app.name'),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $payload['app_url'] ?? config('app.url'),
            'APP_LOCALE' => $payload['settings']['language'] ?? 'ar',
            'APP_TIMEZONE' => $payload['settings']['timezone'] ?? 'Asia/Riyadh',
        ]);

        // مفاتيح فوترة تُكتب مع الباقي في نفس اللحظة — كتابةٌ واحدة لكل
        // التنفيذ، فإعادة تحميل الخادم إن وقعت تقع مرّةً وفي آخره
        if (! empty($payload['fawtra'])) {
            app(\App\Services\Fawtra\FawtraCredentials::class)->store($payload['fawtra']);
        }

        return ['كُتبت بيانات التطبيق و.env', null];
    }

    protected function runStorageLink(): array
    {
        try {
            Artisan::call('storage:link');

            return ['أُنشئ public/storage', null];
        } catch (Throwable $e) {
            // على الاستضافات المشتركة قد تُمنع الروابط الرمزية — ملاحظةٌ لا
            // فشل، فالمرفقات تُرفع ويبقى عرضها هو المتأثّر
            return ['تعذّر إنشاؤه — المرفقات تُرفع لكن قد لا تُعرض', $e->getMessage()];
        }
    }

    /**
     * التحقّق — يرمي عند سقوط فحصٍ حرج، فيتوقّف التنفيذ قبل تسجيل التثبيت.
     */
    protected function runVerification(): array
    {
        $installer = app(SettingsInstaller::class);
        $checks = $installer->verify();

        $lines = array_map(
            fn ($c) => ($c['ok'] ? '✔ ' : ($c['critical'] ? '✘ ' : '⚠ ')).$c['label'].': '.($c['value'] ?? 'غير مضبوط'),
            $checks
        );

        if (! $installer->verificationPassed($checks)) {
            $missing = array_map(
                fn ($c) => $c['label'],
                array_filter($checks, fn ($c) => $c['critical'] && ! $c['ok'])
            );

            throw new \RuntimeException(
                'إعدادات أساسية لم تُضبط: '.implode('، ', $missing)."\n\n".implode("\n", $lines)
            );
        }

        return [count($checks).' فحص — كلها سليمة', implode("\n", $lines)];
    }

    /**
     * فحص التوافق — البوّابة الأخيرة قبل تسجيل التثبيت.
     *
     * يرمي عند سقوط فحصٍ حرج فيتوقّف التنفيذ، ولا تُكتب علامة التثبيت. وهذا
     * المقصود: نظامٌ باشتراك منتهٍ أو بمستخدمين يتجاوزون حدّ الباقة لا يجوز
     * أن يُنهي تثبيته ثم يكتشف ذلك.
     */
    protected function runCompatibility(): array
    {
        $checker = app(CompatibilityChecker::class);
        $checks = $checker->all();

        $lines = array_map(
            fn ($c) => ($c['ok'] ? '✔ ' : ($c['critical'] ? '✘ ' : '⚠ ')).$c['label'].': '.$c['detail'],
            $checks
        );

        if (! $checker->passed($checks)) {
            $failures = $checker->failures($checks);

            $this->journal->record(
                'compatibility.failed',
                'سقط فحص التوافق مع الاشتراك',
                InstallationJournal::LEVEL_ERROR,
                ['failures' => $failures]
            );

            throw new \RuntimeException(
                'النظام غير متوافق مع الاشتراك:

'.implode('
', $failures)
                .'

'.implode('
', $lines)
            );
        }

        $warnings = array_filter($checks, fn ($c) => ! $c['ok']);

        $this->journal->record(
            'compatibility.passed',
            'اجتاز فحص التوافق'.($warnings !== [] ? ' مع '.count($warnings).' ملاحظة' : ''),
            $warnings !== [] ? InstallationJournal::LEVEL_WARNING : InstallationJournal::LEVEL_INFO,
            ['checks' => count($checks), 'warnings' => count($warnings)]
        );

        return [
            count($checks).' فحص'.($warnings !== [] ? ' — '.count($warnings).' ملاحظة' : ' — كلها سليمة'),
            implode('
', $lines),
        ];
    }

    /** ترحيل سجلّ التثبيت من الملف إلى `event_logs` و`logs` و`error_logs`. */
    protected function runJournalFlush(): array
    {
        $result = $this->journal->flush();

        return [
            $result['written'].' حدثاً رُحِّل إلى سجلّات النظام',
            $result['skipped'] > 0 ? $result['skipped'].' حدثاً كان مكتوباً أصلاً' : null,
        ];
    }

    protected function runFinish(array $payload): array
    {
        InstallationState::markInstalled([
            'company' => $payload['company']['name'] ?? null,
            'admin_email' => $payload['admin']['email'] ?? null,
            'demo_data' => (bool) ($payload['options']['demo_data'] ?? false),
        ]);

        $this->journal->record('finished', 'اكتمل التثبيت بنجاح', InstallationJournal::LEVEL_INFO, [
            'company' => $payload['company']['name'] ?? null,
            'admin_email' => $payload['admin']['email'] ?? null,
        ]);

        // تفريغ ثانٍ: حدث «اكتمل» وقع بعد مهمّة الترحيل فلم يشمله التفريغ الأول
        $this->journal->flush();

        // لا يُحذف ملف الحالة هنا — `step()` هي من تحذفه بعد حفظ المؤشّر،
        // وإلا أعادت كتابتَه بعد هذه الدالة مباشرة
        return ['سُجِّل تمام التثبيت', null];
    }

    /**
     * الهجرات التي لم تُشغَّل بعد، مرتّبةً كما يرتّبها Laravel.
     *
     * المسارات نسبية لجذر المشروع لأن `migrate --path` يتوقّعها كذلك.
     *
     * @return array<string, string> اسم الهجرة => مسارها النسبي
     */
    public function pendingMigrations(): array
    {
        $files = [];

        $roots = array_merge(
            ['database/migrations'],
            array_map(
                fn ($p) => str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $p)),
                glob(base_path('Modules/*/database/migrations'), GLOB_ONLYDIR) ?: []
            )
        );

        foreach ($roots as $root) {
            foreach (glob(base_path($root).'/*.php') ?: [] as $absolute) {
                $files[basename($absolute, '.php')] = $root.'/'.basename($absolute);
            }
        }

        ksort($files);

        // جدول `migrations` قد لا يوجد بعد على قاعدة فارغة — وحينها كلها معلّقة
        try {
            if (Schema::hasTable('migrations')) {
                $ran = DB::table('migrations')->pluck('migration')->all();
                $files = array_diff_key($files, array_flip($ran));
            }
        } catch (Throwable $e) {
            // لا اتصال: تُعامَل كلها كمعلّقة، وأول مهمة ستكشف الخطأ بوضوح
        }

        return $files;
    }

    /* ==================== الحالة ==================== */

    public function load(): ?array
    {
        if (! file_exists(self::statePath())) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents(self::statePath()), true);

        return is_array($decoded) ? $this->decryptSecrets($decoded) : null;
    }

    protected function save(array $state): void
    {
        $dir = dirname(self::statePath());

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            self::statePath(),
            json_encode($this->encryptSecrets($state), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        // 0600 — الملف يخصّ مستخدم خادم الويب وحده. القيمة الافتراضية على
        // بعض الاستضافات المشتركة تجعله مقروءاً لكل حسابات الخادم
        @chmod(self::statePath(), 0600);
    }

    /**
     * تشفير الأسرار قبل كتابتها على القرص.
     *
     * **كلمة مرور المدير كانت تُخزَّن صريحة.** الملف يُحذف عند تمام التثبيت،
     * لكنه **يبقى إن تعثّر** — وهو أرجح ما يقع حين يحتاج المرء إلى الاستئناف.
     * فتبقى كلمة مرور أعلى حساب في النظام نصّاً مقروءاً على القرص إلى أن
     * ينتبه أحد.
     *
     * والتشفير بمفتاح التطبيق: موجودٌ حتماً في هذه المرحلة لأن خطوة قاعدة
     * البيانات — وهي قبل التنفيذ — تولّده إن غاب (`ensureAppKey`).
     */
    protected function encryptSecrets(array $state): array
    {
        $password = $state['payload']['admin']['password'] ?? null;

        if ($password === null || $password === '') {
            return $state;
        }

        try {
            $state['payload']['admin']['password'] = Crypt::encryptString($password);
            $state['payload']['admin']['password_encrypted'] = true;
        } catch (Throwable $e) {
            // تعذّر التشفير (مفتاح مفقود) — تُحذف بدل كتابتها صريحة.
            // التثبيت يتعثّر عند إنشاء المدير برسالة واضحة، وهو أهون من
            // كلمة مرور مكشوفة
            unset($state['payload']['admin']['password']);
        }

        return $state;
    }

    /** فكّ ما شُفِّر — والعلامة تمنع فكّ قيمةٍ لم تُشفَّر. */
    protected function decryptSecrets(array $state): array
    {
        if (empty($state['payload']['admin']['password_encrypted'])) {
            return $state;
        }

        try {
            $state['payload']['admin']['password'] = Crypt::decryptString(
                $state['payload']['admin']['password']
            );
        } catch (Throwable $e) {
            // مفتاح تغيّر بين نبضتين — تُترك كما هي ليُبلَّغ الخطأ عند الإنشاء
        }

        unset($state['payload']['admin']['password_encrypted']);

        return $state;
    }

    public function clear(): void
    {
        if (file_exists(self::statePath())) {
            unlink(self::statePath());
        }
    }

    /** هل توجد عملية تثبيت متوقّفة يمكن استئنافها؟ */
    public function hasResumableRun(): bool
    {
        $state = $this->load();

        return $state !== null && $state['cursor'] < count($state['tasks']);
    }

    /* ==================== أدوات ==================== */

    /** سطر واحد من رسالة الخطأ — التفصيل الكامل يُعرض منفصلاً. */
    protected function summarise(string $message): string
    {
        $first = trim(strtok($message, "\n"));

        return mb_strlen($first) > 160 ? mb_substr($first, 0, 157).'…' : $first;
    }

    protected function result(
        bool $done,
        bool $ok,
        int $index,
        int $total,
        string $type,
        string $title,
        string $message,
        ?string $detail,
        float $duration,
    ): array {
        return compact('done', 'ok', 'index', 'total', 'type', 'title', 'message', 'detail', 'duration');
    }
}
