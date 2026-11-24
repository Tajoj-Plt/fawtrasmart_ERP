<?php

namespace App\Support;

use App\Models\Account;
use App\Models\AccountingSetting;
use App\Models\Deduction;
use Illuminate\Support\Facades\DB;

/**
 * حساباتُ أنواع الخصميات — حسابٌ فرعيٌّ لكلِّ نوعٍ تحت «خصميات الموظفين».
 *
 * **لماذا؟**
 *
 * مفاتيحُ `payroll_deduction_{type}` زُرعت بلا ربط، فكلُّ خصميةٍ — غيابٌ
 * كانت أو تلفُ عهدة — تُقيَّد دائنةً على الحساب العام وحده، ولا يُعرف من
 * الشجرة كم خُصم لكلِّ سبب. هنا يُفتح لكلِّ نوعٍ حسابٌ «خصميات <النوع>»
 * **تحت الحساب المربوط بالمفتاح العام** (`payroll_deductions`) — يُقرأ من
 * «الحسابات المحاسبية» لا من معرّفٍ أو كودٍ ثابت — ويُربط مفتاحُ النوع به.
 *
 * **الحسابُ العام يصير أباً وله قيودٌ سابقة (DED-*)** — والشجرةُ تسمح بذلك:
 * رصيدُ الأب في `Account::computeFullBalance()` = قيودُه المباشرة + أرصدةُ
 * أبنائه، وكشفُ الحساب (`getAccountAndDescendantIds`) يعرض قيودَه وقيودَ
 * أبنائه معاً. فلا يختفي شيءٌ من القديم، والجديدُ يظهر تحته مفصَّلاً.
 *
 * **آمنٌ للتكرار**: المفتاحُ المربوط لا يُمسّ (اختيارُ المحاسب)، والحسابُ
 * الموجود بالاسم نفسه تحت الأب يُعاد استعمالُه بدل إنشاء آخر. وإن لم يُربط
 * المفتاحُ العام لم يُنشأ شيء — لا أبَ يُفتح تحته.
 *
 * يستدعيه الترحيل `create_deduction_type_subaccounts` و`AccountingSettingsSeeder`.
 */
class DeductionTypeAccounts
{
    public const GENERAL_KEY = 'payroll_deductions';

    /** اسمُ الحساب الفرعي لنوع: «خصميات غياب». */
    public static function accountName(string $label): string
    {
        return 'خصميات '.$label;
    }

    /**
     * يُنشئ الحسابات الناقصة ويربط المفاتيح غير المربوطة.
     *
     * created/linked: المفتاح => «الكود — الاسم»؛ kept: مفاتيحُ مربوطةٌ سلفاً لم تُمسّ.
     *
     * @return array{skipped: string|null, created: array<string, string>, linked: array<string, string>, kept: array<int, string>}
     */
    public static function ensure(): array
    {
        $result = ['skipped' => null, 'created' => [], 'linked' => [], 'kept' => []];

        AccountingSettings::forget();
        $parentId = AccountingSettings::id(self::GENERAL_KEY);
        $parent = $parentId ? Account::find($parentId) : null;

        if (! $parent) {
            $result['skipped'] = 'حساب «خصميات الموظفين» العام (payroll_deductions) غير مربوط في الحسابات المحاسبية — لم يُنشأ شيء.';

            return $result;
        }

        DB::transaction(function () use ($parent, &$result) {
            foreach (Deduction::types() as $type => $label) {
                $key = PayrollAccounts::deductionTypeKey($type);
                $setting = AccountingSetting::query()->where('key', $key)->lockForUpdate()->first();

                if ($setting && $setting->account_id && Account::whereKey($setting->account_id)->exists()) {
                    $result['kept'][] = $key;

                    continue;
                }

                $name = self::accountName($label);
                $account = Account::query()
                    ->where('parent_id', $parent->id)
                    ->where('name', $name)
                    ->first();

                if (! $account) {
                    $account = Account::create([
                        'name' => $name,
                        'code' => (string) self::nextChildCode($parent),
                        'parent_id' => $parent->id,
                        'type' => 'sub',
                        // طبيعةُ الأب ونوعُه وفرعُه — كما تفعل شاشة الشجرة للحساب الفرعي
                        'account_type' => $parent->account_type,
                        'balance_type' => $parent->balance_type,
                        'branch_id' => $parent->branch_id,
                        'balance' => 0,
                        'opening_balance' => 0,
                        'opening_balance_type' => $parent->balance_type,
                        'is_active' => true,
                        'is_system' => true,
                    ]);
                    $result['created'][$key] = $account->code.' — '.$account->name;
                }

                if ($setting) {
                    $setting->update([
                        'account_id' => $account->id,
                        'account_code' => (string) $account->code,
                    ]);
                } else {
                    AccountingSetting::create([
                        'key' => $key,
                        'name' => $name,
                        'description' => 'الحساب الدائن في قيد اعتماد خصمية «'.$label.'» — إن تُرك فارغاً استُعمل حساب «خصميات الموظفين» العام',
                        'group' => PayrollAccounts::GROUP,
                        'account_id' => $account->id,
                        'account_code' => (string) $account->code,
                        'is_active' => true,
                    ]);
                }

                $result['linked'][$key] = $account->code.' — '.$account->name;
            }
        });

        if ($result['created'] !== []) {
            // الأبُ صار له أبناء: رصيدُه = قيودُه المباشرة + أبناؤه (صفرٌ الآن)
            Account::updateParentBalances($parent->id, true);
            \Illuminate\Support\Facades\Cache::forget('accounts_tree');
        }

        AccountingSettings::forget();

        return $result;
    }

    /**
     * كودُ الابن التالي — قاعدةُ الشجرة نفسُها
     * (`AccountingOperations::nextChildAccountCode`): بادئةُ الأب ثمّ خانة
     * (٥٢٥٢٢٥٥٩ ← ٥٢٥٢٢٥٥٩١)، فإن امتلأت العشرُ فخانتان، والفراغاتُ تُملأ.
     */
    private static function nextChildCode(Account $parent): int
    {
        $prefix = (int) $parent->code;
        if ($prefix <= 0) {
            throw new \RuntimeException('حساب «'.$parent->name.'» بلا كود صالح — راجع شجرة الحسابات.');
        }

        for ($width = 1; $width <= 4; $width++) {
            $span = 10 ** $width;
            $base = $prefix * $span;

            $taken = Account::whereBetween('code', [$base + 1, $base + $span - 1])
                ->pluck('code')
                ->map(fn ($code) => (int) $code)
                ->flip();

            for ($i = 1; $i < $span; $i++) {
                if (! $taken->has($base + $i)) {
                    return $base + $i;
                }
            }
        }

        throw new \RuntimeException('تعذّر توليد كود فرعي تحت الحساب '.$prefix.' — المدى ممتلئ.');
    }
}
