<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'description',
        'group',
        'type',
        'options',
        'default_value',
        'is_inheritable',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'is_inheritable' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_setting_branch')
            ->withPivot(['status', 'value'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** الترتيب المعتمد في شاشة الإعدادات: حسب التصنيف ثم ترتيب الكتالوج. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('group')->orderBy('sort_order')->orderBy('name');
    }

    public function isBoolean(): bool
    {
        return ($this->type ?? 'boolean') === 'boolean';
    }

    /*
     * ══════════════════ الترجمة ══════════════════
     *
     * اسم الإعداد ووصفه مخزَّنان في قاعدة البيانات (يكتبهما `sync`)، لكن
     * الترجمة تُحلّ عند القراءة لا عند الكتابة. لو استُعملت `__()` داخل
     * الكتالوج لتجمّدت لغةُ لحظة المزامنة في الجدول، فيرى كل المستخدمين
     * اللغة التي صادف أن شُغّل بها الأمر.
     *
     * القيمة في الجدول تبقى احتياطاً: أي مفتاح ليس له سطر ترجمة — مفتاح
     * قديم أضافه تركيب سابق مثلاً — يظهر باسمه المخزَّن كما هو.
     */

    /** نصّ مترجم للمفتاح، أو `null` إن لم يكن معرَّفاً. */
    protected function translate(string $path): ?string
    {
        $key = 'branches.' . $path;
        $value = __($key);

        return (is_string($value) && $value !== $key) ? $value : null;
    }

    public function getNameAttribute($value): ?string
    {
        return $this->translate('setting_items.' . $this->attributes['key'] . '.name') ?? $value;
    }

    public function getDescriptionAttribute($value): ?string
    {
        return $this->translate('setting_items.' . $this->attributes['key'] . '.desc') ?? $value;
    }

    /** خيارات قائمة `select` بصيغة [القيمة => العنوان]. */
    public function choices(): array
    {
        $choices = (array) ($this->options['choices'] ?? []);

        foreach ($choices as $value => $label) {
            $choices[$value] = $this->translate('setting_choices.' . $this->key . '.' . $value) ?? $label;
        }

        return $choices;
    }

    /** لاحقة الوحدة أو نصّ الإرشاد داخل الحقل — مترجمَين. */
    public function option(string $name)
    {
        $value = $this->options[$name] ?? null;

        if (! is_string($value) || $value === '') {
            return $value;
        }

        return $this->translate('setting_extras.' . $this->key . '.' . $name) ?? $value;
    }

    /**
     * تحويل القيمة المخزّنة نصّاً إلى نوعها الحقيقي.
     */
    public function castValue($raw)
    {
        if ($this->isBoolean()) {
            return (bool) $raw;
        }

        if ($raw === null || $raw === '') {
            return $this->type === 'number' ? null : '';
        }

        if ($this->type === 'number') {
            return is_numeric($raw) ? $raw + 0 : null;
        }

        return (string) $raw;
    }

    /** القيمة الافتراضية بنوعها الصحيح. */
    public function defaultValue()
    {
        return $this->castValue($this->default_value);
    }

    /**
     * صياغة القيمة للعرض على المستخدم — لا يُعرض «1» لإعداد بولياني
     * ولا مفتاح `fri_sat` لقائمة اختيار لها عنوان عربي.
     */
    public function display($value): string
    {
        if ($this->isBoolean()) {
            return $value ? __('branches.common.enabled') : __('branches.common.disabled');
        }

        if ($value === null || $value === '') {
            return '—';
        }

        if ($this->type === 'select') {
            return (string) ($this->choices()[$value] ?? $value);
        }

        $suffix = $this->option('suffix');

        return trim($value . ($suffix ? ' ' . $suffix : ''));
    }
}
