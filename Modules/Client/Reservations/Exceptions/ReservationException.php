<?php

namespace Modules\Client\Reservations\Exceptions;

use RuntimeException;

/**
 * خطأُ قاعدةِ عملٍ في الحجوزات — رسالتُه **معدّةٌ للعرض على المستخدم**.
 *
 * الفرقُ عن أي استثناءٍ آخر أنّ نصَّه عربيٌّ مفهوم («الموظّف مشغولٌ في
 * هذه الفترة») لا نصٌّ تقنيّ. فالمتحكّمُ يعرضه كما هو، بينما يبتلع بقيّةَ
 * الاستثناءات ويعرض بدلها مرجعاً في سجلّ الأخطاء.
 *
 * و`context` يحمل ما يلزم للشاشة زيادةً على النصّ — كالحجز المتعارض
 * ليُعرَض رابطُه بجانب رسالة المنع.
 */
class ReservationException extends RuntimeException
{
    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public static function because(string $message, array $context = []): self
    {
        $e = new self($message);
        $e->context = $context;

        return $e;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
