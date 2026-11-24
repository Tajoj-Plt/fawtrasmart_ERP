<?php

namespace Modules\Client\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentsProcess;
use Illuminate\Support\Facades\DB;
use Modules\Client\Http\Controllers\Concerns\GuardsClientPortal;

/**
 * مرتجعاتُ العميل في بوابته — عرضٌ فقط.
 *
 * ═══════════ ما «المرتجع» في هذا النظام؟ ═══════════
 *
 * صفٌّ في `invoices` بـ`type = 'returned'` لا جدولٌ مستقلّ، و`reference_id`
 * فيه معرّفُ الفاتورة الأصلية التي رُدّت عنها (يكتبه
 * `ReturnInvoiceController` مع `reference_number` بالقيمة نفسِها؛ نقرأ
 * العلاقةَ `originalInvoice()` لأنها المعرَّفة على النموذج).
 *
 * ═══════════ لماذا لا نعرض `payment_status`؟ ═══════════
 *
 * `ReturnInvoiceController` يكتب `payment_status = 4` على كلّ مرتجعٍ
 * **ثابتاً**، فالعمودُ لا يميّز مرتجعاً عن مرتجع — عمودٌ لا يفرّق ليس حالة.
 * والحالةُ التي تعني العميلَ شيئاً: **أاسترددتَ نقداً أم نزل المبلغ من
 * مستحقّك؟** وهذه تُقرأ من صرف الخزينة الذي رافق المرتجع:
 * `payments_process` بنوع `treasury_return` على معرّف المرتجع نفسِه
 * (والسنداتُ الفاشلة `payment_status = 5` مستبعَدة، كما تُستبعَد في حساب
 * سقف الردّ داخل مسار المرتجع).
 *
 * ولا شيءَ يُكتب هنا: المرتجعُ يُنشئه موظّفٌ من شاشة المبيعات، والعميلُ
 * يقرؤه.
 */
class PortalReturnController extends Controller
{
    use GuardsClientPortal;

    /** مفتاحُ «صلاحيات بوابة العميل» الذي يفتح هذه الشاشة */
    private const PERMISSION = 'view_returns';

    public function index()
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        $returns = Invoice::query()
            ->where('client_id', $clientId)
            ->where('type', 'returned')
            // الأصليةُ بعمودين فقط: الشاشةُ تعرض رقمَها ورابطَها لا مستنداً كاملاً
            ->with('originalInvoice:id,code')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('client::portal.returns', [
            'portalClient' => Client::find($clientId),
            'returns' => $returns,
            'refunds' => $this->cashRefunds($returns->pluck('id')->all()),
            // الإحصاءاتُ على المرتجعات كلِّها لا على صفحةٍ منها: «٣ مرتجعات»
            // فوق جدولٍ فيه خمسةَ عشر سطراً رقمٌ يُكذّب ما تحته
            'totalCount' => (int) Invoice::where('client_id', $clientId)->where('type', 'returned')->count(),
            'totalValue' => (float) Invoice::where('client_id', $clientId)->where('type', 'returned')->sum('grand_total'),
        ]);
    }

    /**
     * ما صُرف نقداً لكلّ مرتجعٍ في الصفحة — معرّفُ المرتجع ⇦ المبلغ.
     *
     * استعلامٌ واحدٌ للصفحة كلِّها: سؤالُ كلّ صفٍّ عن صرفه يجعلها ستةَ عشر
     * استعلاماً بدل اثنين.
     *
     * @param  array<int, int>  $returnIds
     * @return array<int, float>
     */
    private function cashRefunds(array $returnIds): array
    {
        if ($returnIds === []) {
            return [];
        }

        return PaymentsProcess::query()
            ->whereIn('invoice_id', $returnIds)
            ->where('type', 'treasury_return')
            ->where('payment_status', '!=', 5)
            ->groupBy('invoice_id')
            ->pluck(DB::raw('SUM(amount)'), 'invoice_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
