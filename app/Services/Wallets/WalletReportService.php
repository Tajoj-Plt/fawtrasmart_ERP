<?php

namespace App\Services\Wallets;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * تقاريرُ المحافظ — محافظُ العملاء ومحافظُ الموردين بمحرّكٍ واحد.
 *
 * ══════════ من أين يأتي كلُّ رقم ══════════
 *
 *   • **الرصيدُ** (افتتاحيُّ المدى وختاميُّه) من **القيود** على حساب المحفظة
 *     حتى تاريخٍ — لا من `accounts.balance` (رصيدُ اليوم وحده) ولا من
 *     `balance_after` في جدول الحركات. فالرصيدُ الافتتاحيُّ للطرف يُقيَّد
 *     قيداً `OB-…` على المحفظة بلا صفٍّ في جدول الحركات، وجمعُ الحركات
 *     وحدَها كان سيُسقطه.
 *   • **الحركاتُ** مفصَّلةً بنوعها (إيداع/سحب/سداد فاتورة/ردّ) من جدول حركات
 *     المحفظة، بتاريخ قيدها (`journal_entries.date`) — التاريخُ نفسُه الذي
 *     يُحسب به الرصيد، فتتّسق المعادلة.
 *   • وما لا يفسّره نوعُ حركة (رصيدٌ افتتاحيٌّ في المدى، أو قيدٌ يدويٌّ على
 *     المحفظة) يظهر عموداً مستقلاً «أخرى» — فالمعادلة تُغلق حتماً:
 *
 *         افتتاحي + إيداع + ردّ − سحب − سداد فواتير + أخرى = ختامي
 *
 * ══════════ الاتجاه ══════════
 *
 * محفظةُ العميل **التزام** (مالُه عندنا): رصيدُها دائن − مدين. ومحفظةُ
 * المورد **أصل** (دفعةٌ مقدَّمةٌ له): مدين − دائن. والناتجُ في الحالين موجبٌ
 * بمقدار ما في المحفظة — فالتقريران يُقرآن بالطريقة نفسها.
 *
 * @see ClientWalletService
 * @see SupplierWalletService
 */
final class WalletReportService
{
    /** تزيد الرصيدَ (+) أو تُنقصه (−) */
    public const TYPE_SIGNS = [
        'deposit' => 1,
        'refund' => 1,
        'withdraw' => -1,
        'invoice_paid' => -1,
    ];

    public const TYPE_LABELS = [
        'client' => [
            'deposit' => 'إيداع في المحفظة',
            'withdraw' => 'سحب من المحفظة',
            'invoice_paid' => 'سداد فاتورة',
            'refund' => 'ردّ سداد فاتورة',
        ],
        'supplier' => [
            'deposit' => 'دفعة مقدَّمة للمورد',
            'withdraw' => 'استرداد من المحفظة',
            'invoice_paid' => 'سداد فاتورة شراء',
            'refund' => 'ردّ سداد فاتورة',
        ],
    ];

    private const PARTIES = [
        'client' => [
            'table' => 'client_wallet_transactions',
            'party' => 'client_id',
            'invoice' => 'invoice_id',
            'invoice_table' => 'invoices',
            'wallet' => Account::WALLET_CLIENT,
            'liability' => true,
        ],
        'supplier' => [
            'table' => 'supplier_wallet_transactions',
            'party' => 'supplier_id',
            'invoice' => 'purchase_invoice_id',
            'invoice_table' => 'purchase_invoices',
            'wallet' => Account::WALLET_SUPPLIER,
            'liability' => false,
        ],
    ];

    /** يومُ الحركة: تاريخُ قيدها، وإلّا يومُ إدخالها */
    private const DAY = 'COALESCE(e.date, DATE(t.created_at))';

    /** حقولُ الطلب التي تقرؤها الشاشة — `$request->only(self::SCREEN_INPUTS)` */
    public const SCREEN_INPUTS = ['view', 'type', 'treasury', 'balance', 'show_zero'];

    private const PER_PAGE = 50;

