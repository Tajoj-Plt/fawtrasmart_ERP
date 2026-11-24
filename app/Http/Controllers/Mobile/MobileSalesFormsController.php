<?php

namespace App\Http\Controllers\Mobile;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\OrderSource;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Support\DataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Sales\Support\SalesWarehouse;
use App\Models\settingsOrderSource;

/**
 * قوائم الاختيار لنماذج الإنشاء في وحدة المبيعات. لا شاشة `create()` واحدةً تناسب جوّالاً هنا —
 * الفاتورة والعرض يحتاجان قوائم مستقلّة (عميل بالبحث، مستودعٌ، منتجٌ بالبحث)، والسند يحتاج فاتورةً
 * بالبحث فقط — فتُبنى هذه القوائم مباشرةً بنفس نطاق الرؤية (`DataScope`) الذي تستعمله شاشات الويب.
 */
class MobileSalesFormsController extends Controller
{
    private const LIMIT = 60;

    public function options(Request $request, string $form): JsonResponse
    {
        $builders = [
            'payments' => fn () => $this->payments($request),
            'invoices' => fn () => $this->invoices($request),
            'quotes' => fn () => $this->quotes($request),
        ];

        abort_unless(isset($builders[$form]), 404, 'نموذج غير معروف.');

        $lists = $builders[$form]();
        $q = trim((string) $request->query('q', ''));
        $field = (string) $request->query('field', '');

        if ($field !== '') {
            abort_unless(array_key_exists($field, $lists['options']), 404, 'حقل غير معروف.');

            if ($field === 'client_id' && $q !== '') {
                return response()->json(['success' => true, 'data' => $this->searchClients($q)]);
            }
            if ($field === 'product_id' && $q !== '') {
                return response()->json(['success' => true, 'data' => $this->searchProducts($q)]);
            }
            if ($field === 'invoice_id' && $q !== '') {
                return response()->json(['success' => true, 'data' => $this->searchInvoices($request, $q)]);
            }

            return response()->json(['success' => true, 'data' => $this->filter($lists['options'][$field], $q)]);
        }

        $lists['options'] = collect($lists['options'])->map(fn ($items) => $this->filter($items, ''))->all();

        return response()->json(['success' => true, 'data' => $lists]);
    }

    /* ─────────────────────────── سند قبضٍ جديد ─────────────────────────── */

    private function payments(Request $request): array
    {
        return [
            'options' => [
                'invoice_id' => $this->searchInvoices($request, ''),
                'payment_type' => $this->toOptions(PaymentMethod::query()->when(method_exists(PaymentMethod::class, 'scopeActive'), fn ($q) => $q->active())->get()),
            ],
            'defaults' => [],
            'flags' => [],
        ];
    }

    private function searchInvoices(Request $request, string $q): array
    {
        $query = Invoice::with('client')->where('type', '!=', 'returned')->where('payment_status', '!=', 3);

        if (! ($request->user() && $request->user()->hasRolePermission('data_scope_all'))) {
            $userIds = DataScope::documentUserIds();
            $employeeIds = DataScope::documentEmployeeIds();
            $query->where(function ($w) use ($userIds, $employeeIds) {
                $any = false;
                if ($userIds !== null) {
                    $w->whereIn('created_by', $userIds ?: [0]);
                    $any = true;
                }
                if ($employeeIds !== null) {
                    $any ? $w->orWhereIn('employee_id', $employeeIds ?: [0]) : $w->whereIn('employee_id', $employeeIds ?: [0]);
                }
            });
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")->orWhereHas('client', fn ($c) => $c->where('trade_name', 'like', "%{$q}%"));
            });
        }

        return $query->orderByDesc('id')->limit(self::LIMIT)->get()->map(fn (Invoice $i) => [
            'id' => $i->id,
            'label' => trim(($i->code ? '#'.$i->code : '#'.$i->id).' — '.($i->client->trade_name ?? '')),
        ])->all();
    }

    /* ─────────────────────────── فاتورة مبيعاتٍ جديدة ─────────────────────────── */

    private function invoices(Request $request): array
    {
        $storeHouses = SalesWarehouse::optionsFor($request->user());

        return [
            'options' => [
                'client_id' => $this->searchClients(''),
                'store_house_id' => $this->toOptions($storeHouses),
                'product_id' => $this->searchProducts(''),
                'order_source_id' => $this->toOptions(OrderSource::all()),
            ],
            'defaults' => [
                'order_source_id' => settingsOrderSource::get('default_order_source_id'),
            ],
            'flags' => [
                'orderSourceRequired' => (string) settingsOrderSource::get('order_source_mandatory', '0') === '1',
            ],
        ];
    }

    /* ─────────────────────────── عرض سعرٍ جديد ─────────────────────────── */

    private function quotes(Request $request): array
    {
        return [
            'options' => [
                'client_id' => $this->searchClients(''),
                'product_id' => $this->searchProducts(''),
            ],
            'defaults' => [],
            'flags' => [],
        ];
    }

    /* ─────────────────────────── بحوثٌ مشتركة ─────────────────────────── */

    private function searchClients(string $q): array
    {
        $query = Client::query();
        if ($q !== '') $query->where('trade_name', 'like', "%{$q}%");

        return $this->toOptions($query->orderBy('trade_name')->limit(self::LIMIT)->get(), 'trade_name');
    }

    private function searchProducts(string $q): array
    {
        $query = Product::whereIn('type', ProductType::stockedValues())->where('status', 'active');
        if ($q !== '') $query->where('name', 'like', "%{$q}%");

        return $this->toOptions($query->limit(self::LIMIT)->get());
    }

    /* ─────────────────────────── أدوات ─────────────────────────── */

    private function toOptions(iterable $items, string $nameColumn = 'name'): array
    {
        return Collection::make($items)->map(function ($row) use ($nameColumn) {
            $get = fn (string $k) => is_array($row) ? ($row[$k] ?? null) : ($row->{$k} ?? null);
            $name = $get($nameColumn) ?? $get('name') ?? $get('trade_name');
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
