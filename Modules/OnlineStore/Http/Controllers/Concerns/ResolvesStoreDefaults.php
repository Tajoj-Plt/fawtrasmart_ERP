<?php

namespace Modules\OnlineStore\Http\Controllers\Concerns;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\Product;
use App\Models\StoreHouse;
use App\Models\TreasuryEmployee;
use App\Services\OnlineStoreSettingsService;
use App\Support\AccountingSettings;

/**
 * مصدر واحد لحلّ المستودع والخزينة/البنك الافتراضيين للمتجر الإلكتروني.
 *
 * كلاهما يُقرأ حصراً من إعدادات المتجر (`default_storehouse_id`,
 * `default_treasury_id` — قسم checkout) بلا أي احتياطيٍّ ضمنيٍّ (لا مستودعٌ
 * `major`، ولا الخزينة الرئيسية للنظام): قرارٌ يخصّ المتجر يجب أن يُضبَط
 * صراحةً من شاشة إعداداته، لا أن يُخمَّن في الشيفرة — التخمين قد يسحب
 * مخزوناً من مستودعٍ غير مقصودٍ أو يرحّل قيداً محاسبياً لخزينةٍ خاطئة.
 * إن كان أيّهما غير مضبوطٍ، النتيجة null صراحةً في كل موضعٍ يستعمله.
 */
trait ResolvesStoreDefaults
{
    /** معرّف المستودع الافتراضي للمتجر — للاستعمال في قيود الاستعلام. */
    protected function defaultStorehouseId(): ?int
    {
        return $this->getDefaultStorehouse()?->id;
    }

    /**
     * المستودع الافتراضي للمتجر — من إعداد المتجر (`default_storehouse_id`
     * في شاشة إعدادات المتجر) حصراً، لا تخمين بديل. عمداً بلا أي احتياطي
     * (لا `major`، ولا "أوّل مستودعٍ نشط"): مصدر الحقيقة الوحيد لهذا القرار
     * هو الإعداد الذي يضبطه مديرُ المتجر صراحةً — أي احتياطيٍّ ضمنيّ قد
     * يختار مستودعاً غير مقصودٍ للمتجر فيُسحَب منه المخزون خطأً أو يظهر
     * منتجٌ "متوفر" بمخزونٍ لا صلة له بالمتجر. إن كان الإعداد فارغاً أو
     * يشير إلى مستودعٍ غير نشطٍ/غير تابعٍ للمتجر، النتيجة null صراحةً —
     * ويجب ضبط الإعداد من شاشة إعدادات المتجر، لا معالجته هنا بالتخمين.
     */
    protected function getDefaultStorehouse(): ?StoreHouse
    {
        $defaultStorehouseId = OnlineStoreSettingsService::get('default_storehouse_id');

        if (! $defaultStorehouseId) {
            return null;
        }

        return StoreHouse::onlineStore()->where('id', $defaultStorehouseId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * الخزينة/البنك الافتراضي للمتجر — من إعداد المتجر (`default_treasury_id`)
     * حصراً، بلا احتياطيٍّ إلى الخزينة الرئيسية في النظام. نفس منطق
     * `getDefaultStorehouse()`: قرار مالي يخصّ المتجر يجب أن يُضبَط صراحةً
     * من إعداداته، لا أن يُخمَّن — خزينة النظام الرئيسية قد لا تكون
     * الحساب المحاسبي الصحيح لإيرادات المتجر الإلكتروني تحديداً.
     */
    protected function getDefaultTreasuryAccount(): ?Account
    {
        return Account::onlineStore()->find(OnlineStoreSettingsService::get('default_treasury_id'));
    }

    /**
     * نفس الافتراضي أعلاه، لكن خزينة الموظف (عهدته في treasury_employees)
     * تُقدَّم عليه إن وُجدت — لشاشات الموظفين كفاتورة الشراء.
     */
    protected function getDefaultTreasuryAccountForEmployee(?int $employeeId): ?Account
    {
        if ($employeeId) {
            $treasuryId = TreasuryEmployee::where('employee_id', $employeeId)->value('treasury_id');

            if ($treasuryId && ($account = Account::find($treasuryId))) {
                return $account;
            }
        }

        return $this->getDefaultTreasuryAccount();
    }

    /**
     * حساب المخزون المقابل للمستودع الافتراضي — لا إعداد `inventory_account_id`
     * منفصلاً: حساب المخزون هو حساب المستودع الافتراضي نفسه (checkout)، وإلا
     * الحساب النظامي المشترك.
     */
    protected function getDefaultInventoryAccount(): ?Account
    {
        $storehouse = $this->getDefaultStorehouse();

        if ($storehouse) {
            $account = Account::where('storehouse_id', $storehouse->id)->first();

            if ($account) {
                return $account;
            }
        }

        return Account::find(AccountingSettings::id('inventory', SystemAccountIds::INVENTORY));
    }

    /**
     * كمية صنفٍ في المستودع الافتراضي للمتجر وحده — لا مخزون النظام كله،
     * وإلا ظهر منتجٌ "متوفر" برصيدٍ جالسٍ في مستودع فرعٍ أو عهدة موظف لا
     * صلة له بالمتجر. بلا استثناء ولا احتياطي: إن لم يُضبَط `default_storehouse_id`
     * في إعدادات المتجر، الكمية صفرٌ دائماً — نفس القرار في كل صفحات المتجر
     * (الرئيسية، تفاصيل المنتج، السلة، الدفع)، لا تساهلٌ هنا وصرامةٌ هناك.
     */
    protected function getStoreStockQuantity(Product $product): float
    {
        $defaultStorehouse = $this->getDefaultStorehouse();

        if (! $defaultStorehouse) {
            return 0.0;
        }

        return (float) $product->productDetails()
            ->where('store_house_id', $defaultStorehouse->id)
            ->sum('quantity');
    }
}
