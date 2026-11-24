<?php

namespace Modules\Stock\Http\Controllers\Concerns;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\AccountingSetting;
use App\Support\AccountingSettings;

/**
 * قراءة الحسابات المحاسبية من شاشة «الحسابات المحاسبية» بدل الثوابت البرمجية.
 *
 * **لماذا سمة واحدة؟**
 *
 * كانت خمسة متحكّمات في هذه الوحدة تكتب رقم الحساب في الشيفرة
 * (`SystemAccountIds::PURCHASES`)، وسادسٌ — `StorehouseController` — يقرأ من
 * الإعدادات بنسخته الخاصّة من دالة الحلّ. فما يضبطه المحاسب في الشاشة كان
 * يسري على شاشة واحدة ولا يسري على البقيّة. النسخة هنا واحدة، ويرثها الجميع.
 *
 * **ترتيب الحلّ**
 *
 *   الإعداد المربوط والمفعَّل ← وإلا الثابت البرمجي كاحتياط.
 *
 * الاحتياط مقصود: النظام يبقى عاملاً قبل أن يربط المحاسب مفتاحاً، ويسري ربطه
 * فور حفظه بلا نشر جديد. وهو نفس تعاقد `AccountingSettings::id()` ونفس ما
 * تعمل به وحدة المشتريات في أربعين موضعاً.
 *
 * **متى `require`؟**
 *
 * القيد المحاسبي لا يقبل حساباً مفقوداً: `requireAccountingAccount()` ترمي
 * برسالة تسمّي الحساب وتدلّ على شاشته، بدل `null` يتحوّل بعد أسطر إلى قيد
 * بطرف واحد أو حساب معلَّق في جذر الشجرة. أمّا القراءات والتقارير فتستعمل
 * `accountingAccountId()` وتتحمّل الغياب.
 */
trait ResolvesAccountingAccounts
{
    /**
     * معرّف الحساب المضبوط لمفتاح — أو الاحتياط البرمجي.
     *
     * @param  string  $key  مفتاح الإعداد، انظر AccountingSettingsSeeder::definitions()
     * @param  int|null  $fallback  ثابت `SystemAccountIds` المقابل
     */
    protected function accountingAccountId(string $key, ?int $fallback = null): ?int
    {
        return AccountingSettings::id($key, $fallback);
    }

    /**
     * سجلّ الحساب — أو null إن لم يوجد لا في الإعدادات ولا في الاحتياط.
     */
    protected function accountingAccount(string $key, ?int $fallback = null): ?Account
    {
        return AccountingSettings::account($key, $fallback);
    }

    /**
     * الحساب أو استثناء — لمولّدات القيود التي لا يصحّ أن تكمل بلا حساب.
     *
     * @throws \Exception إن لم يُربط المفتاح ولم يوجد حساب الاحتياط
     */
    protected function requireAccountingAccount(string $key, ?int $fallback = null): Account
    {
        $account = $this->accountingAccount($key, $fallback);

        if (! $account) {
            throw new \Exception(__('settings.accounting_settings.account_not_mapped', [
                'account' => $this->accountingAccountLabel($key),
            ]));
        }

        return $account;
    }

    /**
     * معرّف الحساب أو استثناء — بنفس ضمانة `requireAccountingAccount()`.
     */
    protected function requireAccountingAccountId(string $key, ?int $fallback = null): int
    {
        return (int) $this->requireAccountingAccount($key, $fallback)->id;
    }

    /**
     * اسم الحساب كما يعرفه المستخدم في الشاشة — لرسائل الخطأ.
     *
     * يُقرأ من صفّ الإعداد نفسه، فلا يحتاج كل مفتاح جديد مفتاحَ ترجمة يقابله.
     */
    protected function accountingAccountLabel(string $key): string
    {
        try {
            return AccountingSetting::where('key', $key)->value('name') ?: $key;
        } catch (\Throwable $e) {
            return $key;
        }
    }

