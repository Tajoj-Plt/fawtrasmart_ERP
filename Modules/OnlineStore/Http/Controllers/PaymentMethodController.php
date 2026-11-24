<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Enums\PaymentMethod as PaymentMethodEnum;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\OnlineStore\Http\Controllers\Concerns\SanitizesFilters;

/**
 * عرض طرق الدفع المرتبطة بطلبات المتجر — للاطّلاع فقط.
 *
 * كانت هذه الشاشة تُنشئ/تُعدّل/تحذف على نفس جدول `payment_methods` الذي
 * تديره `Modules\Sitting\Http\Controllers\PaymentMethodsController`، بحقول
 * متضاربة (`is_active` هنا مقابل `status` هناك) — فيكتب كنترولران مختلفان
 * على نفس السجل بمنطقين مختلفين. صارت الإدارة (إنشاء/تعديل/حذف/تفعيل/
 * تعيين افتراضي) حصراً من شاشة الإعدادات، وهذه الشاشة للعرض المرتبط
 * بسياق المتجر الإلكتروني وحده.
 */
class PaymentMethodController extends Controller
{
    use SanitizesFilters;

    public function __construct()
    {
        $this->middleware('permission.role:online_store.payment_methods.view');

        // فلاتر الرابط نصوصٌ لا مصفوفات — وإلا سقطت القوالب التي تطبعها
        $this->middleware(function ($request, $next) {
            $this->dropNonScalarFilters($request, ['search', 'type', 'status', 'is_online', 'page']);

            return $next($request);
        });
    }

    /**
     * عرض قائمة طرق الدفع
     */
    public function index(Request $request)
    {
        $query = PaymentMethod::query();

        // البحث
        // نصٌّ فقط: `search[]=x` في الرابط كان يُسقط الصفحة (Array to string)
        if (is_string($request->query('search')) && trim($request->query('search')) !== '') {
            $search = mb_substr(trim($request->query('search')), 0, 100);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // فلترة حسب النوع
        if (is_string($request->query('type')) && $request->query('type') !== '') {
            $query->where('type', $request->query('type'));
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // فلترة حسب نوع الاستخدام (أونلاين أو غير ذلك)
        if ($request->filled('is_online')) {
            $query->where('is_online', $request->is_online === 'yes');
        }

        $paymentMethods = $query->ordered()->paginate(15)->withQueryString();
        $types = PaymentMethodEnum::cases();

        return view('onlinestore::payment-methods.index', compact('paymentMethods', 'types'));
    }

    /**
     * عرض تفاصيل طريقة الدفع
     */
    public function show(PaymentMethod $paymentMethod)
    {
        return view('onlinestore::payment-methods.show', compact('paymentMethod'));
    }
}
