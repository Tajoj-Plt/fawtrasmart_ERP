<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class SalaryItem extends Model
{
    protected $fillable = ['name', 'type', 'is_insurable', 'in_eos_wage', 'status', 'description', 'salary_item_value', 'amount', 'calculation_formula', 'condition', 'reference_value', 'account_id', 'salary_template_id', 'salary_slips_id', 'contracts_id', 'component_type_id', 'origin', 'reward_redemption_id'];

    protected $casts = [
        'type' => 'integer',
        'status' => 'integer',
        'salary_item_value' => 'integer',
        'amount' => 'decimal:2',
        'is_insurable' => 'boolean',
        'in_eos_wage' => 'boolean',
        'reference_value' => 'boolean',
    ];

    /** @var array<string, bool> ذاكرةُ فحص الأعمدة للطلب الواحد */
    private static array $columnCache = [];

    /*
        وراثةُ علمَي البند إلى نُسَخه.

        بندُ الراتب يُعرَّف مرّةً في «بنود الرواتب» ثمّ يُنسخ صفّاً جديداً في
        كلّ عقدٍ وقالبٍ وقسيمة (`SalaryItem::create([... 'name' => $orig->name ...])`
        في `ContractsController` و`SalarySlipController`، وكان في `SalaryTemplatesController`
        قبل إلغاء «قوالب الرواتب» في 2026-09-24).
        والنسخُ هناك لا يحمل `is_insurable` ولا `in_eos_wage` ولا
        `component_type_id` — فكان تصنيفُ البند لا يبلغ القسيمة أبداً،
        والأجرُ الخاضعُ للتأمينات = الأساسي وحده في كلّ قسيمةٍ حقيقية.

        فالوراثةُ هنا، في مكانٍ واحد، لا في كلّ موضعِ نسخ: نسخةٌ لم يُصرَّح
        لها بالعلَمَين تأخذهما من التعريف الذي يحمل اسمَها واتجاهَها.
    */
    protected static function booted(): void
    {
        static::creating(function (SalaryItem $item) {
            if (! $item->isLine() || $item->origin) {
                return;
            }

            $attributes = $item->getAttributes();
            $needsInsurable = ! array_key_exists('is_insurable', $attributes) && self::hasColumn('is_insurable');
            $needsEos = ! array_key_exists('in_eos_wage', $attributes) && self::hasColumn('in_eos_wage');
            $needsType = empty($attributes['component_type_id']) && self::hasColumn('component_type_id');

            if (! $needsInsurable && ! $needsEos && ! $needsType) {
                return;
            }

            $definition = static::query()
                ->catalog()
                ->where('name', $item->name)
                ->when($item->type, fn (Builder $q) => $q->where('type', $item->type))
                ->orderBy('id')
                ->first();

            if (! $definition) {
                return;
            }

            if ($needsInsurable) {
                $item->is_insurable = (bool) $definition->is_insurable;
            }
            if ($needsEos) {
                $item->in_eos_wage = (bool) $definition->in_eos_wage;
            }
            if ($needsType && $definition->component_type_id) {
                $item->component_type_id = $definition->component_type_id;
            }
        });
    }

    private static function hasColumn(string $column): bool
    {
        return self::$columnCache[$column] ??= Schema::hasColumn('salary_items', $column);
    }

    /** نسخةٌ داخل عقدٍ أو قالبٍ أو قسيمة — لا تعريفُ بند. */
    public function isLine(): bool
    {
        return ! empty($this->salary_slips_id)
            || ! empty($this->contracts_id)
            || ! empty($this->salary_template_id);
    }

    /**
     * تعريفاتُ البنود — ما تعرضه شاشة «بنود الرواتب» ويُختار في العقد والقسيمة.
     *
     * جدولُ `salary_items` يجمع التعريفَ ونُسَخَه معاً: النسخةُ تحمل قسيمةً
     * أو عقداً أو قالباً، والمولَّدُ آلياً (أجر إضافي، تأمينات) يحمل `origin`.
     * والشاشةُ كانت تعرضها كلَّها فيتكرّر «راتب أساسي» بعدد العقود، ويُحذف
     * من القائمة بندٌ داخل قسيمةٍ مصروفة.
     */
    public function scopeCatalog(Builder $query): Builder
    {
        return $query->whereNull('salary_slips_id')
            ->whereNull('contracts_id')
            ->whereNull('salary_template_id')
            ->whereNull('origin');
    }

    /**
     * ما يدخل الأجرَ الخاضعَ للتأمينات — علمُ البند نفسِه هو المرجع.
     *
     * ترحيلُ `2026_09_24_100000` نقل علمَ الكتالوج إلى كلّ صفٍّ مصنَّف،
     * فلا يُسأل الكتالوجُ بعده إلا على قاعدةٍ لم يُشغَّل فيها الترحيل بعد.
     * ولا يُجمع الاثنان بـ«أو»: بندٌ أُزيل عنه العلمُ من شاشته يجب أن يخرج
     * من الأجر الخاضع ولو بقي نوعُه القديم «خاضعاً».
     */
    public function scopeInsurable(Builder $query): Builder
    {
        if (self::hasColumn('is_insurable')) {
            return $query->where('is_insurable', true);
        }

        return $query->whereHas('componentType', fn (Builder $t) => $t->where('is_insurable', true));
    }

    /** ما يدخل أجرَ احتساب مكافأة نهاية الخدمة — بالمنطق نفسه. */
    public function scopeInEosWage(Builder $query): Builder
    {
        if (self::hasColumn('in_eos_wage')) {
            return $query->where('in_eos_wage', true);
        }

        return $query->whereHas('componentType', fn (Builder $t) => $t->where('is_default', true));
    }

    /*
        نوع البند — والقيمتان كانتا **معكوستين** هنا.

        كُتب في هذا الموديل `TYPE_DEDUCTION = 1` و`TYPE_ADDITION = 2`،
        ووافقه تعليقُ المهاجرة. ويقول التشغيلُ كلُّه عكسَ ذلك:

          • شاشة البند:  `<option value="1"> مستحق` و`value="2"> مستقطع`
          • `SalarySlip::additionItems()`  تُرشّح `type = 1`
          • `SalarySlip::deductionItems()` تُرشّح `type = 2`
          • `ContractsController` في أربعة عشر موضعاً، و
            `SalaryTemplatesController`، و`SalarySlipApiController`
            (وفيه `// مستحق` و`// مستقطع` صراحةً) — كلُّها على `1` استحقاقاً.

        ولم يظهر الخللُ لأن أحداً لم يستعمل الثابتين: البيانات في القاعدة
        على الاصطلاح الصحيح، والشيفرةُ تكتب `1` و`2` رقمين. فأولُ من كان
        سيثق بالاسم — لا بالرقم — كان سيقلب الاستحقاقات والخصميات في قسائم
        الرواتب كلِّها، ولا شيء يشي بالعطل إلا صافي راتبٍ خاطئ.

        فصُحّحا هنا، واستُعملا في `SalarySlip` كي لا يعودا اسمَين لا يقيسهما
        شيء.

        **قواعدُ قائمة**: عمودُ `type` فيها تعليقٌ قديم مقلوب — تعليقٌ لا
        أثر له في التشغيل، والقيمُ نفسُها صحيحة.
    */
    const TYPE_ADDITION = 1;

    const TYPE_DEDUCTION = 2;

    // Constants for status
    const STATUS_ACTIVE = 1;

    const STATUS_STOPPED = 2;

    const STATUS_NOT_ACTIVE = 3;

    // Constants for salary_item_value
    const VALUE_TYPE_AMOUNT = 1;

    const VALUE_TYPE_CALCULATION_FORMULA = 2;

    /** بندٌ مولَّد من دقائق الحضور الإضافية — لا يُدخل يدوياً. */
    public const ORIGIN_OVERTIME = 'overtime';

    /** استقطاع تأمينات مولَّد عند الصرف — حصة الموظف فقط. */
    public const ORIGIN_SOCIAL_INSURANCE = 'social_insurance';

    /**
     * استحقاقٌ لمرّةٍ واحدة من مكافأةٍ نقدية معتمدة في «كتالوج المكافآت» —
     * يولّده `EmployeeRewardFulfillment` ويحمل `reward_redemption_id` (فريد).
     * ليس بدلاً: لا يتكرّر، ولا يخضع للتأمينات، ولا يدخل أجرَ نهاية الخدمة.
     */
    public const ORIGIN_REWARD_REDEMPTION = 'reward_redemption';

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(SalaryComponentType::class, 'component_type_id');
    }

    /**
     * Get the chart of account that owns the salary item
     */
    public function Account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** «قوالب الرواتب» أُلغيت (2026-09-24) — العلاقةُ باقيةٌ لقراءة الصفوف القديمة فقط */
    public function salaryTemplate()
    {
        return $this->belongsTo(SalaryTemplate::class, 'salary_template_id');
    }

    public function salarySlips()
    {
        return $this->belongsTo(SalarySlip::class, 'salary_slips_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }
}
