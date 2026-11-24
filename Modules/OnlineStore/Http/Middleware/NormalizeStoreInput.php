<?php

namespace Modules\OnlineStore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * تطبيعُ مدخلات واجهة المتجر العامة قبل وصولها إلى المتحكّمات.
 *
 * المتحكّمات كتبت على أنّ `per_page` رقمٌ، و`search` نصٌّ، و`sort_order` إمّا
 * asc أو desc. زائرٌ يُرسل `per_page=-1` أو `page=abc` أو `search[]=x` أو
 * `sort_order=zz` يُسقطها بـ500 (OFFSET سالب، «Array to string conversion»،
 * InvalidArgumentException). بدل تحصين كل متحكّمٍ على حدة، تُنقَّى المفاتيح
 * المعروفة هنا مرّةً واحدة: القيمة غير الصالحة تُحذف فيرجع المتحكّم إلى
 * افتراضيّه، لا يُرفض الطلب.
 *
 * يعمل على الاستعلام (query) وعلى جسم الطلب (JSON أو نموذج) معاً.
 */
class NormalizeStoreInput
{
    /** مفاتيح نصّية/رقمية مفردة — المصفوفة فيها ليست قيمة صالحة. */
    private const SCALAR_KEYS = [
        'search', 'q', 'query', 'keyword', 'sort', 'sort_by', 'sort_order', 'order_by',
        'min_price', 'max_price', 'status', 'type', 'order_amount', 'in_stock', 'featured',
        'on_sale', 'lang', 'locale', 'with_count', 'root_only', 'depth', 'rating', 'code',
        'phone', 'email', 'country', 'city', 'region',
    ];

    /** معرّفاتٌ مفردة: لو جاءت مصفوفةً أُخذ أول عنصرٍ مفرد منها. */
    private const ID_KEYS = ['category_id', 'brand_id', 'product_id', 'variant_id', 'address_id', 'shipping_method_id', 'payment_method_id', 'saved_card_id'];

    public function handle(Request $request, Closure $next)
    {
        $request->query->replace($this->clean($request->query->all()));

        if ($request->isJson()) {
            $json = $request->json();
            $json->replace($this->clean($json->all()));
        } elseif ($request->request->count()) {
            $request->request->replace($this->clean($request->request->all()));
        }

        return $next($request);
    }

    /** @param  array<string, mixed>  $data */
    private function clean(array $data): array
    {
        foreach (self::SCALAR_KEYS as $key) {
            if (array_key_exists($key, $data) && is_array($data[$key])) {
                unset($data[$key]);
            }
        }

        foreach (self::ID_KEYS as $key) {
            if (array_key_exists($key, $data) && is_array($data[$key])) {
                $first = collect($data[$key])->first(fn ($v) => is_scalar($v));
                if ($first === null) {
                    unset($data[$key]);
                } else {
                    $data[$key] = $first;
                }
            }
        }

        // الصفحة وحجمها وحدّ العدد: أعدادٌ صحيحة موجبة بسقف
        foreach (['per_page' => 100, 'limit' => 100, 'page' => 100000] as $key => $max) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            if (! is_scalar($data[$key]) || ! is_numeric($data[$key]) || (float) $data[$key] < 1) {
                unset($data[$key]);

                continue;
            }

            $data[$key] = (int) min((float) $data[$key], $max);
        }

        // الترتيب: اتجاهٌ صحيح، وعمودٌ بصيغة اسمٍ آمنة (القائمة البيضاء في المتحكّم)
        if (array_key_exists('sort_order', $data) && ! in_array(strtolower((string) $data['sort_order']), ['asc', 'desc'], true)) {
            unset($data['sort_order']);
        } elseif (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = strtolower((string) $data['sort_order']);
        }

        if (array_key_exists('sort_by', $data) && ! preg_match('/^[A-Za-z_]{1,40}$/', (string) $data['sort_by'])) {
            unset($data['sort_by']);
        }

        // نصوص البحث: سقفٌ معقول لطولها
        foreach (['search', 'q', 'query', 'keyword'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = mb_substr($data[$key], 0, 200);
            }
        }

        return $data;
    }
}
