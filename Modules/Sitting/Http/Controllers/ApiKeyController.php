<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Sitting\Http\Requests\ApiKeyRequest;

/**
 * مفاتيح الربط البرمجي.
 *
 * كان هذا المتحكّم الوحيد في قسم الإعدادات **بلا أي صلاحية** — لا في
 * المتحكّم ولا على مساراته. جُرِّب فعلياً: حساب «مندوب مبيعات» أنشأ مفتاحاً
 * ناجحاً. والمفارقة أن الصلاحيات `api_keys.*` كانت مزروعة في قاعدة البيانات
 * منذ البداية ولم يُفرَض أيٌّ منها.
 *
 * المفتاح يفتح بيانات النظام لطرف خارج المتصفّح، فصلاحيته أقرب إلى تنزيل
 * نسخة احتياطية منها إلى تعديل إعداد.
 */
class ApiKeyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:api_keys.view')->only('index');
        $this->middleware('permission:api_keys.create')->only('store');
        $this->middleware('permission:api_keys.delete')->only('destroy');
        // التعطيل والتفعيل إبطالٌ للمفتاح لا تعديلٌ له
        $this->middleware('permission:api_keys.revoke')->only('toggle');
    }

    public function index()
    {
        $keys = ApiKey::getUserKeys(Auth::id());

        return view('sitting::apiKeys.index', [
            'apiKeys' => $keys,
            'abilities' => ApiKey::abilities(),
            'stats' => [
                'total' => $keys->count(),
                'active' => $keys->filter(fn (ApiKey $key) => $key->isValid())->count(),
                'expired' => $keys->filter(
                    fn (ApiKey $key) => $key->expires_at && $key->expires_at->isPast()
                )->count(),
                'calls' => (int) $keys->sum('usage_count'),
            ],
        ]);
    }

    /**
     * إنشاء مفتاح.
     *
     * الرمز السري يُعرض مرة واحدة في الاستجابة ولا يُخزَّن إلا مُجزَّأً —
     * فلا سبيل إلى استرجاعه لاحقاً، وهذا مقصود.
     */
    public function store(ApiKeyRequest $request)
    {
        try {
            $apiKey = ApiKey::generate(
                Auth::id(),
                $request->validated('name'),
                $request->validated('abilities'),
                $request->validated('expires_at')
            );
        } catch (\Throwable $e) {
            Log::error('تعذّر إنشاء مفتاح ربط', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('settings.api.create_failed', ['error' => $e->getMessage()]),
            ], 500);
        }

        Log::info('أُنشئ مفتاح ربط', [
            'api_key_id' => $apiKey->id,
            'user_id' => Auth::id(),
            'abilities' => $apiKey->abilities,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('settings.api.created_msg'),
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $apiKey->key,
                'secret' => $apiKey->raw_secret,
                'abilities' => $apiKey->abilityLabels(),
                'expires_at' => $apiKey->expires_at?->format('Y-m-d'),
                'created_at' => $apiKey->created_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function destroy($id)
    {
        $apiKey = ApiKey::where('user_id', Auth::id())->find($id);

        if (! $apiKey) {
            return response()->json(['success' => false, 'message' => __('settings.api.not_found')], 404);
        }

        $name = $apiKey->name;
        $apiKey->delete();

        Log::warning('حُذف مفتاح ربط', ['name' => $name, 'user_id' => Auth::id()]);

        return response()->json([
            'success' => true,
            'message' => __('settings.api.deleted', ['name' => $name]),
        ]);
    }

    public function toggle($id)
    {
        $apiKey = ApiKey::where('user_id', Auth::id())->find($id);

        if (! $apiKey) {
            return response()->json(['success' => false, 'message' => __('settings.api.not_found')], 404);
        }

        $apiKey->is_active = ! $apiKey->is_active;
        $apiKey->save();

        return response()->json([
            'success' => true,
            'message' => $apiKey->is_active
                ? __('settings.api.enabled', ['name' => $apiKey->name])
                : __('settings.api.disabled', ['name' => $apiKey->name]),
            'is_active' => $apiKey->is_active,
        ]);
    }
}
