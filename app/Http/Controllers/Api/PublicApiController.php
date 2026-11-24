<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الواجهة البرمجية المحمية بمفاتيح الربط.
 *
 * قراءة فقط عن قصد: المفتاح يُعطى لطرف خارجي، وفتح الكتابة قرار يخصّ صاحب
 * النظام. كل نقطة هنا مربوطة بصلاحية يمنحها المفتاح صراحةً.
 */
class PublicApiController extends Controller
{
    /** أقصى عدد سجلات في الصفحة — يمنع سحب الجدول كاملاً بطلب واحد */
    protected const MAX_PER_PAGE = 100;

    /**
     * فحص الاتصال: يخبر العميل أن مفتاحه صالح وما الذي يفتحه.
     */
    public function ping(Request $request): JsonResponse
    {
        $key = $request->attributes->get('api_key');

        return response()->json([
            'success' => true,
            'message' => 'الاتصال يعمل.',
            'key_name' => $key?->name,
            'abilities' => $key?->abilities ?: [],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function clients(Request $request): JsonResponse
    {
        $query = Client::query()->select([
            'id', 'trade_name', 'first_name', 'last_name',
            'email', 'phone', 'mobile', 'city', 'tax_number', 'created_at',
        ]);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('trade_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->latest('id'), $request);
    }

    public function invoices(Request $request): JsonResponse
    {
        $query = Invoice::query()->select([
            'id', 'code', 'client_id', 'created_at',
        ]);

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return $this->paginated($query->latest('id'), $request);
    }

    public function products(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('name', 'like', "%{$search}%");
        }

        return $this->paginated($query->latest('id'), $request);
    }

    /**
     * ملخّص رقمي سريع — أكثر ما يطلبه تكامل خارجي للوحات المتابعة.
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'clients' => Client::count(),
                'invoices' => Invoice::count(),
                'products' => Product::count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * صفحة موحّدة الشكل لكل النقاط.
     */
    protected function paginated($query, Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25) ?: 25, self::MAX_PER_PAGE);

        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $page->items(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
