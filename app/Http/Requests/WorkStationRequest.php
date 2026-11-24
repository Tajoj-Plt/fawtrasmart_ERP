<?php

namespace App\Http\Requests;

use App\Models\WorkStations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkStationRequest extends FormRequest
{
    /** سقفُ أعمدة المبالغ `decimal(10,2)` */
    private const MAX_AMOUNT = 99999999.99;

    /** سقفُ `work_stations.total_cost` — `decimal(8,2)` */
    private const MAX_HOURLY_COST = 999999.99;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * تهيئةُ المدخلات قبل الفحص.
     *
     * صفوفُ المصروفات يبنيها السكربت، ويبقى منها صفٌّ فارغ متى لم يُملأ:
     * يُسقَط هنا الصفُّ الخالي من التكلفة والحساب معاً ثمّ يُعاد الترقيم،
     * فيطابق «المصروف رقم كذا» في رسالة الخطأ ترتيبَ الصفوف التي تُعاد
     * إلى الشاشة من `old()`.
     *
     * والوحدةُ ثابتة لا تُقرأ من الطلب.
     */
    protected function prepareForValidation(): void
    {
        $costs = array_values((array) $this->input('cost_expenses', []));
        $accounts = array_values((array) $this->input('account_expenses', []));

        $cleanCosts = [];
        $cleanAccounts = [];

        for ($i = 0, $rows = max(count($costs), count($accounts)); $i < $rows; $i++) {
            $cost = $costs[$i] ?? null;
            $account = $accounts[$i] ?? null;

            if (($cost === null || $cost === '') && ($account === null || $account === '')) {
                continue;
            }

            $cleanCosts[] = $cost;
            $cleanAccounts[] = $account;
        }

        $this->merge([
            'cost_expenses' => $cleanCosts,
            'account_expenses' => $cleanAccounts,
            'unit' => WorkStations::UNIT,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        /*
            الحساباتُ المسموحة هي قوائمُ الشاشة نفسُها — مصروفاتٌ طرفية
            للأجور والمصروفات، وأصولٌ ثابتة للأصل — مضافاً إليها عند التعديل
            ما حُفظ سابقاً على المحطّة. فمعرّفٌ يُحقن من أدوات المطوّر
            (حساب عميل أو بنك) يُردّ كما يُردّ في القائمة.
        */
        $station = $this->route('id')
            ? WorkStations::with('stationsCosts')->find($this->route('id'))
            : null;

        $expenseIds = WorkStations::expenseAccountOptions($station ? $station->savedExpenseAccountIds() : [])
            ->pluck('id')->all();
        $assetIds = WorkStations::assetAccountOptions($station ? [$station->origin] : [])
            ->pluck('id')->all();

        return [
            'name' => 'required|string|max:255',
            /*
                `code` لا يُرسَل أصلاً — حقلُه في الشاشة معطَّل للعرض — ولا
                يُقرأ إن أُرسل: يُصدره المتحكّم من إعدادات «الترقيم المتسلسل»
                تحت قفل. والقاعدةُ هنا تحدّ طولَ ما قد يُحقن وحسب.
            */
            'code' => 'nullable|string|max:50',
            'unit' => 'nullable|string|max:100',
            'description' => 'nullable|string',

            /*
                `numeric` قبل `min`: بلا `numeric` تقيس `min:0` **طولَ النصّ**
                لا قيمتَه، فكان «-50» يمرّ (طولُه ثلاثة) وتُحفظ تكلفةٌ سالبة.
            */
            'cost_expenses' => 'nullable|array',
            'cost_expenses.*' => 'required|numeric|min:0|max:'.self::MAX_AMOUNT,

            'account_expenses' => 'nullable|array',
            'account_expenses.*' => ['required', Rule::in($expenseIds)],

            'cost_wages' => 'nullable|numeric|min:0|max:'.self::MAX_AMOUNT,
            'account_wages' => ['nullable', Rule::in($expenseIds)],

            'cost_origin' => 'nullable|numeric|min:0|max:'.self::MAX_AMOUNT,
            'origin' => ['nullable', Rule::in($assetIds)],
            'asset_life_hours' => 'nullable|numeric|min:0|max:9999999999.99',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // القيمُ غير الرقمية أُبلغ عنها في القواعد — هنا تُحسب صفراً
            $number = fn ($value) => is_numeric($value) ? (float) $value : 0.0;

            $expenses = array_sum(array_map($number, (array) $this->input('cost_expenses', [])));
            $wages = $number($this->input('cost_wages'));
            $origin = $number($this->input('cost_origin'));
            $life = $number($this->input('asset_life_hours'));

            if ($expenses <= 0 && $wages <= 0 && $origin <= 0) {
                $validator->errors()->add('costs', 'يجب عليك إدخال تكلفة واحدة على الأقل من: الأجور، الأصول أو المصروفات الأخرى.');
            }

            /*
                قيمةُ أصلٍ بلا عمر لا إهلاكَ لها: تُحفظ ولا تدخل تكلفةَ
                الساعة، فيظنّ المستخدم أنّ الآلة محسوبة وهي ليست كذلك.
            */
            $depreciates = $this->boolean('automatic_depreciation');

            if ($depreciates && $origin <= 0 && ! $validator->errors()->has('cost_origin')) {
                $validator->errors()->add('cost_origin', 'الإهلاك التلقائي مفعَّل — أدخل قيمة الأصل، أو عطّل الإهلاك.');
            }

            if ($depreciates && $origin > 0 && $life <= 0 && ! $validator->errors()->has('asset_life_hours')) {
                $validator->errors()->add('asset_life_hours', 'الإهلاك التلقائي مفعَّل — أدخل عمر الأصل بالساعات (أكبر من صفر)، فعليه يُحسب إهلاك الساعة.');
            }

            if (WorkStations::hourlyCostFor($expenses, $wages, $origin, $depreciates ? $life : 0) > self::MAX_HOURLY_COST) {
                $validator->errors()->add('costs', 'تكلفة الساعة الناتجة تتجاوز الحدّ المسموح ('.number_format(self::MAX_HOURLY_COST, 2).') — راجع المبالغ أو عمر الأصل.');
            }
        });
    }

    public function messages()
    {
        return [
            'name.required' => 'يجب إدخال اسم محطة العمل.',
            'name.string' => 'يجب أن يكون الاسم نصاً.',
            'name.max' => 'يجب ألا يزيد الاسم عن 255 حرفاً.',

            'cost_expenses.*.required' => 'المصروف رقم :position: أدخل التكلفة أو احذف الصف.',
            'cost_expenses.*.numeric' => 'المصروف رقم :position: التكلفة يجب أن تكون رقماً.',
            'cost_expenses.*.min' => 'المصروف رقم :position: التكلفة لا تكون سالبة.',
            'cost_expenses.*.max' => 'المصروف رقم :position: التكلفة أكبر من الحدّ المسموح.',

            'account_expenses.*.required' => 'المصروف رقم :position: اختر حساب المصروف.',
            'account_expenses.*.in' => 'المصروف رقم :position: الحساب المختار ليس من حسابات المصروفات.',

            'cost_wages.numeric' => 'تكلفة الأجور يجب أن تكون رقماً.',
            'cost_wages.min' => 'تكلفة الأجور لا تكون سالبة.',
            'cost_wages.max' => 'تكلفة الأجور أكبر من الحدّ المسموح.',
            'account_wages.in' => 'حساب الأجور المختار ليس من حسابات المصروفات.',

            'cost_origin.numeric' => 'قيمة الأصل يجب أن تكون رقماً.',
            'cost_origin.min' => 'قيمة الأصل لا تكون سالبة.',
            'cost_origin.max' => 'قيمة الأصل أكبر من الحدّ المسموح.',
            'origin.in' => 'حساب الأصل المختار ليس من حسابات الأصول الثابتة.',

            'asset_life_hours.numeric' => 'عمر الأصل بالساعات يجب أن يكون رقماً.',
            'asset_life_hours.min' => 'عمر الأصل بالساعات لا يكون سالباً.',
            'asset_life_hours.max' => 'عمر الأصل بالساعات أكبر من الحدّ المسموح.',
        ];
    }
}
