<?php

namespace App\Console\Commands;

use App\Models\SmtpSetting;
use App\Models\User;
use App\Services\MailerConfigurator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * يبني إعدادات بريد للاختبار من قيم ملف البيئة، ويرسل رسالة تحقّق.
 *
 * جدول `smtp_settings` كان فارغاً لأن الحفظ من الشاشة كان يفشل (عمود كلمة
 * المرور أضيق من القيمة المشفَّرة). هذا الأمر يملأه بقيم `.env` العاملة
 * فيصير هناك ما يُختبر عليه فعلاً.
 */
class SeedSmtpSettings extends Command
{
    protected $signature = 'smtp:seed
                            {--to= : عنوان يُرسل إليه بريد تحقّق بعد الحفظ}
                            {--user= : معرّف المستخدم المالك للإعداد}';

    protected $description = 'إنشاء إعدادات بريد للاختبار من ملف البيئة، مع إرسال رسالة تحقّق اختيارية';

    public function handle(MailerConfigurator $mailer): int
    {
        $host = config('mail.mailers.smtp.host');
        $username = config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');

        if (! $host || ! $username || ! $password) {
            $this->error('ملف البيئة لا يحمل بيانات بريد كاملة (MAIL_HOST / MAIL_USERNAME / MAIL_PASSWORD).');

            return self::FAILURE;
        }

        $userId = $this->option('user') ?: User::query()->orderBy('id')->value('id');

        if (! $userId) {
            $this->error('لا يوجد مستخدم في قاعدة البيانات لربط الإعداد به.');

            return self::FAILURE;
        }

        $settings = SmtpSetting::updateOrCreate(['user_id' => $userId], [
            'use_smtp' => true,
            'is_active' => true,
            'sender_name' => config('mail.from.name') ?: 'فوترة',
            'email' => config('mail.from.address') ?: $username,
            'password' => $password,
            'smtp_username' => $username,
            'smtp_host' => $host,
            'smtp_port' => (int) (config('mail.mailers.smtp.port') ?: 587),
            'smtp_encryption' => config('mail.mailers.smtp.encryption') ?: 'tls',
            'ssl_required' => config('mail.mailers.smtp.encryption') === 'ssl',
        ]);

        $mailer->forget();

        $this->info('حُفظت إعدادات البريد في قاعدة البيانات:');
        $this->table(['الحقل', 'القيمة'], [
            ['المستخدم', $userId],
            ['الخادم', $settings->smtp_host],
            ['المنفذ', $settings->smtp_port],
            ['التشفير', $settings->smtp_encryption],
            ['اسم المستخدم', $settings->smtp_username],
            ['كلمة المرور', $settings->password ? 'محفوظة ومشفَّرة (' . strlen($settings->password) . ' حرف)' : 'مفقودة'],
            ['المرسِل', $settings->sender_name . ' <' . $settings->email . '>'],
        ]);

        $active = $mailer->describe();
        $this->line('  النظام يرسل الآن عبر: <info>' . $active['label'] . ' — ' . $active['host'] . '</info>');

        $to = $this->option('to');

        if (! $to) {
            $this->comment('لم يُطلب إرسال تحقّق. استعمل --to=البريد لإرسال رسالة فعلية.');

            return self::SUCCESS;
        }

        return $this->sendVerification($mailer, $settings, $to);
    }

    private function sendVerification(MailerConfigurator $mailer, SmtpSetting $settings, string $to): int
    {
        // الإعداد يُطبَّق صراحةً: بلا هذا يمرّ الاختبار بمُرسِل ملف البيئة
        // فيبدو ناجحاً دون أن يمسّ ما حُفظ في قاعدة البيانات
        $mailer->apply($settings);

        $this->line("  جارٍ الإرسال إلى <comment>{$to}</comment> عبر <comment>{$settings->smtp_host}</comment> …");

        try {
            Mail::raw(
                "هذه رسالة تحقّق من نظام فوترة.\n\n"
                . "الخادم: {$settings->smtp_host}:{$settings->smtp_port}\n"
                . "التشفير: {$settings->smtp_encryption}\n"
                . "المرسِل: {$settings->email}\n\n"
                . 'وصولها يعني أن إعدادات البريد المحفوظة في شاشة الإعدادات تعمل فعلاً.',
                function ($message) use ($to, $settings) {
                    $message->to($to)
                        ->from($settings->email, $settings->sender_name)
                        ->subject('تحقّق من إعدادات البريد — نظام فوترة');
                }
            );
        } catch (\Throwable $e) {
            $this->error('فشل الإرسال: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("تم الإرسال إلى {$to}. تحقّق من صندوق الوارد (ومجلد الرسائل غير المرغوبة).");

        return self::SUCCESS;
    }
}
