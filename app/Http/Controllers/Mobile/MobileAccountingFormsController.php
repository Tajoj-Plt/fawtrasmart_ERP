<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ChequeBook;
use App\Models\CostCenter;
use App\Models\PayableCheque;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Account\Http\Controllers\AccountsChartController;
use Modules\Account\Http\Controllers\Cheques\CheckBooksController;
use Modules\Account\Http\Controllers\Cheques\PayableChequesController;
use Modules\Account\Http\Controllers\Cheques\ReceivedChequesController;
use Modules\Account\Http\Controllers\CostCentersController;
use Modules\Account\Http\Controllers\Finance\ExpensesController;
use Modules\Account\Http\Controllers\Finance\IncomesController;
use Modules\Account\Http\Controllers\Finance\TreasuryController;
use Modules\Account\Http\Controllers\JournalEntryController;

/**
 * قوائم الاختيار لنماذج الإنشاء في التطبيق.
 *
 * تُبنى من **بيانات شاشة الإنشاء في الويب نفسها** (`create()` تُعيد View يحمل المتغيّرات التي
 * تُرسم منها القوائم) — فما يراه المستخدم في الجوال هو ما يراه في الويب بالضبط، بنطاقه وصلاحياته،
 * دون إعادة كتابة منطق التقييد.
 *
 * كل بانٍ يُعيد: `options` (قوائم `{id,label}` بمفتاح الحقل)، و`defaults`، و`flags`.
 */
class MobileAccountingFormsController extends Controller
{
    /** أقصى عدد خيارات في الرد الواحد — البحث (`?field=&q=`) يضيّق القائمة */
    private const LIMIT = 60;

    public function options(Request $request, string $form): JsonResponse
    {
        $builders = [
            'expenses' => fn () => $this->expenses($request),
            'incomes' => fn () => $this->incomes($request),
            'journal' => fn () => $this->journal($request),
            'treasuries' => fn () => $this->treasuries($request),
            'chart' => fn () => $this->chart($request),
            'cost_centers' => fn () => $this->costCenters($request),
            'cheque_books' => fn () => $this->chequeBooks($request),
            'payable_cheques' => fn () => $this->payableCheques($request),
            'received_cheques' => fn () => $this->receivedCheques($request),
        ];

        abort_unless(isset($builders[$form]), 404, 'نموذج غير معروف.');

        $lists = $builders[$form]();
        $q = trim((string) $request->query('q', ''));
        $field = (string) $request->query('field', '');

        if ($field !== '') {
            abort_unless(isset($lists['options'][$field]), 404, 'حقل غير معروف.');

            return response()->json(['success' => true, 'data' => $this->filter($lists['options'][$field], $q)]);
        }

        // الرد الأول: كل القوائم، والكبيرة مقصوصة (تُبحث لاحقاً بـ ?field=&q=)
        $lists['options'] = collect($lists['options'])->map(fn ($items) => $this->filter($items, ''))->all();

        return response()->json(['success' => true, 'data' => $lists]);
    }

    /** الكود التالي المقترح لحسابٍ أو مركز تكلفة تحت أب معيَّن — نفس ما تقترحه شاشة الويب */
    public function nextCode(Request $request, string $kind): JsonResponse
    {
        $parentId = $request->integer('parent_id') ?: null;

        if ($kind === 'cost_center') {
            abort_unless($parentId, 422, 'اختر المركز الأب.');
            $res = app(CostCentersController::class)->getNextCode(CostCenter::findOrFail($parentId));
        } elseif ($kind === 'chart') {
            $parent = $parentId ? \App\Models\Account::find($parentId) : null;
            $res = app(AccountsChartController::class)->getNextCode($request, $parent);
        } else {
            abort(404, 'نوع غير معروف.');
        }

        $data = $res instanceof JsonResponse ? $res->getData(true) : [];

        return response()->json(['success' => true, 'data' => ['code' => $data['nextCode'] ?? $data['next_code'] ?? $data['code'] ?? null]]);
    }

