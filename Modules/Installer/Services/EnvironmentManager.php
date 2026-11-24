<?php

namespace Modules\Installer\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;

/**
 * اختبار الاتصال بقاعدة البيانات وكتابة `.env`.
 *
 * الكتابة تعديلٌ موضعي لا استبدال: `.env` يحمل مفاتيح لا يعرفها معالج
 * التثبيت (بوابات دفع، مفاتيح خدمات، إعدادات خادم). إعادةُ توليده كاملاً
 * تمحوها صامتةً، فالمكتوب هنا هو ما طُلب تغييره فقط.
 */
class EnvironmentManager
{
    /**
     * اختبار الاتصال باتصالٍ مباشر عبر PDO لا عبر إعداد Laravel.
     *
     * السبب أن اتصال Laravel مُهيَّأ من `.env` الحالي وقتَ الإقلاع، فاختبارُ
     * بياناتٍ لم تُكتب بعد يتطلّب اتصالاً منفصلاً بها.
     *
     * @return array{ok:bool, message:string, server_version?:string}
     */
    public function testConnection(array $config): array
    {
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        $endpoint = $this->validateEndpoint($config);

        if ($endpoint === null) {
            return [
                'ok' => false,
                'message' => 'عنوان الخادم أو المنفذ غير صالح. المضيف اسمٌ أو عنوان IP، والمنفذ رقم بين 1 و65535.',
            ];
        }

        [$host, $port] = $endpoint;

        try {
            // الاتصال بالخادم أولاً بلا اسم قاعدة: يفرّق بين «بيانات دخول
            // خاطئة» و«قاعدة غير موجودة»، وهما خطآن علاجهما مختلف
            $pdo = new PDO(
                "mysql:host={$host};port={$port}",
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );

            $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            $exists = $pdo->query(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '.$pdo->quote($database)
            )->fetchColumn();

            if ($exists === false) {
                return [
                    'ok' => false,
                    'message' => "تم الاتصال بخادم قاعدة البيانات، لكن القاعدة «{$database}» غير موجودة. أنشئها ثم أعد المحاولة، أو فعّل خيار الإنشاء التلقائي.",
                    'can_create' => true,
                    'server_version' => $version,
                ];
            }

            return [
                'ok' => true,
                'message' => "تم الاتصال بنجاح (MySQL {$version}).",
                'server_version' => $version,
            ];
        } catch (PDOException $e) {
            return [
                'ok' => false,
                'message' => 'تعذّر الاتصال: '.$e->getMessage(),
            ];
        }
    }

