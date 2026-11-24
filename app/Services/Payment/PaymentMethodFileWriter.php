<?php

namespace App\Services\Payment;

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ملفّ إعدادات طريقة الدفع — يُكتب على القرص عند كل حفظ.
 *
 * **لماذا ملفّ وقاعدة البيانات موجودة؟** ما يقرأ بوابة الدفع ليس دائماً
 * تطبيق Laravel: نصوص تشغيل، خطّافات الويب هوك، وأدوات تُشغَّل من الطرفية
 * تحتاج الإعداد دون فتح اتصال بالقاعدة ولا فكّ تعمية `APP_KEY`. الملفّ
 * مصدرٌ واحد لهذه الجهات، ويُعاد توليده عند كل تغيير فلا يتباعد عن الصفّ.
 *
 * **القرص `local` لا `public`.** الملفّ يحمل مفاتيح حيّة؛ وضعه تحت
 * `storage/app/public` يجعله قابلاً للتنزيل برابط مباشر من الإنترنت.
 */
class PaymentMethodFileWriter
{
    /** مجلَّد الملفات داخل القرص `local` */
    public const DIRECTORY = 'payment-methods';

    private const DISK = 'local';

    /**
     * يكتب ملفّ الطريقة ويعيد مساره، أو `null` إن تعذّرت الكتابة.
     *
     * الفشل لا يُسقط الحفظ: طريقة الدفع محفوظة في القاعدة، وملفٌّ لم
     * يُكتب مشكلة تشغيلية تُسجَّل ويعالجها المسؤول — لا سبب لرفض عمل
     * المستخدم كلّه.
     */
    public function write(PaymentMethod $method): ?string
    {
        $path = $this->pathFor($method);

        try {
            // الرمز قد يتغيّر فينتقل الملفّ — القديم يُحذف كي لا يبقى
            // ملفّ يتيم يحمل مفاتيح حيّة باسم لم يعد مستعملاً
            if ($method->config_file_path && $method->config_file_path !== $path) {
                Storage::disk(self::DISK)->delete($method->config_file_path);
            }

            Storage::disk(self::DISK)->put(
                $path,
                json_encode($this->payload($method), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
            );

            return $path;
        } catch (\Throwable $e) {
            Log::warning('تعذّرت كتابة ملفّ طريقة الدفع', [
                'payment_method' => $method->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * يحذف ملفّ الطريقة — يُستدعى عند حذفها.
     */
    public function delete(PaymentMethod $method): void
    {
        $path = $method->config_file_path ?: $this->pathFor($method);

        try {
            Storage::disk(self::DISK)->delete($path);
        } catch (\Throwable $e) {
            Log::warning('تعذّر حذف ملفّ طريقة الدفع', [
                'payment_method' => $method->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** المسار الكامل على القرص — للعرض في الواجهة */
    public function absolutePath(string $path): string
    {
        return Storage::disk(self::DISK)->path($path);
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && Storage::disk(self::DISK)->exists($path);
    }

    /**
     * اسم الملفّ من رمز الطريقة، أو من مُعرّفها حين لا رمز لها.
     */
    public function pathFor(PaymentMethod $method): string
    {
        $slug = Str::slug((string) $method->code) ?: 'method-'.$method->id;

        return self::DIRECTORY.'/'.$slug.'.json';
    }

    /**
     * محتوى الملفّ.
     *
     * المفاتيح تُكتب صريحةً — وهذا هو الغرض: جهةٌ لا تملك `APP_KEY` تحتاج
     * قراءتها. لذلك يبقى الملفّ خارج المجلَّد العام، والمسؤولية على أذونات
     * الخادم لا على تعميةٍ تُفقده فائدته.
     *
     * @return array<string, mixed>
     */
    private function payload(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'code' => $method->code,
            'name' => $method->name,
            'name_en' => $method->name_en,
            'type' => $method->type,
            'mode' => $method->gateway_mode ?: 'test',
            'is_active' => (bool) $method->is_active,
            'is_online' => (bool) $method->is_online,
            'credentials' => [
                'public_key' => $method->public_key,
                'secret_key' => $method->secret_key,
            ],
            'webhook' => [
                'url' => $method->webhook_url,
                'secret' => $method->webhook_secret,
            ],
            'limits' => [
                'min_amount' => $method->min_amount !== null ? (float) $method->min_amount : null,
                'max_amount' => $method->max_amount !== null ? (float) $method->max_amount : null,
            ],
            'fee' => [
                'type' => $method->fee_type,
                'amount' => $method->fee_amount !== null ? (float) $method->fee_amount : null,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
