<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * بانِي استعلامات [AccountSetting] — انظر `AccountSetting::newEloquentBuilder()`.
 *
 * `first()` على استعلامٍ مقيَّدٍ بمستخدمٍ لا صفَّ له تُعيد صفَّ المنشأة (أقدمَ
 * صفّ) بدل `null`. وبها تمرّ `value()` و`firstOrNew()` و`firstOrCreate()` —
 * فلا يُنشأ صفٌّ ثانٍ لمديرٍ آخر يفتح الشاشة، ولا تنقسم هويّةُ المنشأة بين
 * من حفظ ومن لم يحفظ.
 *
 * لا يمسّ استعلاماً بلا قيدِ مستخدم، ولا `get()` ولا `count()` ولا `exists()`.
 */
class AccountSettingBuilder extends Builder
{
    public function first($columns = ['*'])
    {
        $found = parent::first($columns);

        if ($found !== null || ! $this->filtersByUser()) {
            return $found;
        }

        // `get()` لا `first()`: كي لا يعود النداءُ إلى هذه الدالّة نفسِها
        return $this->model->newQuery()->orderBy('id')->limit(1)->get($columns)->first();
    }

    /** أفي الاستعلام شرطٌ على `user_id`؟ */
    protected function filtersByUser(?array $wheres = null): bool
    {
        foreach ($wheres ?? $this->getQuery()->wheres as $where) {
            $column = $where['column'] ?? null;

            if (is_string($column) && preg_match('/(^|\.)user_id$/', $column) === 1) {
                return true;
            }

            // `where(['user_id' => …])` — صيغةُ `firstOrNew` و`firstOrCreate` —
            // تُسجَّل شرطاً متداخلاً، فيُنظر في داخله
            if (($where['type'] ?? null) === 'Nested' && isset($where['query'])
                && $this->filtersByUser($where['query']->wheres)) {
                return true;
            }
        }

        return false;
    }
}
