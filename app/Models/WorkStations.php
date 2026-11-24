<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentCurrency;
use App\Support\AccountingSettings;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class WorkStations extends Model
{
    /*
        المحطّة مستندٌ بمبالغ: `total_cost` و`cost_wages` و`cost_origin`.
        كانت بلا عمود عملة — فتُطبع تكلفتُها بالعملة الافتراضية **الحالية**
        مهما تبدّلت بعد إنشائها، ويصير رقمُ محطّةٍ سُجِّلت بالريال معروضاً
        بالدولار بمجرّد تبديل الافتراضية في شاشة «العملات».
    */
    use BelongsToBranch, HasDocumentCurrency;

    /** الوحدةُ ثابتة: كلُّ حسابات التصنيع تضرب تكلفةَ المحطّة في ساعات التشغيل. */
    public const UNIT = 'ساعة';

    protected $table = 'work_stations';

    protected $fillable = [
        'name',
        'code',
        'description',
        'total_cost',
        'unit',
        'cost_wages', 'account_wages',
        'cost_origin', 'origin',
        // عمرُ الأصل بالساعات — مقامُ إهلاك الساعة، انظر hourlyDepreciation()
        'asset_life_hours',
        'automatic_depreciation',
        'created_by',
        'updated_by',
        'currency',
        // الفرع — `null` تعني «كل الفروع»، انظر BelongsToBranch::scopeForBranch
        'branch_id',
    ];

    public function stationsCosts()
    {
        return $this->hasMany(WorkStationsCost::class, 'work_station_id');
    }

    public function accountWages()
    {
        return $this->belongsTo(Account::class, 'account_wages');
    }

    public function accountOrigin()
    {
        return $this->belongsTo(Account::class, 'origin');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * إهلاكُ الساعة = قيمة الأصل ÷ عمره بالساعات.
     *
     * كانت قيمةُ الأصل كلُّها تُجمع على تكلفة الساعة: آلةٌ بمئة ألف تجعل
     * ساعةَ التشغيل بمئة ألف. والأصلُ يُستهلك على عمره لا في ساعةٍ واحدة.
     * بلا عمرٍ (فارغ أو صفر) لا إهلاك — صفر.
     */
    public static function hourlyDepreciationFor($costOrigin, $lifeHours): float
    {
        $cost = (float) $costOrigin;
        $life = (float) $lifeHours;

        return ($cost > 0 && $life > 0) ? $cost / $life : 0.0;
    }

    /**
     * تكلفةُ الساعة = مجموع المصروفات + الأجور + إهلاك الساعة.
     *
     * الصيغةُ في موضعٍ واحد: يقرؤها المتحكّم عند الحفظ وطلبُ التحقّق عند
     * فحص الحدّ، وعليها تُبنى شاشةُ الإدخال.
     */
    public static function hourlyCostFor($expensesTotal, $wages, $costOrigin, $lifeHours): float
    {
        return round(
            (float) $expensesTotal + (float) $wages + static::hourlyDepreciationFor($costOrigin, $lifeHours),
            2
        );
    }

    public function hourlyDepreciation(): float
    {
        // «الإهلاك التلقائي» معطَّلاً ⇒ الأصلُ للعلم ولا إهلاكَ في تكلفة الساعة
        if (! $this->automatic_depreciation) {
            return 0.0;
        }

        return round(static::hourlyDepreciationFor($this->cost_origin, $this->asset_life_hours), 2);
    }

    /**
     * حساباتُ المصروفات والأجور: حساباتُ المصروفات الطرفية وحدها.
     *
     * كانت القائمةُ دليلَ الحسابات كلَّه — عملاء وبنوك وخزائن — فيُختار
     * لأجور المحطّة حسابُ عميل. والحسابُ الأب يُستثنى: القيدُ يُرحَّل على
     * الطرفيّ لا على المجموعة.
     *
     * @param  array<int|string|null>  $keep  حساباتٌ محفوظة تبقى ظاهرةً ولو خرجت عن الفلتر (تعديل)
     */
    public static function expenseAccountOptions(array $keep = []): Collection
    {
        $accounts = Account::query()
            ->from('accounts as a')
            ->where('a.account_type', 'expenses')
            ->where('a.is_active', 1)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('accounts as c')->whereColumn('c.parent_id', 'a.id');
            })
            ->orderBy('a.code')
            ->get(['a.id', 'a.name', 'a.code']);

        return static::withKeptAccounts($accounts, $keep);
    }

    /**
     * حساباتُ الأصل: الأصول الثابتة الطرفية وحدها.
     *
     * `account_type = assets` يضمّ العملاء والخزائن والبنوك والمستودعات
     * (قرابة أربعة آلاف حساب)، فلا يكفي فلتراً. والمعتمَد شجرةُ «الأصول
     * الثابتة» كما رُبطت في «الحسابات المحاسبية» (`fixed_assets`): كلُّ
     * طرفيٍّ تحتها عدا مجمّعَ الإهلاك وما تحته — ذاك حسابٌ دائن مقابلٌ
     * للأصل لا أصلٌ يُشترى.
     *
     * وإن لم يُربط الجذر: الأصولُ الطرفية التي ليست عميلاً ولا مورّداً ولا
     * مستودعاً ولا خزينة — أوسعُ من المطلوب لكنّها لا تُفرغ القائمة.
     *
     * @param  array<int|string|null>  $keep  حساباتٌ محفوظة تبقى ظاهرةً ولو خرجت عن الفلتر (تعديل)
     */
    public static function assetAccountOptions(array $keep = []): Collection
    {
        $candidates = Account::query()
            ->where('account_type', 'assets')
            ->whereNull('client_id')
            ->whereNull('supplier_id')
            ->whereNull('storehouse_id')
            ->where(function ($q) {
                $q->whereNull('is_store_treasury')->orWhere('is_store_treasury', 0);
            })
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'parent_id', 'balance_type', 'type_accont', 'is_active']);

        $childrenOf = $candidates->groupBy('parent_id');
        $rootId = AccountingSettings::id('fixed_assets');
        $accumulatedId = AccountingSettings::id('accumulated_depreciation');

        $accounts = collect();

        if ($rootId && $candidates->firstWhere('id', $rootId)) {
            $queue = [$rootId];

            while ($queue) {
                $parentId = array_shift($queue);

                foreach ($childrenOf->get($parentId, []) as $child) {
                    // مجمّع الإهلاك وما تحته — بالربط إن وُجد، وبطبيعته الدائنة إن لم يُربط
                    if ($child->id == $accumulatedId || $child->balance_type === 'credit') {
                        continue;
                    }

                    if ($childrenOf->has($child->id)) {
                        $queue[] = $child->id;
                    } elseif ($child->is_active) {
                        $accounts->push($child);
                    }
                }
            }
        } else {
            $accounts = $candidates->filter(fn ($a) => $a->is_active
                && $a->type_accont === null
                && $a->balance_type !== 'credit'
                && ! $childrenOf->has($a->id));
        }

        return static::withKeptAccounts($accounts->sortBy('code')->values(), $keep);
    }

    /**
     * يُلحق بالقائمة ما حُفظ سابقاً وخرج عن الفلتر — كي لا يُمحى اختيارٌ
     * قديم بمجرّد فتح شاشة التعديل وحفظها.
     */
    private static function withKeptAccounts(Collection $accounts, array $keep): Collection
    {
        $missing = collect($keep)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->diff($accounts->pluck('id'));

        if ($missing->isNotEmpty()) {
            $accounts = $accounts->concat(
                Account::whereIn('id', $missing->all())->get(['id', 'name', 'code'])
            );
        }

        return $accounts->values();
    }

    /** الحساباتُ المحفوظة على جهة المصروفات: حسابُ الأجور وحساباتُ صفوف المصروفات. */
    public function savedExpenseAccountIds(): array
    {
        return $this->stationsCosts->pluck('account_expenses')
            ->push($this->account_wages)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
