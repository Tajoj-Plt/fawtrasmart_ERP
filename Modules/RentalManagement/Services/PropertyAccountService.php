<?php

namespace Modules\RentalManagement\Services;

use App\Models\Account;
use App\Models\StoreHouse;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Support\RealEstateAccounts;
use Modules\RentalManagement\Support\RealEstateSettings;

/**
 * حسابات العقار في دليل الحسابات ومستودعُه في المخزون.
 *
 * «مستحقات ملاك العقار» حسابٌ أب في الخصوم تتفرّع منه الحسابات الجارية
 * للملّاك. حسابات المباني والوحدات التي أُنشئت في إصدارٍ سابق تبقى ولا يُنشأ
 * منها جديد (انظر `ensureBuildingAccount`).
 */
class PropertyAccountService
{
    /** `accounts.code` عمودُ int موقَّع في MySQL */
    private const MAX_CODE = 2147483647;

    public const OWNER_PAYABLES_NAME = 'مستحقات ملاك العقار';

    /**
     * حساب «مستحقات ملاك العقار»: من الربط المحاسبي، وإلا فالحساب المسمّى بهذا
     * الاسم، وإلا يُنشأ تحت جذر الخصوم ويُحفظ في الإعدادات.
     */
    public function ownerPayablesAccount(): Account
    {
        $linked = Account::find(RealEstateSettings::get('owner_payables_account_id'));

        // مربوطٌ وصالح (خصوم، ليس خزينة): يُستعمل كما اختاره المحاسب
        if ($linked && ! RealEstateAccounts::problem('owner_payables_account_id', $linked)) {
            return $linked;
        }

        $account = Account::where('name', self::OWNER_PAYABLES_NAME)->where('account_type', 'liabilities')->first();

        if (! $account) {
            $liabilities = Account::whereNull('parent_id')->where('account_type', 'liabilities')->orderBy('code')->first();

            $account = Account::create([
                'name' => self::OWNER_PAYABLES_NAME,
                'code' => $this->nextCode($liabilities),
                'parent_id' => $liabilities?->id,
                'type' => 'main',
                'account_type' => 'liabilities',
                'balance_type' => 'credit',
                'balance' => 0,
                'is_active' => 1,
            ]);
        }

        RealEstateSettings::put('owner_payables_account_id', $account->id);

        return $account;
    }

