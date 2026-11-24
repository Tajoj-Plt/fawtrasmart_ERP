<?php

namespace App\Services\Zatca;

use RuntimeException;

/**
 * خطأٌ رسالتُه تُعرَض للمستخدم كما هي.
 *
 * ما يُرمى بهذا الصنف سببٌ يُصلحه المستخدم (بياناتٌ ناقصة، مستندٌ لا
 * يُرسَل، شهادةٌ منتهية) — لا عطلٌ في الشيفرة. فالشاشة تعرض نصّه، وغيرُه
 * من الأخطاء يُسجَّل ويُعرَض بدله نصٌّ عامّ.
 */
class ZatcaException extends RuntimeException
{
    /** @var array<int, string> */
    protected array $details = [];

    /** @param  array<int, string>  $details */
    public static function withDetails(string $message, array $details): self
    {
        $exception = new self($message);
        $exception->details = array_values(array_filter($details));

        return $exception;
    }

    /** @return array<int, string> */
    public function details(): array
    {
        return $this->details;
    }
}
