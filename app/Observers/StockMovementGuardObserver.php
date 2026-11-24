<?php

namespace App\Observers;

use App\Support\StockEligibility;

/**
 * يمنع دخول صنفٍ بلا رصيد إلى جداول المخزون.
 *
 * يُسجَّل على `WarehousePermitsProducts` (بنود الأذون) و`ProductDetails`
 * (أرصدة المستودعات) — وهما البابان الوحيدان اللذان تدخل منهما الكمّيات.
 * الحارس على الموديل لا على الكنترولر عمداً: نقاط الكتابة خمس عشرة
 * موزَّعة، وحارسٌ في كلٍّ منها يُنسى في السادسة عشرة.
 *
 * يُفحص عند الإنشاء وعند تغيير `product_id` وحدهما — فتعديل كمّية سطرٍ
 * قائم لصنفٍ مخزني لا يدفع ثمن استعلامٍ لا حاجة إليه.
 */
class StockMovementGuardObserver
{
    public function creating($model): void
    {
        $this->guard($model);
    }

    public function updating($model): void
    {
        if ($model->isDirty('product_id')) {
            $this->guard($model);
        }
    }

    private function guard($model): void
    {
        if (! $model->product_id) {
            return;
        }

        StockEligibility::assert($model->product_id);
    }
}