    /** بيانات دفتر شيكات وأول رقم شيك متاح فيه */
    public function checkbookInfo(int $id): JsonResponse
    {
        $book = ChequeBook::with('bank')->findOrFail($id);
        $last = PayableCheque::where('cheque_book_id', $book->id)->max('cheque_number');
        $next = $last ? ((int) $last) + 1 : (int) $book->start_serial_number;

        return response()->json(['success' => true, 'data' => [
            'currency' => $book->currency,
            'start' => (int) $book->start_serial_number,
            'end' => (int) $book->end_serial_number,
            'next_number' => $next <= (int) $book->end_serial_number ? $next : null,
        ]]);
    }

    /* ─────────────────────────── سندات الصرف ─────────────────────────── */

    private function expenses(Request $request): array
    {
        $controller = app(ExpensesController::class);
        $data = $this->viewData($controller->create($request));
        $user = $request->user();

        return [
            'options' => [
                'expenses_category_id' => $this->toOptions($data['expenses_categories'] ?? []),
                'treasury_id' => $this->toOptions($data['treasuries'] ?? []),
                'expense_account_id' => $this->toOptions($data['expenseLeafAccounts'] ?? []),
                'client_account_id' => $this->toOptions($data['accounts'] ?? []),
                'supplier_id' => $this->toOptions($data['suppliers'] ?? [], 'trade_name'),
            ],
            'defaults' => [
                'treasury_id' => $data['treasury_id'] ?? null,
                'client_default_category_id' => $data['clientDefaultCategoryId'] ?? null,
            ],
            'flags' => [
                'can_expense_to_supplier' => ! $user->isEmployee() || $controller->canExpenseToSupplier($user),
            ],
        ];
    }

    /* ─────────────────────────── سندات القبض ─────────────────────────── */

    private function incomes(Request $request): array
    {
        $data = $this->viewData(app(IncomesController::class)->create($request));

        return [
            'options' => [
                'incomes_category_id' => $this->toOptions($data['incomes_categories'] ?? []),
                'treasury_id' => $this->toOptions($data['treasuries'] ?? []),
                'client_account_id' => $this->toOptions($data['accounts'] ?? []),
                'other_account_id' => $this->toOptions($data['otherAccounts'] ?? []),
            ],
            'defaults' => ['treasury_id' => $data['treasury_id'] ?? null],
            'flags' => [],
        ];
    }

    /* ─────────────────────────── قيود اليومية ─────────────────────────── */

    private function journal(Request $request): array
    {
        $data = $this->viewData(app(JournalEntryController::class)->create());

        // حسابات القيد: بحثٌ على الخادم (شجرةٌ كبيرة) بنفس منطق `searchAccounts` في الويب — طرفية فقط
        $sub = Request::create('/x', 'GET', ['q' => (string) $request->query('q', '')]);
        $sub->setUserResolver(fn () => $request->user());
        $found = app(JournalEntryController::class)->searchAccounts($sub);
        $rows = $found instanceof JsonResponse ? ($found->getData(true)['data'] ?? $found->getData(true)) : [];

        return [
            'options' => [
                'account_id' => $this->toOptions(is_array($rows) ? $rows : []),
                'cost_center_id' => $this->toOptions($data['costCenters'] ?? []),
            ],
            // «قيد يومية» — نوع المرجع اليدوي في الويب
            'defaults' => ['reference_type' => 'JV'],
            'flags' => [],
        ];
    }

    /* ─────────────────────────── الخزائن ─────────────────────────── */

    private function treasuries(Request $request): array
    {
        $data = $this->viewData(app(TreasuryController::class)->create());

        return [
            'options' => [
                'employee_id' => $this->toOptions($data['employees'] ?? [], 'full_name'),
                'role_id' => $this->toOptions($data['roles'] ?? []),
                'branch_id' => $this->toOptions($data['branches'] ?? []),
            ],
            'defaults' => [],
            'flags' => [],
        ];
    }

    /* ─────────────────────────── شجرة الحسابات ─────────────────────────── */

