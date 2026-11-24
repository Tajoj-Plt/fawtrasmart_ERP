<?php

namespace Modules\Pos\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * تنبيهُ تيليجرام — خارج مسار البيع، وبعد نجاحه.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 *     Http::timeout(60)->post($telegramApiUrl, [...]);
 *
 * استدعاءُ شبكةٍ **متزامن**، بمهلة ستّين ثانية، **داخل معاملة قاعدة بيانات
 * مفتوحة** أثناء البيع. فإذا تباطأت تيليجرام أو تعذّر الوصول إليها:
 *
 *   • المعاملةُ تبقى مفتوحة ستّين ثانية، والصفوفُ مقفولة طوال ذلك.
 *   • الكاشيرُ واقفٌ أمام العميل ينتظر شاشةً لا تستجيب.
 *   • وأجهزةُ الكاشير الأخرى تنتظر القفلَ نفسَه.
 *
 * وكان في المسار **استدعاءان** لا واحد: واحدٌ للفاتورة وآخر لكلّ تنبيه
 * مخزونٍ منخفض — أي أن فاتورةً بخمسة أصناف منخفضة قد تُبقي المعاملة
 * مفتوحةً ستَّ دقائق.
 *
 * ═════════════════════ ما صار ═════════════════════
 *
 * طابورٌ يُرسَل **بعد** `commit`. فشلُ التنبيه لا يمسّ البيع، وبطؤه لا
 * يُبقي قفلاً. والتوكن من البيئة لا من الشيفرة.
 */
class SendPosTelegramAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** محاولتان تكفيان: تنبيهٌ متأخّرٌ ساعةً لا قيمة له. */
    public $tries = 2;

    public $backoff = 30;

    public function __construct(private string $message) {}

    public function handle(): void
    {
        $config = config('pos.telegram');

        // قناةٌ لم تُضبَط لا تُرسِل — لا إلى قناةٍ افتراضيةٍ تخصّ غيرها
        if (! ($config['enabled'] ?? false) || empty($config['token']) || empty($config['chat_id'])) {
            return;
        }

        try {
            $response = Http::timeout($config['timeout'] ?? 10)
                ->post("https://api.telegram.org/bot{$config['token']}/sendMessage", [
                    'chat_id' => $config['chat_id'],
                    'text' => $this->message,
                    'parse_mode' => 'Markdown',
                ]);

            if ($response->failed()) {
                Log::warning('تيليجرام ردّ بخطأ على تنبيه نقطة البيع', [
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('تعذّر إرسال تنبيه تيليجرام لنقطة البيع', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
