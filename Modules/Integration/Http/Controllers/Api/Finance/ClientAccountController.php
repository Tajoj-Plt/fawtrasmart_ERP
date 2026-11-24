<?php

namespace Modules\Integration\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Client;
use App\Models\JournalEntryDetail;
use App\Models\User;
use App\Services\Clients\ClientVisibilityScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Account\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

/**
 * العملاء المرتبطون بالمستخدم وكشف حساب كلٍّ منهم.
 *
 * **النطاق عرضٌ لا إنشاء:** كانا على `eligibleReceiptClientIds()` — قاعدة
 * *إنشاء* سند القبض المقيَّدة بخطّ سير اليوم — فلا يرى المندوب كشف حساب
 * عميله المُسنَد إليه إلا يوم زيارته. الآن: نفس العملاء الذين يراهم في
 * «إدارة العملاء» على الويب ({@see ClientVisibilityScope})، ومعهم للمشرف
 * عملاءُ من يشرف عليهم، ولصاحب `data_scope_all` الكلّ. وقاعدة إنشاء
 * سند القبض نفسها لم تتغيّر.
 */
class ClientAccountController extends Controller
{
    use ScopesToEmployeeHierarchy;

    public function index(Request $request): JsonResponse
    {
        $eligibleIds = $this->linkedClientIds($request->user());

        $query = Client::query()->select('id', 'trade_name', 'first_name', 'last_name', 'phone', 'mobile', 'status_id');

        if ($eligibleIds !== null) {
            $query->whereIn('id', $eligibleIds->all() ?: [0]);
        }

        // حجم الصفحة محصور — طلبٌ بـ per_page=100000 كان يجرّ جدول العملاء كلّه
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $clients = $query->orderBy('trade_name')->paginate($perPage);

        return response()->json(['success' => true, 'data' => $clients]);
    }

    /** كشف حساب عميل: حركة حسابه الدفتري كاملةً برصيدٍ متحرّك. */
    public function statement(Request $request, $clientId): JsonResponse
    {
        $eligibleIds = $this->linkedClientIds($request->user());

        if ($eligibleIds !== null && ! $eligibleIds->contains((int) $clientId)) {
            abort(403, 'لا تملك صلاحية الوصول إلى كشف حساب هذا العميل.');
        }

        $client = Client::findOrFail($clientId);
        $account = Account::ledger()->where('client_id', $clientId)->first();

        if (! $account) {
            return response()->json([
                'success' => true,
                'data' => ['client' => $client, 'account_id' => null, 'opening_balance' => 0, 'closing_balance' => 0, 'entries' => []],
            ]);
        }

        $details = JournalEntryDetail::with('journalEntry:id,date,reference_number,description')
            ->where('account_id', $account->id)
            ->whereHas('journalEntry')
            ->get()
            ->sortBy(fn ($d) => [$d->journalEntry->date ?? '', $d->id])
            ->values();

        $running = (float) ($account->opening_balance ?? 0);
        $entries = $details->map(function ($d) use (&$running) {
            $running += (float) $d->debit - (float) $d->credit;

            return [
                'date' => $d->journalEntry->date ?? null,
                'reference_number' => $d->journalEntry->reference_number ?? null,
                'description' => $d->description ?: ($d->journalEntry->description ?? null),
                'debit' => (float) $d->debit,
                'credit' => (float) $d->credit,
                'balance' => round($running, 2),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'client' => $client,
                'account_id' => $account->id,
                'opening_balance' => (float) ($account->opening_balance ?? 0),
                'closing_balance' => round($running, 2),
                'entries' => $entries,
            ],
        ]);
    }

    /**
     * عملاء المستخدم: ما يراه في «إدارة العملاء»، ومعه للمشرف عملاءُ
     * مرؤوسيه المُسندون إليهم — أو `null` بلا قيد (`data_scope_all`).
     */
    private function linkedClientIds(User $user): ?Collection
    {
        $teamUserIds = $this->accessibleUserIds();
        if ($teamUserIds === null) {
            return null;
        }

        $ids = ClientVisibilityScope::eligibleClientIds($user);
        if ($ids === null) {
            return null;
        }

        /*
            والعملاء **المُسنَدون** (`client_employee`) إليه وإلى فريقه دائماً:
            «إدارة العملاء» تبدأ بخطّ السير، فمندوبٌ يملك «خط السير» و«عملاؤه»
            معاً يُحبس في خطّ اليوم — فكان يرى صفراً من عملائه المُسنَدين بينما
            يراهم مشرفه.
        */
        $employeeIds = User::whereIn('id', array_merge([$user->id], $teamUserIds))
            ->whereNotNull('employee_id')
            ->pluck('employee_id');

        $ids = $ids->merge(DB::table('client_employee')->whereIn('employee_id', $employeeIds)->pluck('client_id'));

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }
}
