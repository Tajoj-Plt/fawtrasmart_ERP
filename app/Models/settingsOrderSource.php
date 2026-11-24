<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * إعدادات مصادر الطلب — مفتاح وقيمة.
 *
 * كان هذا النموذج معطَّلاً بثلاث علل مجتمعة:
 *
 *  1. `$table` كان `'settings _orderSources'` بمسافة داخل الاسم، والجدول
 *     في الترحيل اسمه `settings_order_sources` — فأي استعلام يسقط.
 *  2. المتحكّم ينادي `settingsOrderSource::get('key')`، و`get` على نموذج
 *     Eloquent هي `Builder::get($columns)` — تعيد مجموعة صفوف لا قيمةً،
 *     فكان «المصدر الافتراضي» مجموعةً تُقارَن بمعرّف فلا تطابق أبداً.
 *  3. `set()` لا وجود لها أصلاً، فحفظ الإعدادات يرمي BadMethodCallException.
 *
 * الدالّتان أدناه تحملان الاسمين اللذين ينتظرهما المتحكّم، وتغلبان
 * `Builder::get` لأنهما معرَّفتان على الصنف نفسه.
 */
class settingsOrderSource extends Model
{
    use HasFactory;

    protected $table = 'settings_order_sources';

    protected $fillable = ['setting_key', 'value'];

    /**
     * قيمة إعداد، أو `$default` إن لم يُحفَظ بعد.
     *
     * التوقيع يخالف `Model::get()` عمداً — لا استدعاء لها بمعنى الاستعلام
     * في هذا الصنف، ولا معنى لصفٍّ من جدول مفتاح/قيمة إلا قيمته.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->where('setting_key', $key)->value('value') ?? $default;
    }

    /** كتابة قيمة إعداد، إنشاءً أو تحديثاً. */
    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['setting_key' => $key],
            ['value' => (string) $value]
        );
    }
}