    /**
     * إنشاء القاعدة إن أذن المستخدم. الترميز utf8mb4 إلزامي لا خياراً:
     * النظام يخزّن نصوصاً عربية ورموزاً، وutf8 القديم يبترها.
     */
    public function createDatabase(array $config): array
    {
        $database = $config['database'] ?? '';

        if ($database === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            return ['ok' => false, 'message' => 'اسم قاعدة البيانات غير صالح — يُقبل الحرف والرقم والشرطة السفلية فقط.'];
        }

        $endpoint = $this->validateEndpoint($config);

        if ($endpoint === null) {
            return ['ok' => false, 'message' => 'عنوان الخادم أو المنفذ غير صالح.'];
        }

        [$host, $port] = $endpoint;

        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port}",
                $config['username'] ?? '',
                $config['password'] ?? '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return ['ok' => true, 'message' => "تم إنشاء القاعدة «{$database}»."];
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => 'تعذّر إنشاء القاعدة: '.$e->getMessage()];
        }
    }

    /**
     * كتابة مفاتيح على `.env` مع الإبقاء على ما سواها.
     *
     * @param  array<string, string|null>  $values
     */
    public function write(array $values): void
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            // لا `.env` ولا `.env.example`؟ نبدأ من ملف فارغ بدل الانهيار
            $example = base_path('.env.example');
            copy(file_exists($example) ? $example : '/dev/null', $path);

            if (! file_exists($path)) {
                file_put_contents($path, '');
            }
        }

        $contents = file_get_contents($path);

        foreach ($values as $key => $value) {
            $contents = $this->setKey($contents, $key, $value);
        }

        file_put_contents($path, $contents);

        // يمنع بقاء إعدادٍ قديم في ذاكرة الطلب الحالي بعد الكتابة
        $this->refreshRuntimeConfig($values);
    }

    /**
     * استبدال قيمة مفتاح، أو إلحاقه إن لم يكن موجوداً.
     */
    protected function setKey(string $contents, string $key, ?string $value): string
    {
        $escaped = $this->quote((string) $value);

        // ^KEY= في أول السطر فقط: مفتاح مذكور داخل تعليق أو كجزء من مفتاح
        // أطول (DB_HOST مقابل DB_HOSTNAME) يجب ألّا يُصاب
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            return preg_replace($pattern, $key.'='.$escaped, $contents, 1);
        }

        return rtrim($contents, "\r\n")."\n".$key.'='.$escaped."\n";
    }

    /**
     * تغليف القيمة بعلامتَي تنصيص حين تحتاجها — كلمة مرور فيها مسافة أو #
     * تُقرأ مبتورةً بدونهما.
     *
     * **وحذف محارف التحكّم أولاً — وهذه ثغرة أمنية لا تجميل.**
     *
     * `.env` ملفٌّ سطرُه مفتاح. فقيمةٌ فيها سطرٌ جديد تُنتج مفاتيح جديدة مهما
     * غُلِّفت بعلامات تنصيص. جُرِّب فعلاً: اسم منشأة قيمته
     * `"شركتي\nAPP_KEY=base64:PWNED"` أنتج في الملف سطراً مستقلاً
     * `APP_KEY=base64:PWNED` **قبل** المفتاح الأصلي — وDotenv يأخذ الأول.
     * ومن ملك `APP_KEY` فكّ كل كوكي وزوّر أي جلسة.
     *
     * والحذف لا الرفض: اسم منشأة فيه سطرٌ جديد خطأُ لصقٍ غالباً لا هجوم،
     * وإفشال التثبيت لأجله عقوبةٌ بلا داعٍ. أما `=` و`#` فيكفيهما التغليف.
     */
    protected function quote(string $value): string
    {
        // \x00 يبتر الملف عند بعض الدوال، و\r\n يفتح سطراً جديداً
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s"\'#=]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }

    /**
     * توجيه اتصال هذا الطلب إلى القاعدة المختارة — بلا كتابةٍ في `.env`.
     *
     * تُستدعى في كل طلب من مدخلات الجلسة: المعالج يعمل على القاعدة الجديدة
     * من لحظة اختيارها، بينما يبقى الملف على حاله حتى نهاية التنفيذ.
     *
     * @param  array{host?:string, port?:string|int, database?:string, username?:string, password?:string}  $data
     */
    public function applyRuntimeDatabase(array $data): void
    {
        $this->refreshRuntimeConfig(array_filter([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['host'] ?? null,
            'DB_PORT' => $data['port'] ?? null,
            'DB_DATABASE' => $data['database'] ?? null,
            'DB_USERNAME' => $data['username'] ?? null,
            'DB_PASSWORD' => $data['password'] ?? '',
        ], fn ($v) => $v !== null));
    }

    /**
     * مزامنة إعداد الطلب الحالي مع ما كُتب، وإعادة تهيئة اتصال قاعدة البيانات.
     *
     * بدون هذا يبقى `migrate` في الخطوة التالية متّصلاً بالقاعدة القديمة —
     * والمستخدم يرى نجاحاً على قاعدةٍ غير التي أدخلها.
     */
    protected function refreshRuntimeConfig(array $values): void
    {
        $map = [
            'DB_CONNECTION' => 'database.default',
            'DB_HOST' => 'database.connections.mysql.host',
            'DB_PORT' => 'database.connections.mysql.port',
            'DB_DATABASE' => 'database.connections.mysql.database',
            'DB_USERNAME' => 'database.connections.mysql.username',
            'DB_PASSWORD' => 'database.connections.mysql.password',
            'APP_NAME' => 'app.name',
            'APP_URL' => 'app.url',
            'APP_LOCALE' => 'app.locale',
            'APP_TIMEZONE' => 'app.timezone',
        ];

        $touchedDatabase = false;

        foreach ($values as $key => $value) {
            if (! isset($map[$key])) {
                continue;
            }

            Config::set($map[$key], $value);

            if (str_starts_with($key, 'DB_')) {
                $touchedDatabase = true;
            }
        }

        if ($touchedDatabase) {
            DB::purge('mysql');
            DB::reconnect('mysql');
        }
    }

    /**
     * التحقّق من المضيف والمنفذ قبل بناء سلسلة الاتصال.
     *
     * **سلسلة DSN تُفسَّر معاملاتٍ مفصولة بفاصلة منقوطة**، فمضيفٌ قيمته
     * `127.0.0.1;unix_socket=/tmp/x` يُدخل معاملاً لم يقصده أحد. جُرِّب فعلاً
     * فنجح الاتصال — أي أن المهاجم يوجّه الاتصال إلى مقبسٍ يملكه بدل الخادم
     * المقصود. والفاصلة المنقوطة ليست وحدها: أي محرف خارج الاسم أو العنوان
     * لا محلّ له هنا.
     *
     * @return array{0:string,1:int}|null [المضيف، المنفذ] أو null إن كان غير صالح
     */
    protected function validateEndpoint(array $config): ?array
    {
        $host = trim((string) ($config['host'] ?? '127.0.0.1'));
        $port = trim((string) ($config['port'] ?? '3306'));

        // اسم مضيف صالح (RFC 1123) أو عنوان IPv4/IPv6
        $isHostname = preg_match('/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/', $host) === 1;
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;

        if (! $isHostname && ! $isIp) {
            return null;
        }

        if (! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
            return null;
        }

        return [$host, (int) $port];
    }

    /** القيم المعروضة مبدئياً في نموذج قاعدة البيانات. */
    public function currentDatabaseConfig(): array
    {
        return [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', ''),
            'username' => env('DB_USERNAME', 'root'),
            'password' => '',
        ];
    }
}
