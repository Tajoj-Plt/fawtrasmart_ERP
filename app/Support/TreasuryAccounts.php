<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Support\Collection;

/**
 * حسابات الخزائن النقدية — مصدرٌ واحد لكل قائمةٍ تسأل «من أيّ خزينة؟».
 *
 * **العمود `type_accont` ليس علامةَ خزينة.** شاشة الخزائن تُسنده صفراً
 * للخزينة وواحداً للبنك عند الإنشاء من تلك الشاشة وحدها، أمّا الخزائن
 * المزروعة مع شجرة الحسابات فتبقى `NULL`. فالاستعلام
 * `whereIn('type_accont', [0, 1])` يُسقط الخزائن الأصلية ويجلب مكانها
 * حساباتٍ ليست خزائن أصلاً — مستودعاتٍ وسُلفاً صادف أن حملت صفراً.
 *
 * والصواب أن تُقرأ من الشجرة: الخزائن أبناءُ الحساب المضبوط في
 * «الحسابات المحاسبية» تحت المفتاح `cash_treasuries_parent`، والبنوك
 * أبناءُ `bank_accounts_parent`.
 */
class TreasuryAccounts
{
    /** مفتاح الحساب الأب للخزائن النقدية في «الحسابات المحاسبية» */
    public const CASH_PARENT = 'cash_treasuries_parent';

    /** ومفتاح البنوك — لمن أراد الاثنين معاً */
    public const BANK_PARENT = 'bank_accounts_parent';

    /**
     * الخزائن النقدية النشطة، مرتّبةً بالاسم.
     *
     * @return Collection<int, Account>
     */
    public static function cash(): Collection
    {
        return self::childrenOf(self::CASH_PARENT);
    }

    /**
     * الخزائن والبنوك معاً — لشاشاتٍ يقبل فيها الدفع من حسابٍ بنكي.
     *
     * @return Collection<int, Account>
     */
    public static function cashAndBanks(): Collection
    {
        return self::childrenOf(self::CASH_PARENT)
            ->concat(self::childrenOf(self::BANK_PARENT))
            ->values();
    }

    /**
     * أبناء حسابٍ مضبوطٍ في الإعدادات — فارغةٌ إن لم يكن مربوطاً.
     *
     * @return Collection<int, Account>
     */
    private static function childrenOf(string $settingKey): Collection
    {
        $parentId = AccountingSettings::id($settingKey);

        if (! $parentId) {
            return collect();
        }

        return Account::where('parent_id', $parentId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'balance']);
    }
}
