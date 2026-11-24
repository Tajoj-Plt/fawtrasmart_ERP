<?php

namespace App\Models;

use App\Support\HrSettings;
use Illuminate\Database\Eloquent\Model;

/**
 * صفُّ مجموعةِ إعداداتٍ في الموارد البشرية — نظيرُ `GeneralClientSetting`.
 *
 * لا أعمدةَ إعدادٍ هنا: المفاتيحُ كلُّها في حقيبة `options`. والتعريفُ
 * والافتراضاتُ في `App\Support\HrSettings` وحده.
 *
 * @see HrSettings
 */
class HrSetting extends Model
{
    protected $fillable = ['key', 'name', 'options'];

    protected $casts = ['options' => 'array'];

    /** قيمةٌ من الحقيبة بمفتاحها، مع افتراضٍ عند الغياب. */
    public function option(string $key, mixed $default = null): mixed
    {
        $options = $this->options;

        if (! is_array($options) || ! array_key_exists($key, $options)) {
            return $default;
        }

        return $options[$key];
    }

    /**
     * دمجُ قيمٍ في الحقيبة دون مسحِ ما لم يُذكر — فحفظُ تبويبٍ واحدٍ لا
     * يمسح مفاتيحَ تبويبٍ آخرَ في المجموعة نفسها.
     *
     * @param  array<string, mixed>  $values
     */
    public function mergeOptions(array $values): self
    {
        $this->options = array_merge(is_array($this->options) ? $this->options : [], $values);

        return $this;
    }

    /** الصفُّ بمفتاحه، أو إنشاؤه بالافتراضات إن لم يوجد. */
    public static function getOrCreateByKey(string $key, string $name, array $defaults): self
    {
        return static::firstOrCreate(['key' => $key], ['name' => $name, 'options' => $defaults]);
    }
}
