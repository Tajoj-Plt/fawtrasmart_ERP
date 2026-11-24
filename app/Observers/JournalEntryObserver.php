<?php

namespace App\Observers;

use App\Enums\ReferenceType;
use App\Models\JournalEntry;
use App\Models\WarehousePermits;

/**
 * إذنٌ مخزني رُحِّل قيده هو إذنٌ نافذ.
 *
 * أغلب وحدات النظام (المبيعات والمشتريات ونقطة البيع والتصنيع والمتجر)
 * تُنشئ إذن المخزن وقيده معاً ولا تمرّ بشاشة الاعتماد أصلاً. وبما أنّ
 * الإذن الجديد صار «معلَّقاً» افتراضياً، كانت تلك الأذون تبقى معلَّقة
 * بقيدٍ مُرحَّل — واعتمادها لاحقاً من الشاشة يُرحّل القيد مرّةً ثانية.
 *
 * لذلك: متى رُحِّل قيدٌ مرتبطٌ بإذنٍ معلَّق، اعتُبر الإذن معتمَداً.
 * ويقتصر الأمر على أنواع المراجع المخزنية وحدها كي لا يُعتمد إذنٌ لأنّ
 * `reference_id` صادف رقم فاتورة.
 */
class JournalEntryObserver
{
    private const WAREHOUSE_REFERENCES = [
        ReferenceType::APPROVED_WAREHOUSE,
        ReferenceType::WAREHOUSE_PERMIT,
        ReferenceType::OPENING_BALANCE,
    ];

    public function created(JournalEntry $entry): void
    {
        if (! $entry->reference_id) {
            return;
        }

        $reference = $entry->reference_type instanceof ReferenceType
            ? $entry->reference_type
            : ReferenceType::tryFrom((string) $entry->reference_type);

        if (! $reference || ! in_array($reference, self::WAREHOUSE_REFERENCES, true)) {
            return;
        }

        // القيد العكسي لا يعتمد شيئاً — بل يأتي بعد إلغاء الاعتماد
        if (str_starts_with((string) $entry->reference_number, 'REVERSE_')) {
            return;
        }

        WarehousePermits::where('id', $entry->reference_id)
            ->where('status', 'pending')
            ->update(['status' => 'apporod']);
    }
}
