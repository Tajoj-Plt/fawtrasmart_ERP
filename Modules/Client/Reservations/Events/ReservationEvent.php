<?php

namespace Modules\Client\Reservations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Client\Reservations\Models\Reservation;

/**
 * أصلُ أحداث الحجوزات — كلُّها تحمل الحجزَ ولا تحمل غيره.
 *
 * **لماذا حدثٌ لكل حالةٍ لا حدثٌ واحدٌ بمعامل؟** لأنّ المستمعَ يُسجَّل
 * بالنوع: مستمعُ الإلغاء يُربط بـ`ReservationCancelled` ولا يوقظه شيءٌ
 * آخر. وبحدثٍ واحدٍ عامٍّ يستيقظ كلُّ مستمعٍ على كلّ انتقالٍ ثمّ يفحص
 * بـ`if` — فيصير جدولُ التوجيه موزَّعاً على المستمعين بدل أن يكون في
 * `EventServiceProvider` حيث يُقرأ في موضعٍ واحد.
 *
 * ولا تُطلَق إلّا **بعد** نجاح الحفظ: المستمعون يرسلون رسائلَ لا تُلغى
 * بـrollback.
 */
abstract class ReservationEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Reservation $reservation) {}

    /** مفتاحُ الحدث كما يُكتب في سجلّ الأحداث. */
    public function key(): string
    {
        return 'reservation.'.strtolower(preg_replace(
            '/(?<!^)[A-Z]/', '_$0',
            str_replace('Reservation', '', class_basename(static::class))
        ));
    }
}
