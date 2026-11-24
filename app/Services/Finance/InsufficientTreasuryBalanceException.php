<?php

namespace App\Services\Finance;

use App\Models\Account;

/**
 * رصيدُ الخزينة لا يكفي للصرف — يرميه `TreasuryBalanceGuard`.
 *
 * صنفٌ مستقلٌّ عن `RuntimeException` العامة كي يُميَّز الرفضُ المالي عن
 * الأعطال في المتحكّمات والتقارير، وتبقى الأرقامُ متاحةً لمن يعرضها.
 */
class InsufficientTreasuryBalanceException extends \RuntimeException
{
    public function __construct(
        public readonly Account $treasury,
        public readonly float $balance,
        public readonly float $required,
        public readonly string $purpose = 'الصرف',
    ) {
        parent::__construct(sprintf(
            'رصيد الخزينة «%s» هو (%s) ولا يكفي لـ%s (%s). لا يُسمح بأن يصير رصيد الخزينة سالباً — غذِّ الخزينة أو اختر خزينةً أخرى.',
            $treasury->name,
            number_format($balance, 2),
            $purpose,
            number_format($required, 2)
        ));
    }
}