    private function chart(Request $request): array
    {
        $found = app(AccountsChartController::class)->getParents(Request::create('/x', 'GET'));
        $rows = $found instanceof JsonResponse ? $found->getData(true) : [];
        $rows = isset($rows['data']) && is_array($rows['data']) ? $rows['data'] : (is_array($rows) ? $rows : []);

        $options = collect($rows)->map(fn ($a) => [
            'id' => (int) $a['id'],
            'label' => trim((($a['code'] ?? '') !== '' ? $a['code'].' — ' : '').$a['name']),
        ])->all();

        return ['options' => ['parent_id' => $options], 'defaults' => [], 'flags' => []];
    }

    /* ─────────────────────────── مراكز التكلفة ─────────────────────────── */

    private function costCenters(Request $request): array
    {
        return [
            'options' => ['parent_id' => $this->toOptions(CostCenter::orderBy('code')->get())],
            'defaults' => [],
            'flags' => [],
        ];
    }

    /* ─────────────────────────── دفاتر الشيكات ─────────────────────────── */

    private function chequeBooks(Request $request): array
    {
        $data = $this->viewData(app(CheckBooksController::class)->create());

        return [
            'options' => ['bank_id' => $this->toOptions($data['bank_accounts'] ?? [])],
            'defaults' => ['cheque_book_number' => $data['cheque_book_number'] ?? null, 'currency' => 'SAR', 'status' => 1],
            'flags' => [],
        ];
    }

    /* ─────────────────────────── الشيكات الصادرة ─────────────────────────── */

    private function payableCheques(Request $request): array
    {
        $data = $this->viewData(app(PayableChequesController::class)->create());

        $books = collect($data['check_books'] ?? [])->map(fn ($b) => [
            'id' => (int) $b->id,
            'label' => trim(($b->cheque_book_number ?? '').' ('.$b->start_serial_number.'–'.$b->end_serial_number.')'),
        ])->all();

        return [
            'options' => [
                'cheque_book_id' => $books,
                'recipient_account_id' => $this->toOptions($data['accounts'] ?? []),
                'supplier_id' => $this->toOptions(Supplier::all(), 'trade_name'),
            ],
            'defaults' => [],
            'flags' => [],
        ];
    }

    /* ─────────────────────────── الشيكات المستلمة ─────────────────────────── */

    private function receivedCheques(Request $request): array
    {
        $data = $this->viewData(app(ReceivedChequesController::class)->create());

        return [
            'options' => [
                'recipient_account_id' => $this->toOptions($data['client_accounts'] ?? []),
                'bank_account_id' => $this->toOptions($data['bank_accounts'] ?? []),
            ],
            'defaults' => [],
            'flags' => [],
        ];
    }

    /* ─────────────────────────── أدوات ─────────────────────────── */

    /** متغيّرات شاشة الإنشاء (`view(...)` لا يُرسَم هنا، فقط تُقرأ بياناته) */
    private function viewData(mixed $view): array
    {
        abort_unless($view instanceof View, 422, 'تعذّر تحميل بيانات النموذج.');

        return $view->getData();
    }

    /**
     * سجلات Eloquent/مصفوفات ← خيارات موحَّدة `{id, label}`.
     *
     * @param  iterable<mixed>  $items
     */
    private function toOptions(iterable $items, string $nameColumn = 'name'): array
    {
        return Collection::make($items)->map(function ($row) use ($nameColumn) {
            $get = fn (string $k) => is_array($row) ? ($row[$k] ?? null) : ($row->{$k} ?? null);
            $name = $get($nameColumn) ?? $get('name') ?? $get('trade_name') ?? $get('first_name');
            $code = $get('code');

            return [
                'id' => (int) $get('id'),
                'label' => trim(($code !== null && $code !== '' && $code !== 0 && $code !== '0' ? $code.' — ' : '').$name),
            ];
        })->values()->all();
    }

    private function filter(array $items, string $q): array
    {
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $items = array_values(array_filter($items, fn ($o) => str_contains(mb_strtolower($o['label']), $needle)));
        }

        return array_slice($items, 0, self::LIMIT);
    }
}