    /** سقفُ صفوف التصدير في عرض الحركات */
    private const EXPORT_LIMIT = 5000;

    private array $config;

    public function __construct(private readonly string $party)
    {
        $this->config = self::PARTIES[$party] ?? throw new InvalidArgumentException('طرف محفظة غير معروف: '.$party);
    }

    public static function for(string $party): self
    {
        return new self($party);
    }

    /** @return array<string, string> */
    public function typeLabels(): array
    {
        return self::TYPE_LABELS[$this->party];
    }

    /**
     * كلُّ ما يعرضه المحتوى المشترك (`reports.wallets.content`) — التقريران
     * لا يختلفان إلّا في الأطراف وتسمياتها، وهذه يمرّرها المتحكّم.
     *
     * @param  array<int, int>  $partyIds  الأطراف بعد فلاتر الشاشة ونطاق المستخدم
     * @param  array<int, array<string, mixed>>  $parties  اسمُ كلّ طرفٍ وكودُه وروابطُه
     * @param  array<string, mixed>  $input  `$request->only(self::SCREEN_INPUTS)`
     */
    public function screen(array $partyIds, array $parties, string $from, string $to, array $input): array
    {
        $view = ($input['view'] ?? null) === 'movements' ? 'movements' : 'balances';
        $type = (string) ($input['type'] ?? '');
        $filters = [
            'type' => array_key_exists($type, self::TYPE_SIGNS) ? $type : null,
            'treasury' => ! empty($input['treasury']) ? (int) $input['treasury'] : null,
        ];

        $rows = [];
        $movements = null;
        $exportMovements = collect();

        if ($view === 'balances') {
            $balance = (string) ($input['balance'] ?? '');
            $showIdle = filter_var($input['show_zero'] ?? false, FILTER_VALIDATE_BOOLEAN);

            foreach ($this->balanceRows($partyIds, $from, $to) as $id => $row) {
                // محفظةٌ بلا رصيدٍ ولا حركةٍ في المدى — تُخفى ما لم تُطلب
                $idle = abs($row['opening']) < 0.005 && abs($row['closing']) < 0.005
                    && $row['count'] === 0 && abs($row['other']) < 0.005;

                if (($idle && ! $showIdle)
                    || ($balance === 'nonzero' && abs($row['closing']) < 0.005)
                    || ($balance === 'zero' && abs($row['closing']) >= 0.005)) {
                    continue;
                }

                $rows[] = $row + ($parties[$id] ?? []);
            }

            usort($rows, fn ($a, $b) => $b['closing'] <=> $a['closing']);
        } else {
            $movements = $this->movementsQuery($partyIds, $from, $to, $filters)
                ->paginate(self::PER_PAGE)
                ->withQueryString();
            $exportMovements = $this->movementsQuery($partyIds, $from, $to, $filters)
                ->limit(self::EXPORT_LIMIT)
                ->get();
        }

        $sum = fn (string $key) => round(array_sum(array_column($rows, $key)), 2);

        return [
            'walletView' => $view,
            'walletFilters' => $filters,
            'rows' => $rows,
            'totals' => [
                'wallets' => count($rows),
                'holding' => count(array_filter($rows, fn ($r) => $r['closing'] >= 0.005)),
                'opening' => $sum('opening'),
                'deposit' => $sum('deposit'),
                'withdraw' => $sum('withdraw'),
                'invoice_paid' => $sum('invoice_paid'),
                'refund' => $sum('refund'),
                'other' => $sum('other'),
                'closing' => $sum('closing'),
            ],
            'movements' => $movements,
            'exportMovements' => $exportMovements,
            'exportLimit' => self::EXPORT_LIMIT,
            // بلاطاتُ عرض الحركات تتبع فلترَيه؛ وفي عرض الأرصدة مجاميعُ المدى كلِّه
            'typeTotals' => $this->movementTypeTotals($partyIds, $from, $to, $view === 'movements' ? $filters : []),
            'typeLabels' => $this->typeLabels(),
            'treasuries' => $this->treasuryOptions(),
            'parties' => $parties,
        ];
    }

