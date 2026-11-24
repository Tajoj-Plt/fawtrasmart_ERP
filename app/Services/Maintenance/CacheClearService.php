<?php

namespace App\Services\Maintenance;

use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * مسح الذاكرة المؤقتة من شاشة الإعدادات.
 *
 * لماذا من الواجهة؟ للسبب نفسه الذي أُنشئت لأجله شاشة «تحديث النظام»:
 * التركيب الغالب هنا استضافةٌ مشتركة بلا SSH. وأشيع عطلٍ بعد رفع تحديثٍ
 * بالـFTP هو كاشٌ قديم — قالبٌ مترجَم يشير إلى حقلٍ حُذف، أو خريطة إعدادات
 * محفوظة تُظهر تطبيقاً عطّلتَه، أو مسارٌ مخزَّن لا يعرف الصفحة الجديدة.
 * كلّها تُحَلّ بأمرٍ واحد لا سبيل إلى تشغيله بلا طرفية.
 *
 * ما لا تفعله هذه الخدمة أبداً:
 * - لا تلمس بياناتك: الكاش نسخةٌ ثانية من شيءٍ مصدرُه القاعدة أو الملفّات.
 *   أسوأ ما يقع بمسحه هو بطءُ أوّل طلبٍ بعده حتى يُعاد بناؤه.
 * - لا تقبل اسم أمرٍ من الطلب: تأخذ مفتاحاً وتطابقه على `targets()`.
 *   وهي قاعدة `CronRegistry` نفسها — لا يصل إلى `Artisan::call` نصٌّ
 *   جاء من المتصفّح.
 * - لا `optimize` ولا `config:cache`: بناءُ الكاش قرارُ نشرٍ لا زرُّ شاشة،
 *   و`config:cache` على تركيبٍ ناقص يُسقط النظام كلَّه إلى أن يُمسَح يدوياً.
 */
class CacheClearService
{
    /** حدّ زمني — `view:clear` على آلاف القوالب يتجاوز الافتراضي. */
    private const TIME_LIMIT = 300;

    /**
     * ما يمكن مسحه — القائمة البيضاء الوحيدة.
     *
     * `symptom` ليست زينة: المستخدم هنا لا يعرف الفرق بين «كاش الإعدادات»
     * و«كاش التطبيق»، وإنما يعرف العطل الذي يراه. فالعَرَض هو مدخلُه.
     *
     * @return array<string, array{label: string, hint: string, symptom: string, command: string, icon: string}>
     */
    public static function targets(): array
    {
        return [
            'app' => [
                'label' => 'كاش التطبيق',
                'hint' => 'القيم التي يحفظها النظام في الذاكرة ليقرأها مرّةً بدل كل طلب: '
                    .'خريطة التطبيقات المفعَّلة، اللغات النشطة والعملات، ربط الحسابات المحاسبية، '
                    .'ألوان الواجهة، إعدادات البريد، حدود الباقة.',
                'symptom' => 'غيّرتَ إعداداً وحُفِظ، لكن الشاشات ما زالت تعمل بالقيمة القديمة.',
                'command' => 'cache:clear',
                'icon' => 'database',
            ],
            'views' => [
                'label' => 'القوالب المترجَمة',
                'hint' => 'كل ملفّ Blade يُترجَم إلى PHP مرّةً ويُحفَظ. الملفّ المترجَم لا يُعاد '
                    .'بناؤه إلا إذا تغيّر تاريخ تعديل أصله — ورفعُ الملفّات بالـFTP لا يضمن ذلك.',
                'symptom' => 'رفعتَ تحديثاً فظهرت صفحةٌ بخطأ «متغيّر غير معرَّف»، أو بقيت الصفحة على شكلها القديم.',
                'command' => 'view:clear',
                'icon' => 'layout',
            ],
            'config' => [
                'label' => 'كاش الإعدادات',
                'hint' => 'ملفّات <code>config/</code> و<code>.env</code> مجموعةً في ملفٍّ واحد. '
                    .'ما دام موجوداً، فتعديل <code>.env</code> لا أثر له إطلاقاً.',
                'symptom' => 'عدّلتَ <code>.env</code> — بيانات قاعدة أو بريد أو مفتاح — ولم يتغيّر شيء.',
                'command' => 'config:clear',
                'icon' => 'sliders',
            ],
            'routes' => [
                'label' => 'كاش المسارات',
                'hint' => 'جدول عناوين النظام كلِّه مبنيٌّ مسبقاً. المسار الذي أضافه تحديثٌ جديد '
                    .'غير موجودٍ فيه حتى يُمسَح.',
                'symptom' => 'شاشةٌ جديدة تُعطي «الصفحة غير موجودة»، أو خطأ «Route ... not defined».',
                'command' => 'route:clear',
                'icon' => 'git-branch',
            ],
            'permissions' => [
                'label' => 'كاش الصلاحيات',
                'hint' => 'قائمة الصلاحيات والأدوار تُحفَظ لمدّة. الصلاحية التي مُنحت للتوّ '
                    .'لا يراها النظام قبل انتهائها.',
                'symptom' => 'منحتَ دوراً صلاحيةً ولم يظهر بندُها في القائمة، أو ما زالت الشاشة تُرفَض.',
                'command' => 'permission:cache-reset',
                'icon' => 'shield',
            ],
            'events' => [
                'label' => 'كاش الأحداث',
                'hint' => 'ربط الأحداث بمستمعيها مبنيٌّ مسبقاً. مستمعٌ جديد لا يُستدعى حتى يُمسَح.',
                'symptom' => 'إشعارٌ أو تسجيلٌ في السجلّ توقّف عن العمل بعد تحديث.',
                'command' => 'event:clear',
                'icon' => 'bell',
            ],
            'compiled' => [
                'label' => 'ملفّات الأصناف المترجَمة',
                'hint' => 'خريطة مقدِّمي الخدمة والحِزم في <code>bootstrap/cache</code>. '
                    .'تُبنى تلقائياً، وتَقدُم حين تُفعَّل وحدةٌ أو تُعطَّل.',
                'symptom' => 'خطأ «Class not found» بعد تفعيل وحدة أو تعطيلها.',
                'command' => 'clear-compiled',
                'icon' => 'package',
            ],
        ];
    }

