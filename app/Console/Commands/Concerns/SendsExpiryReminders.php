<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * قواعدُ التذكير بما يوشك أن ينتهي — عقداً كان أو وثيقة.
 *
 * **مسألتان تتكرّران في كل مهمّة تنبيهٍ دورية، وكلتاهما تُخطَأ بالطريقة
 * نفسِها:**
 *
 * ─────────────────── ١. مدًى لا يومٌ بعينه ───────────────────
 *
 * الصيغةُ المغرية `whereDate('end_date', now()->addDays($notice))` تبدو
 * دقيقة، وفيها عطلٌ صامت: إن فات `schedule:run` يوماً واحداً — انقطاعُ
 * خادم، أو كرونٌ لم يُضبط بعد — **سقط التنبيهُ ولا يُرسَل أبداً**، لأن
 * اليومَ الموافق مضى ولن يعود.
 *
 * فالشرطُ مدًى: كلُّ ما دخل النافذة يُنبَّه عليه، فاتت المهمّةُ يوماً أو
 * أياماً.
 *
 * ─────────────────── ٢. ومدًى يقتضي كبحاً ───────────────────
 *
 * فمهمّةٌ يومية على مدًى ثلاثينيّ تُنبّه ثلاثين مرّة على الشيء نفسِه.
 * وأوّلُ ما يُفعل بتنبيهٍ يتكرّر كلَّ يوم أن يُتجاهَل — فتضيع معه
 * التنبيهاتُ التي تستحقّ.
 *
 * فيُختم كلُّ سجلٍّ بـ`last_alert_sent_at`، ولا يُذكَّر به قبل انقضاء
 * أسبوع. **والختمُ بعد الإرسال لا قبله**: لو خُتم أوّلاً ثم انكسر
 * الإرسالُ لبقي الختمُ يمنع المحاولةَ أسبوعاً كاملاً.
 */
trait SendsExpiryReminders
{
    /** لا يُعاد التذكير بالسجلّ نفسِه قبل انقضاء هذه المدّة. */
    protected int $reminderIntervalDays = 7;

    /**
     * أحان تذكيرٌ جديد بهذا السجلّ؟
     *
     * يقرأ `last_alert_sent_at` — وسجلٌّ لم يُنبَّه عليه قطّ يستحقّ أوّلَ
     * تنبيه.
     */
    protected function isReminderDue(Model $record): bool
    {
        $last = $record->getAttribute('last_alert_sent_at');

        if (! $last) {
            return true;
        }

        return $last->lte(now()->subDays($this->reminderIntervalDays));
    }

    /** ختمُ التذكير — يُنادى **بعد** نجاح الإرسال. */
    protected function stampReminder(Model $record): void
    {
        $record->forceFill(['last_alert_sent_at' => now()])->save();
    }

    /**
     * الأيامُ الصحيحة حتى تاريخٍ — سالبةٌ لما مضى.
     *
     * **ولماذا `startOfDay` على الطرفين؟** لأن `now()->diffInDays($date)`
     * تُرجع كسراً يحمل فرقَ الساعات: `30.000000000069445`. فتُطبع في
     * الرسالة «بعد 30.000000000069 يوم» — وهو ما كانت تفعله
     * `CheckContractExpirations` حرفياً.
     */
    protected function wholeDaysUntil($date): ?int
    {
        if (! $date) {
            return null;
        }

        $target = $date instanceof \DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);

        return (int) now()->startOfDay()->diffInDays($target->copy()->startOfDay(), false);
    }

    /** وصفٌ عربيّ للمدّة الباقية — «تنتهي خلال…» أو «انتهت منذ…». */
    protected function describeRemaining(?int $days): string
    {
        if ($days === null) {
            return 'بلا تاريخ انتهاء';
        }

        return $days < 0
            ? 'انتهت منذ '.abs($days).' يوماً'
            : 'تنتهي خلال '.$days.' يوماً';
    }
}
