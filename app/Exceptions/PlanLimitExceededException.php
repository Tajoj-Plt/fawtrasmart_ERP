<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * محاولةُ إنشاءٍ فوق حدّ باقة فوترة — يرميها الحارس الأخير.
 *
 * **ليست عطلاً بل قاعدةَ عمل.** ولهذا هي مستثناةٌ من «سجلّ الأخطاء» في
 * `bootstrap/app.php`: منشأةٌ بلغت سقفها تُنتج واحدةً مع كل محاولة، فتُغرق
 * الشاشةَ بضجيجٍ يُخفي العطل الحقيقي — وهو أوّل ما يجعل سجلّ الأخطاء يُهجَر.
 *
 * وترميها [\App\Observers\PlanLimitObserver] من حدث `creating`، أي **داخل
 * المعاملة**: فترتدّ المعاملة كلّها ولا يبقى نصفُ مستندٍ محفوظاً.
 */
class PlanLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $feature,
        string $message,
        public readonly int $remaining = 0,
    ) {
        parent::__construct($message);
    }

    /**
     * الردّ الذي يراه المستخدم.
     *
     * لا صفحة جدارٍ ولا 500: بلوغُ الحدّ حالةٌ يعالجها المستخدم من مكانه —
     * يحذف ما لم يعد مستعمَلاً أو يشتري إضافة — فيعود إلى شاشته ومعه ما كان
     * يكتبه.
     *
     * ملاحظة: متحكّمٌ يلتقط `\Throwable` حول عملية الحفظ سيبتلع هذه الرسالة
     * ويعرض رسالته العامّة بدلاً منها. المنعُ يقع في الحالين — والرسالة
     * الواضحة تأتي من `plan.limit` على المسار، وهو الحارس الأوّل.
     */
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'code' => 'PLAN_LIMIT_REACHED',
                'feature' => $this->feature,
                'remaining' => $this->remaining,
            ], 402);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
