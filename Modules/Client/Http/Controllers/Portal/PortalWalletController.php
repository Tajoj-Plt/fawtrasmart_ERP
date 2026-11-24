<?php

namespace Modules\Client\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientWalletTransaction;
use App\Services\Wallets\ClientWalletService;
use Modules\Client\Http\Controllers\Concerns\GuardsClientPortal;

/**
 * محفظةُ العميل في بوابته — عرضٌ فقط.
 *
 * ═══════════ رصيدٌ واحدٌ لا حسابان ═══════════
 *
 * الرصيدُ من `ClientWalletService::balance()` — وهو نفسُه الذي تقرؤه شاشةُ
 * الموظّف (`ClientWalletController::show`). ولا يُجمَع هنا من حركات الجدول:
 * الرصيدُ يعيش في `accounts.balance` كبقيّة الحسابات، وجمعٌ ثانٍ فوق
 * الحركات يُنتج رقماً يختلف عن دفتر الأستاذ يومَ تُعدَّل حركةٌ أو يُلغى
 * قيدُها — ورقمان لرصيدٍ واحدٍ أسوأ من لا رقم.
 *
 * و«الرصيد بعد الحركة» عمودٌ مخزَّنٌ (`balance_after`) تكتبه الخدمةُ عند كل
 * حركة، لا مجموعٌ متسلسلٌ نحسبه في القالب: الصفحةُ الثانية من الكشف لا
 * تعرف ما قبلها، فتسلسلٌ يبدأ من الصفر في كل صفحةٍ كذبٌ مرتّب.
 *
 * ═══════════ ولا حركةَ من هنا ═══════════
 *
 * الإيداعُ والسحبُ والسدادُ من المحفظة كلُّها قيودٌ على الخزينة وحساب
 * العميل — يكتبها موظّفٌ من شاشة المحافظ. البوابةُ تُري العميلَ رصيدَه
 * وكشفَه، لا أكثر.
 *
 * @see \App\Services\Wallets\ClientWalletService محرّك المحفظة وقيودها
 */
class PortalWalletController extends Controller
{
    use GuardsClientPortal;

    /** مفتاحُ «صلاحيات بوابة العميل» الذي يفتح هذه الشاشة */
    private const PERMISSION = 'view_wallet';

    public function index()
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        $client = Client::find($clientId);

        /* عميلٌ بلا سجلّ — حسابٌ مربوطٌ بعميلٍ محذوف. لا محفظةَ له ولا
           `balance()` تقبل `null`؛ والشاشةُ تُعرَض فارغةً بلا انفجار. */
        if (! $client) {
            return view('client::portal.wallet', [
                'portalClient' => null,
                'balance' => 0.0,
                'transactions' => ClientWalletTransaction::whereRaw('1 = 0')->paginate(20),
                'totalIn' => 0.0,
                'totalOut' => 0.0,
            ]);
        }

        $transactions = ClientWalletTransaction::query()
            ->where('client_id', $client->id)
            // الفاتورةُ برقمها وحده: حركةُ «سداد فاتورة» بلا رقمها سطرٌ لا يُراجَع
            ->with('invoice:id,code')
            ->orderByDesc('id')
            ->paginate(20);

        /* الداخلُ والخارجُ على الحركات كلِّها لا على الصفحة: بطاقةُ ملخّصٍ
           تتغيّر بتقليب الصفحات ليست ملخّصاً. و`CREDIT_TYPES` هي نفسُها
           التي يقرؤها `isCredit()` في القالب — لا قائمةَ أنواعٍ ثانية. */
        $sums = ClientWalletTransaction::where('client_id', $client->id)
            ->selectRaw('type, SUM(amount) AS total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $totalIn = 0.0;
        $totalOut = 0.0;

        foreach ($sums as $type => $total) {
            if (in_array($type, ClientWalletTransaction::CREDIT_TYPES, true)) {
                $totalIn += (float) $total;
            } else {
                $totalOut += (float) $total;
            }
        }

        return view('client::portal.wallet', [
            'portalClient' => $client,
            'balance' => ClientWalletService::balance($client),
            'transactions' => $transactions,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
        ]);
    }
}