    /** هل المفتاح مسجَّل؟ */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::targets());
    }

    /**
     * مسح نوعٍ واحد.
     *
     * @return array{ok: bool, output: string}
     */
    public function clear(string $key): array
    {
        $target = self::targets()[$key] ?? null;

        if ($target === null) {
            return ['ok' => false, 'output' => "نوعٌ غير مسجَّل: {$key}"];
        }

        return $this->run($target['command']);
    }

    /**
     * مسح كل الأنواع بالترتيب.
     *
     * لا تتوقّف عند أوّل فشل — على خلاف `SystemUpdateService::runAll()`.
     * الفرق أن الخطوات هنا مستقلّة تماماً: فشلُ مسح القوالب (ملفٌّ مقفل
     * مثلاً) لا يجعل مسح المسارات خطأً، ووقفُ البقيّة يترك عطلاً قائماً
     * كان سيُحَلّ في الخطوة التالية.
     *
     * @return array{ok: bool, steps: array<int, array{label: string, state: string, output: string}>}
     */
    public function clearAll(): array
    {
        $steps = [];
        $ok = true;

        foreach (self::targets() as $key => $target) {
            $result = $this->clear($key);
            $ok = $ok && $result['ok'];

            $steps[] = [
                'label' => $target['label'],
                'state' => $result['ok'] ? 'ok' : 'failed',
                'output' => $result['output'],
            ];
        }

        return ['ok' => $ok, 'steps' => $steps];
    }

    /**
     * حالة الكاش الآن — ما الموجود فعلاً على القرص.
     *
     * الغاية منها أن يرى المستخدم أثر الزرّ: عدّادٌ نزل من ٤٠٠ ملفّ إلى صفر
     * جوابٌ أوضح من سطر «تمّ بنجاح» لا يقابله شيء في الشاشة.
     *
     * @return array{driver: string, app: array{files: int, bytes: int}, views: array{files: int, bytes: int}, bootstrap: array<string, bool>}
     */
    public function status(): array
    {
        return [
            'driver' => (string) config('cache.default'),
            'app' => $this->folder(storage_path('framework/cache/data')),
            'views' => $this->folder(storage_path('framework/views'), 'php'),
            'bootstrap' => [
                'config' => file_exists(base_path('bootstrap/cache/config.php')),
                'routes' => (bool) glob(base_path('bootstrap/cache/routes-*.php')),
                'events' => file_exists(base_path('bootstrap/cache/events.php')),
                'services' => file_exists(base_path('bootstrap/cache/services.php')),
            ],
        ];
    }

    // ==================== الداخل ====================

    /**
     * عدد ملفّات مجلّدٍ وحجمها — بالتنقّل في الشجرة كاملة.
     *
     * كاش الملفّات يوزّع مدخلاته على مجلّداتٍ فرعية من حرفين، فالعدّ في
     * المستوى الأوّل وحده يعطي عشرات بدل آلاف.
     *
     * @return array{files: int, bytes: int}
     */
    private function folder(string $path, ?string $extension = null): array
    {
        if (! is_dir($path)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $files = 0;
        $bytes = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (! $file->isFile() || $file->getFilename() === '.gitignore') {
                    continue;
                }

                if ($extension !== null && $file->getExtension() !== $extension) {
                    continue;
                }

                $files++;
                $bytes += (int) $file->getSize();
            }
        } catch (Throwable) {
            // مجلّدٌ بلا صلاحية قراءة: الشاشة تعرض ما استطاعت لا أن تسقط
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    /**
     * @return array{ok: bool, output: string}
     */
    private function run(string $command): array
    {
        @set_time_limit(self::TIME_LIMIT);

        try {
            $status = Artisan::call($command);
            $output = trim(Artisan::output());

            return [
                'ok' => $status === 0,
                'output' => $output !== '' ? $output : 'تمّ التنفيذ بلا مخرَج.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'output' => trim(Artisan::output())."\n\n".$e->getMessage(),
            ];
        }
    }
}
