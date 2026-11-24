<?php

namespace Modules\Installer\Services;

/**
 * فحص ما يحتاجه النظام قبل التثبيت: إصدار PHP، الامتدادات، صلاحيات الكتابة.
 *
 * كل فحص يُرجع صفّاً موحّداً {key, label, required, current, passed} فالقالب
 * يعرض القائمة دون أن يعرف شيئاً عن طبيعة الفحص.
 */
class RequirementsChecker
{
    /**
     * الامتدادات المطلوبة — مشتقّة من استعمال المشروع الفعلي لا من قائمة عامة:
     * pdo_mysql للاتصال، zip وgd لـ maatwebsite/excel وintervention/image،
     * intl وmbstring للعربية، bcmath للحسابات المالية، fileinfo للمرفقات.
     */
    public const EXTENSIONS = [
        'pdo_mysql' => 'PDO MySQL — الاتصال بقاعدة البيانات',
        'mbstring' => 'mbstring — معالجة النصوص العربية',
        'openssl' => 'OpenSSL — التشفير ومفتاح التطبيق',
        'tokenizer' => 'Tokenizer',
        'json' => 'JSON',
        'curl' => 'cURL — الاتصالات الخارجية (SMS، بوابات الدفع)',
        'fileinfo' => 'Fileinfo — رفع المرفقات',
        'gd' => 'GD — الشعارات والباركود والصور',
        'zip' => 'Zip — تصدير Excel والنسخ الاحتياطي',
        'bcmath' => 'BCMath — دقّة الحسابات المالية',
        'xml' => 'XML',
        'ctype' => 'Ctype',
    ];

    /**
     * المسارات التي يكتب فيها النظام أثناء التشغيل.
     * `.env` مذكور لأن معالج التثبيت نفسه يكتبه في خطوة قاعدة البيانات.
     */
    public const WRITABLE_PATHS = [
        'storage/app',
        'storage/framework',
        'storage/framework/views',
        'storage/framework/sessions',
        'storage/framework/cache',
        'storage/logs',
        'bootstrap/cache',
        'public/storage',
    ];

    public const MIN_PHP = '8.2.0';

    /**
     * @return array<string, array<int, array{key:string,label:string,required:string,current:string,passed:bool}>>
     */
    public function all(): array
    {
        return [
            'php' => $this->php(),
            'extensions' => $this->extensions(),
            'permissions' => $this->permissions(),
        ];
    }

    /** هل اجتاز النظام كل الفحوص الإلزامية؟ */
    public function passes(): bool
    {
        foreach ($this->all() as $group) {
            foreach ($group as $check) {
                if (! $check['passed']) {
                    return false;
                }
            }
        }

        return true;
    }

    /** قائمة مسطّحة بما رسب — تُستعمل في رسالة الخطأ وفي مخرجات الطرفية. */
    public function failures(): array
    {
        $failed = [];

        foreach ($this->all() as $group) {
            foreach ($group as $check) {
                if (! $check['passed']) {
                    $failed[] = $check;
                }
            }
        }

        return $failed;
    }

    protected function php(): array
    {
        return [[
            'key' => 'php_version',
            'label' => 'إصدار PHP',
            'required' => '>= '.self::MIN_PHP,
            'current' => PHP_VERSION,
            'passed' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
        ]];
    }

    protected function extensions(): array
    {
        $checks = [];

        foreach (self::EXTENSIONS as $ext => $label) {
            $loaded = extension_loaded($ext);

            $checks[] = [
                'key' => 'ext_'.$ext,
                'label' => $label,
                'required' => 'مُفعَّل',
                'current' => $loaded ? 'مُفعَّل' : 'غير مُفعَّل',
                'passed' => $loaded,
            ];
        }

        return $checks;
    }

    protected function permissions(): array
    {
        $checks = [];

        foreach (self::WRITABLE_PATHS as $relative) {
            $absolute = base_path($relative);

            // المسار غير الموجود يُنشأ الآن بدل الرسوب: أدلّة مثل
            // storage/framework/cache تغيب في نسخةٍ منزوعة من git، وإنشاؤها
            // أنفع للمستخدم من مطالبته بإنشائها يدوياً
            if (! file_exists($absolute)) {
                @mkdir($absolute, 0755, true);
            }

            $writable = is_dir($absolute) && is_writable($absolute);

            $checks[] = [
                'key' => 'path_'.str_replace('/', '_', $relative),
                'label' => $relative,
                'required' => 'قابل للكتابة',
                'current' => $writable ? 'قابل للكتابة' : (is_dir($absolute) ? 'للقراءة فقط' : 'غير موجود'),
                'passed' => $writable,
            ];
        }

        // .env يُفحص على أبيه حين لا يكون موجوداً: الكتابة الأولى إنشاءٌ
        // داخل جذر المشروع لا تعديلٌ لملف
        $envPath = base_path('.env');
        $envWritable = file_exists($envPath) ? is_writable($envPath) : is_writable(base_path());

        $checks[] = [
            'key' => 'path_env',
            'label' => '.env',
            'required' => 'قابل للكتابة',
            'current' => $envWritable ? 'قابل للكتابة' : 'غير قابل للكتابة',
            'passed' => $envWritable,
        ];

        return $checks;
    }
}
