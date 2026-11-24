<?php

namespace Modules\OnlineStore\Http\Controllers\Concerns;

use App\Models\Store\OnlineOrder;
use App\Support\HeaderBranchScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * نطاقُ عرض طلبات المتجر — قاعدةٌ واحدة للقائمة والتفاصيل ولوحة التحكم والتقارير.
 *
 * كانت الشاشات تصفّي بـ`forBranch(current_branch_id())` مباشرةً: الوقوفُ على
 * الفرع الرئيسي من الشريط العلوي لا يرفع القيد عمّن يحقّ له «الكل»، ولا يردّ
 * موظفَ فرعٍ فرعيٍّ بدّل إلى الرئيسي إلى فرعه. {@see HeaderBranchScope::nodeId()}
 * هو المرجع: الرئيسي = المنشأة كلّها لصاحب `data_scope_all` وغير الموظف ومدير
 * المقرّ فقط؛ وغيرُهم يُردّ إلى فرعه.
 *
 * وصفحةُ الطلب نفسُها (`/orders/{id}` وما تحتها) لم تكن تُفحَص أصلاً: أيُّ
 * مستخدمٍ يملك صلاحية العرض يفتح طلبَ أيّ فرعٍ برقمه. الآن خارجَ النطاق = 404.
 */
trait ScopesStoreOrders
{
    /** عقدةُ الفرع التي تُصفّى بها بياناتُ المتجر — `null` = بلا تصفية. */
    protected function storeBranchNode(): ?int
    {
        return HeaderBranchScope::nodeId();
    }

    /** استعلامُ الطلبات داخل نطاق المستخدم. */
    protected function scopedOrders(): Builder
    {
        return OnlineOrder::query()->forBranch($this->storeBranchNode());
    }

    /** 404 لطلبٍ خارج النطاق — لا 403: لا يُفصَح عن وجوده. */
    protected function ensureOrderInScope(OnlineOrder $order): void
    {
        abort_unless($this->scopedOrders()->whereKey($order->getKey())->exists(), 404);
    }
}