    /**
     * كلُّ طرفٍ له محفظة — حسابٌ أو حركة. يُقصر عليه استعلامُ الأطراف قبل
     * فلاترها، فلا يُحمَّل ٣٨٥٢ عميلاً لتقريرٍ عن عشرين محفظة.
     *
     * @return array<int, int>
     */
    public function partiesWithWallet(): array
    {
        $party = $this->config['party'];

        return Account::where('type_accont', $this->config['wallet'])
            ->whereNotNull($party)
            ->pluck($party)
            ->merge(DB::table($this->config['table'])->distinct()->pluck($party))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * صفوفُ الأرصدة: طرفٌ لكل صفّ، بمعادلة المدى كاملة.
     *
     * @param  array<int, int>  $partyIds  الأطراف بعد فلاتر الشاشة ونطاقها
     * @return array<int, array<string, mixed>> `[party_id => row]`
     */
    public function balanceRows(array $partyIds, string $from, string $to): array
    {
        if ($partyIds === []) {
            return [];
        }

        $party = $this->config['party'];
        $accounts = Account::where('type_accont', $this->config['wallet'])
            ->whereIn($party, $partyIds)
            ->pluck('id', $party)
            ->map(fn ($id) => (int) $id)
            ->all();

        $accountIds = array_values($accounts);
        $dayBefore = Carbon::parse($from)->subDay()->toDateString();
        $opening = $this->balancesAt($accountIds, $dayBefore);
        $closing = $this->balancesAt($accountIds, $to);
        $movements = $this->movementTotals($partyIds, $from, $to);

        $rows = [];
        foreach ($partyIds as $id) {
            $accountId = $accounts[$id] ?? null;
            $m = $movements[$id] ?? [];

            $row = [
                'party_id' => $id,
                'account_id' => $accountId,
                'opening' => round($accountId ? ($opening[$accountId] ?? 0.0) : 0.0, 2),
                'deposit' => round($m['deposit'] ?? 0.0, 2),
                'withdraw' => round($m['withdraw'] ?? 0.0, 2),
                'invoice_paid' => round($m['invoice_paid'] ?? 0.0, 2),
                'refund' => round($m['refund'] ?? 0.0, 2),
                'count' => (int) ($m['count'] ?? 0),
                'last_movement' => $m['last'] ?? null,
                'closing' => round($accountId ? ($closing[$accountId] ?? 0.0) : 0.0, 2),
            ];

            $explained = $row['deposit'] + $row['refund'] - $row['withdraw'] - $row['invoice_paid'];
            $row['other'] = round($row['closing'] - $row['opening'] - $explained, 2);

            $rows[$id] = $row;
        }

        return $rows;
    }

    /**
     * أرصدةُ حسابات المحافظ حتى تاريخٍ شامل، بإشارة المحفظة (موجبٌ = ما فيها).
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, float>
     */
    public function balancesAt(array $accountIds, string $cutoff): array
    {
        if ($accountIds === []) {
            return [];
        }

        $sign = $this->config['liability'] ? 'SUM(d.credit) - SUM(d.debit)' : 'SUM(d.debit) - SUM(d.credit)';

        return DB::table('journal_entry_details as d')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
            ->where('e.status', 1)
            ->whereIn('d.account_id', $accountIds)
            ->where('e.date', '<=', $cutoff)
            ->groupBy('d.account_id')
            ->selectRaw('d.account_id, '.$sign.' as balance')
            ->pluck('balance', 'account_id')
            ->map(fn ($b) => (float) $b)
            ->all();
    }

    /**
     * مجاميعُ حركات المدى لكل طرف بنوعها، وعددُها، وآخرُ يومٍ فيها.
     *
     * @param  array<int, int>  $partyIds
     * @return array<int, array<string, mixed>>
     */
    public function movementTotals(array $partyIds, string $from, string $to): array
    {
        $party = $this->config['party'];
        $out = [];

        $rows = $this->baseQuery($partyIds, $from, $to)
            ->groupBy('t.'.$party, 't.type')
            ->selectRaw('t.'.$party.' as party_id, t.type, SUM(t.amount) as total, COUNT(*) as n, MAX('.self::DAY.') as last_day')
            ->get();

        foreach ($rows as $row) {
            $id = (int) $row->party_id;
            $out[$id] ??= ['count' => 0, 'last' => null];
            $out[$id][$row->type] = (float) $row->total;
            $out[$id]['count'] += (int) $row->n;

            if ($row->last_day && (! $out[$id]['last'] || $row->last_day > $out[$id]['last'])) {
                $out[$id]['last'] = substr((string) $row->last_day, 0, 10);
            }
        }

        return $out;
    }

    /**
     * سجلُّ الحركات — استعلامٌ جاهزٌ للترقيم، الأحدثُ أولاً.
     *
     * @param  array<int, int>  $partyIds
     * @param  array{type?: ?string, treasury?: ?int}  $filters
     */
    public function movementsQuery(array $partyIds, string $from, string $to, array $filters = []): Builder
    {
        $invoice = $this->config['invoice'];

        return $this->baseQuery($partyIds, $from, $to)
            ->leftJoin($this->config['invoice_table'].' as inv', 'inv.id', '=', 't.'.$invoice)
            ->leftJoin('accounts as tr', 'tr.id', '=', 't.treasury_account_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->when(! empty($filters['type']), fn ($q) => $q->where('t.type', $filters['type']))
            ->when(! empty($filters['treasury']), fn ($q) => $q->where('t.treasury_account_id', (int) $filters['treasury']))
            ->select([
                't.id', 't.'.$this->config['party'].' as party_id', 't.type', 't.amount', 't.balance_after',
                't.reference', 't.note', 't.created_at', 't.journal_entry_id', 't.'.$invoice.' as invoice_id',
                'inv.code as invoice_code', 'tr.name as treasury_name', 'u.name as user_name',
            ])
            ->selectRaw(self::DAY.' as day')
            ->orderByDesc('day')
            ->orderByDesc('t.id');
    }

    /**
     * مجاميعُ السجلّ بأنواعه على الفلاتر نفسها — لبلاطات الشاشة وتذييلها.
     *
     * @param  array<int, int>  $partyIds
     * @return array<string, array{total: float, count: int}>
     */
    public function movementTypeTotals(array $partyIds, string $from, string $to, array $filters = []): array
    {
        $rows = $this->baseQuery($partyIds, $from, $to)
            ->when(! empty($filters['type']), fn ($q) => $q->where('t.type', $filters['type']))
            ->when(! empty($filters['treasury']), fn ($q) => $q->where('t.treasury_account_id', (int) $filters['treasury']))
            ->groupBy('t.type')
            ->selectRaw('t.type, SUM(t.amount) as total, COUNT(*) as n')
            ->get();

        $out = [];
        foreach (array_keys(self::TYPE_SIGNS) as $type) {
            $out[$type] = ['total' => 0.0, 'count' => 0];
        }
        foreach ($rows as $row) {
            $out[$row->type] = ['total' => round((float) $row->total, 2), 'count' => (int) $row->n];
        }

        return $out;
    }

    /** الخزائنُ التي مرّت بها حركاتُ المحافظ — قائمةُ فلتر «الخزينة» */
    public function treasuryOptions(): array
    {
        return DB::table($this->config['table'].' as t')
            ->join('accounts as a', 'a.id', '=', 't.treasury_account_id')
            ->distinct()
            ->orderBy('a.name')
            ->pluck('a.name', 'a.id')
            ->all();
    }

    /** @param  array<int, int>  $partyIds */
    private function baseQuery(array $partyIds, string $from, string $to): Builder
    {
        return DB::table($this->config['table'].' as t')
            ->leftJoin('journal_entries as e', 'e.id', '=', 't.journal_entry_id')
            ->whereIn('t.'.$this->config['party'], $partyIds ?: [0])
            ->whereRaw(self::DAY.' BETWEEN ? AND ?', [$from, $to]);
    }
}
