<?php

namespace App\Services;

use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * يجعل إعدادات البريد المحفوظة في الشاشة هي التي يُرسل بها النظام فعلاً.
 *
 * **لماذا؟**
 *
 * كانت شاشة SMTP تحفظ في جدول `smtp_settings` ولا يقرأه أحد — لا المصيّر
 * ولا أي خدمة إرسال. حتى زرّ «إرسال بريد تجريبي» كان يستعمل مُرسِل `.env`
 * فيقول «نجح» بينما لم يمسّ إعدادات المستخدم إطلاقاً. النتيجة: شاشة تبدو
 * تعمل ولا أثر لها في أي بريد يخرج من النظام.
 *
 * هنا تُقرأ الإعدادات وتُطبَّق على مُرسِل Laravel وقت التشغيل.
 */
class MailerConfigurator
{
    protected const CACHE_KEY = 'mail.smtp-settings';

    protected const CACHE_TTL = 600;

    /**
     * يطبّق الإعدادات المحفوظة على مُرسِل النظام.
     *
     * @return bool هل طُبِّق إعداد محفوظ، أم بقي مُرسِل `.env`؟
     */
    public function apply(?SmtpSetting $settings = null): bool
    {
        $config = $settings ? $this->toConfig($settings) : $this->cachedConfig();

        if ($config === null) {
            return false;
        }

        $password = $this->decrypt($config['password']);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $config['host'],
            'mail.mailers.smtp.port' => $config['port'],
            'mail.mailers.smtp.username' => $config['username'],
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.encryption' => $config['encryption'],
            'mail.from.address' => $config['from_address'],
            'mail.from.name' => $config['from_name'],
        ]);

        // المُرسِل يُبنى مرة واحدة ويُحتفظ به؛ بلا إسقاطه يبقى على الإعداد
        // القديم مهما غُيّرت القيم
        $this->purgeResolvedMailer();

        return true;
    }

    /**
     * وصف موجز لما يُرسل به النظام الآن — يُعرض في الشاشة.
     */
    public function describe(): array
    {
        $config = $this->cachedConfig();

        if ($config === null) {
            return [
                'source' => 'env',
                'host' => config('mail.mailers.smtp.host'),
                'from' => config('mail.from.address'),
                'label' => 'إعدادات ملف البيئة (.env)',
            ];
        }

        return [
            'source' => 'database',
            'host' => $config['host'],
            'from' => $config['from_address'],
            'label' => 'الإعدادات المحفوظة في هذه الشاشة',
        ];
    }

    /** يُستدعى بعد كل حفظ أو إعادة تعيين */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * الصفّ المعتمد: إعداد المستخدم الحالي إن وُجد، وإلا أول إعداد مفعَّل.
     *
     * البريد يُرسَل أيضاً من مهام مجدولة بلا مستخدم مسجَّل، فلا يصحّ ربط
     * المُرسِل بالجلسة وحدها.
     */
    public function activeSettings(): ?SmtpSetting
    {
        $query = SmtpSetting::query()->where('use_smtp', true)->where('is_active', true);

        if (auth()->check()) {
            $own = (clone $query)->where('user_id', auth()->id())->first();

            if ($own) {
                return $own;
            }
        }

        return $query->orderBy('id')->first();
    }

    // ==================== الداخل ====================

    /**
     * الإعداد مخزَّناً مؤقتاً — وكلمة المرور تبقى مشفَّرة داخل الذاكرة المؤقتة.
     *
     * تخزينها مفكوكة يعني كلمة مرور صريحة في ملفات الكاش، وهو أسوأ من
     * العمود المشفَّر الذي جاءت منه.
     */
    protected function cachedConfig(): ?array
    {
        try {
            $config = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                $settings = $this->activeSettings();

                return $settings ? $this->toConfig($settings) : ['empty' => true];
            });
        } catch (\Throwable $e) {
            // لا جدول `smtp_settings` بعد، أو لا اتصال بالقاعدة أصلاً.
            //
            // هذه الدالة تُستدعى من `AppServiceProvider::boot()` — أي على كل
            // طلب وكل أمر artisan. وكان الاستعلام مكشوفاً، فقاعدةٌ لم تُهاجَر
            // بعد تُسقط النظام كلّه من الإقلاع: حتى `php artisan migrate`
            // نفسه يفشل، فلا سبيل إلى إصلاح الحالة التي سبّبت العطل.
            //
            // والاستثناء يخرج من `remember` قبل الكتابة، فلا تُخزَّن حالةُ
            // فشلٍ عابرة عشر دقائق.
            return null;
        }

        return ($config['empty'] ?? false) ? null : $config;
    }

    protected function toConfig(SmtpSetting $settings): array
    {
        $raw = $settings->getAttributes();

        return [
            'host' => $settings->smtp_host,
            'port' => (int) ($settings->smtp_port ?: 587),
            'username' => $settings->smtp_username ?: $settings->email,
            'password' => $raw['password'] ?? null,
            'encryption' => $settings->smtp_encryption ?: 'tls',
            'from_address' => $settings->email,
            'from_name' => $settings->sender_name,
        ];
    }

    protected function decrypt(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            // النموذج يحفظ بـ `encrypt()` وهي تُسلسِل القيمة، فيجب فكّها
            // بـ `decrypt()` لا `decryptString()` وإلا رجعت كلمة مرور فارغة
            return Crypt::decrypt($value);
        } catch (\Throwable $e) {
            // مفتاح التطبيق تغيّر بعد الحفظ: الإرسال سيفشل برسالة مصادقة
            // واضحة بدل أن يمرّ بكلمة مرور مشوّهة
            return null;
        }
    }

    protected function purgeResolvedMailer(): void
    {
        try {
            Mail::purge('smtp');
        } catch (\Throwable $e) {
            app()->forgetInstance('mail.manager');
            app()->forgetInstance('mailer');
        }
    }
}