    /**
     * ينقل حسابات المباني التي أُنشئت تحت الإيرادات (الإصدار الأول) إلى الخصوم
     * مع حسابات وحداتها — القيود تشير إلى المعرّف فلا تتأثّر بتغيّر الرمز.
     */
    public function realign(): void
    {
        $parent = $this->ownerPayablesAccount();

        Building::withTrashed()->whereNotNull('account_id')->get()->each(function (Building $building) use ($parent) {
            $account = Account::find($building->account_id);

            if (! $account || ((int) $account->parent_id === (int) $parent->id && $account->account_type === 'liabilities')) {
                return;
            }

            $account->forceFill(['parent_id' => $parent->id, 'account_type' => 'liabilities', 'code' => $this->nextCode($parent)])->save();

            foreach (Account::where('parent_id', $account->id)->orderBy('id')->get() as $index => $child) {
                $code = (int) ($account->code.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));
                $free = $code <= self::MAX_CODE && ! Account::where('code', $code)->whereKeyNot($child->id)->exists();
                $child->forceFill(['account_type' => 'liabilities'] + ($free ? ['code' => $code] : []))->save();
            }
        });
    }

    /**
     * حساب المبنى **إن وُجد** — لا يُنشأ جديد.
     *
     * كان لكل مبنى حسابٌ أب ولكل وحدةٍ حسابٌ فرعي تحت الخصوم يُقيَّد عليه
     * إيجار المبنى الذي لا مالك له، فيظهر دخلُ المنشأة دَيناً عليها. الآن
     * إيجار المبنى المملوك يُقيَّد على «إيرادات الإيجار»، والمُدار على الحساب
     * الجاري للمالك؛ فلم يعد شيءٌ يُرحَّل على هذه الحسابات. ما أُنشئ منها
     * سابقاً يبقى تاريخاً مالياً ويلحق به اسم صاحبه.
     */
    public function ensureBuildingAccount(Building $building): ?Account
    {
        return $building->account_id ? Account::find($building->account_id) : null;
    }

    /** حساب الوحدة إن وُجد — لا يُنشأ جديد (انظر `ensureBuildingAccount`). */
    public function ensureUnitAccount(Unit $unit): ?Account
    {
        return $unit->account_id ? Account::find($unit->account_id) : null;
    }

    /** الاسم تغيّر؟ يلحق به اسم الحساب. */
    public function syncBuilding(Building $building): void
    {
        if ($building->account_id && $building->wasChanged('name_ar')) {
            Account::whereKey($building->account_id)->update(['name' => $building->name_ar]);
        }
    }

    /** تغيّر رقم الوحدة أو نوعها، أو نُقلت إلى مبنى آخر — يلحق بها حسابها. */
    public function syncUnit(Unit $unit): void
    {
        if (! $unit->account_id) {
            return;
        }

        $changes = [];

        if ($unit->wasChanged(['unit_no', 'unit_type'])) {
            $changes['name'] = $this->unitAccountName($unit);
        }

        if ($unit->wasChanged('building_id') && ($building = $unit->building) && ($parent = $this->ensureBuildingAccount($building))) {
            $changes['parent_id'] = $parent->id;
        }

        if ($changes) {
            Account::whereKey($unit->account_id)->update($changes);
        }
    }

    /** حسابٌ بلا قيودٍ ولا أبناء يُحذف مع صاحبه؛ وما عليه حركةٌ يبقى تاريخاً مالياً. */
    public function release(Building|Unit $owner): void
    {
        $account = $owner->account_id ? Account::find($owner->account_id) : null;

        if ($account && ! $account->journalEntryDetails()->exists() && ! $account->children()->exists()) {
            $account->delete();
            $owner->forceFill(['account_id' => null])->saveQuietly();
        }
    }

    /**
     * ينشئ مستودعاً باسم المبنى في قسم المخزون مع حسابه المحاسبي — بنفس ما
     * تفعله شاشة «إضافة مستودع»: حسابُ أصولٍ تحت حساب «المخزون».
     */
    public function createStoreHouse(Building $building): StoreHouse
    {
        $inventory = AccountingSettings::account('inventory');

        if (! $inventory) {
            throw ValidationException::withMessages([
                'warehouse_mode' => 'تعذّر إنشاء المستودع: لم يُربط حساب «المخزون» في الإعدادات ← الحسابات المحاسبية.',
            ]);
        }

        $storeHouse = StoreHouse::create([
            'name' => $building->name_ar,
            'shipping_address' => trim(implode(' — ', array_filter([$building->city, $building->district, $building->address]))) ?: null,
            'status' => 'active',
            'major' => 0,
            'branch_id' => $building->branch_id,
            'view_permissions' => 0,
            'crate_invoices_permissions' => 0,
            'edit_stock_permissions' => 0,
        ]);

        $account = Account::create([
            'name' => $storeHouse->name,
            'code' => $this->nextCode($inventory),
            'parent_id' => $inventory->id,
            'type_accont' => 0,
            'type' => 'sub',
            'account_type' => 'assets',
            'balance_type' => 'debit',
            'balance' => 0,
            'is_active' => 1,
            'storehouse_id' => $storeHouse->id,
            'branch_id' => $building->branch_id,
        ]);

        OperationLog::record('created', 'storehouse_log', $storeHouse->id, sprintf(
            'أُنشئ المستودع **%s** تلقائياً لمبنى العقارات (%s) مع الحساب المحاسبي رقم **%s**',
            $storeHouse->name, $building->code, $account->code));

        return $storeHouse;
    }

    private function unitAccountName(Unit $unit): string
    {
        return trim($unit->type_label.' '.$unit->unit_no);
    }

    /** رمزٌ فريد تحت الأب بنمط الدليل (رمز الأب + 001…)، مع سقف عمود int. */
    public function nextCode(?Account $parent): ?int
    {
        if ($parent) {
            $last = Account::where('parent_id', $parent->id)->max('code');
            $code = $last ? (int) $last + 1 : (int) (($parent->code ?: $parent->id).'001');
        } else {
            $code = (int) Account::whereNull('parent_id')->max('code') + 1;
        }

        if ($code > self::MAX_CODE) {
            $code = (int) Account::max('code') + 1;
        }

        while ($code <= self::MAX_CODE && Account::where('code', $code)->exists()) {
            $code++;
        }

        return $code <= self::MAX_CODE ? $code : null;
    }
}