    /**
     * حساب مستودع بعينه — يُوجَد أو يُنشأ تحت الأب المضبوط في الإعدادات.
     *
     * **لماذا هنا؟**
     *
     * كان إنشاء حساب المستودع مكرّراً في أحد عشر موضعاً عبر ستة متحكّمات،
     * وفي كلٍّ منها بصيغة مختلفة:
     *
     *   • *بلا أب*: عشرةٌ منها لا تضبط `parent_id` إطلاقاً، فيُعلَّق الحساب في
     *     جذر شجرة الحسابات خارج شجرة المخزون — فيغيب عن ميزان مراجعة المخزون
     *     ولا يُرحَّل رصيده إلى أيّ أب.
     *   • *بنوعٍ مختلف*: `assets` و`storehouse` و`assetss` (بخطأ إملائي) —
     *     ثلاثة أنواع لشيء واحد.
     *   • *بلا كود*: فلا يظهر له رقم في دليل الحسابات.
     *
     * صار الإنشاء في موضع واحد، تحت الأب الذي يختاره المحاسب في مفتاح
     * `warehouse_accounts_parent`.
     *
     * @param  int|string|null  $storeHouseId  معرّف المستودع
     * @param  string|null  $storeHouseName  اسمه، للتسمية عند الإنشاء
     *
     * @throws \Exception إن لم يُمرَّر معرّف مستودع
     */
    protected function warehouseAccount($storeHouseId, ?string $storeHouseName = null): Account
    {
        if (blank($storeHouseId)) {
            throw new \Exception(__('settings.accounting_settings.warehouse_account_missing_store'));
        }

        $account = Account::where('storehouse_id', $storeHouseId)->first();

        if ($account) {
            // حسابات أُنشئت قبل هذا التوحيد وبقيت بلا أب: تُضَمّ إلى الشجرة
            // عند أول مرور، فلا تحتاج ترحيلَ بيانات منفصلاً
            if (blank($account->parent_id)) {
                $account->parent_id = $this->warehouseAccountsParentId();
                $account->save();
            }

            return $account;
        }

        $parentId = $this->warehouseAccountsParentId();
        $name = 'حساب المستودع - '.($storeHouseName ?: ('مستودع '.$storeHouseId));

        return Account::create([
            'name' => $name,
            'code' => $this->nextWarehouseAccountCode($parentId),
            'parent_id' => $parentId,
            'storehouse_id' => $storeHouseId,
            'account_type' => 'assets',
            'balance_type' => Account::DEBIT,
            'balance' => 0,
            'status' => 1,
        ]);
    }

    /** الأب المضبوط لحسابات المستودعات — وإلا حساب المخزون */
    protected function warehouseAccountsParentId(): int
    {
        return $this->requireAccountingAccountId(
            'warehouse_accounts_parent',
            $this->accountingAccountId('inventory', SystemAccountIds::INVENTORY)
        );
    }

    /**
     * رمز شاغر تحت الأب — `<كود الأب><تسلسل>`.
     *
     * الحسابات المنشأة تلقائياً كانت بلا كود إطلاقاً، فتظهر فارغةً في دليل
     * الحسابات ولا تُرتَّب معه.
     *
     * **الفحص عالمي لا تحت الأب وحده**: `accounts.code` عليه قيد فريد على
     * مستوى الجدول كلّه، فالاكتفاء بفحص إخوة الحساب يصطدم برمزٍ يحمله حسابٌ
     * في فرع آخر. والمقارنة نصّية بعد التحويل: السائق قد يُعيد الرمز عدداً،
     * فتفشل المقارنة الصارمة ويُسلَّم رمزٌ محجوز.
     */
    protected function nextWarehouseAccountCode(int $parentId): string
    {
        $parentCode = (string) (Account::where('id', $parentId)->value('code') ?: '');

        if ($parentCode === '') {
            return '';
        }

        $taken = Account::where('code', 'like', $parentCode.'%')
            ->pluck('code')
            ->filter()
            ->map(fn ($code) => (string) $code)
            ->all();

        for ($i = 1; $i <= 999; $i++) {
            $candidate = $parentCode.str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            if (! in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }

        // كل الرموز الممكنة محجوزة: يُترك فارغاً بدل أن يُصطدم بالقيد الفريد
        return '';
    }
}
