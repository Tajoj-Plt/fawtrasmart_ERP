<?php

namespace Modules\Integration\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * شكلٌ واحد لردود واجهة الجوال: `success` دائماً، و`message` حين تُقال
 * جملة، و`data` للحمولة، و`meta` للترقيم.
 */
trait RespondsJson
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json(array_filter([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], fn ($v) => $v !== null) + $extra, $status);
    }

    protected function fail(string $message, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message] + $extra, $status);
    }

    protected function paginated(LengthAwarePaginator $page, array $items, array $extra = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ] + $extra);
    }

    /** حجم الصفحة من الطلب محصوراً بين 1 و100. */
    protected function perPage(int $default = 20): int
    {
        return max(1, min(100, (int) request()->integer('per_page', $default) ?: $default));
    }
}
