<?php

namespace App\Support;

use App\Helpers\ClientSettingsHelper;
use App\Models\EmployeeClientVisit;
use App\Models\Invoice;
use App\Models\PaymentsProcess;
use App\Models\Receipt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * أهليّة زر «تبديل البضاعة» لعميلٍ بعينه — يوم خط سير الموظف لهذا العميل
 * + معاملة اليوم (فاتورة/سند قبض/إشعار دائن/سند دفع)، إن اشترط ذلك إعداد
 * «اشتراط فاتورة/سند/إشعار دائن اليوم للتبديل».
 *
 * نُقلت من ClientController::show() لتُستهلَك من مساحة عمل الزيارة
 * (VisitWorkspaceController) أيضاً بلا تكرار الاستعلامات الخمسة. لم يُعدَّل
 * `ClientController::show()` نفسها — تبقى شاشة العميل على منطقها القائم
 * كما هو حرفياً، تفادياً لأيّ انحرافٍ عن سلوكها المختبَر.
 */
class ProductExchangeEligibility
{
    /** @return array{can_exchange: bool, is_itinerary_day: bool, has_today_transaction: bool} */
    public static function forClient(int $clientId, bool $inOpenVisit = false): array
    {
        $isItineraryDay = true;
        $hasTodayTransaction = true;
        $exchangeRequiresTransaction = ClientSettingsHelper::exchangeRequiresTransaction();

        if (Auth::user()?->isEmployee()) {
            $today = now()->toDateString();
            $userId = Auth::id();

            /* زيارةٌ جارية مع العميل تكفي عن صفّ خطّ السير: هي الدليل الأقوى
               أنّ المندوب عنده اليوم (بدأها من موقعه). كان الزرّ يُقفل
               «متاح في يوم خط السير فقط» داخل زيارةٍ مفتوحة متى لم يطابق
               صفُّ خطّ السير المستخدمَ الذي يُجري الزيارة — كمشرفٍ يفتح زيارة
               مندوبه، أو عميلٍ دخله المندوب من الفهرس لا من خطّ سيره. شرطُ
               معاملة اليوم أدناه باقٍ كما يقرّره الإعداد. */
            $isItineraryDay = $inOpenVisit
                || EmployeeClientVisit::isItineraryDayFor($userId, $clientId)
                || \App\Models\Visit::hasOpenWorkspaceVisit($userId, $clientId);

            $hasInvoiceToday = Invoice::where('client_id', $clientId)->whereDate('invoice_date', $today)->exists();
            $hasCreditNoteToday = DB::table('credit_notifications')->where('client_id', $clientId)->whereDate('credit_date', $today)->exists();
            $hasReceiptToday = Receipt::whereHas('account', fn ($q) => $q->where('client_id', $clientId))->whereDate('created_at', $today)->exists();
            $hasPaymentToday = PaymentsProcess::whereHas('invoice', fn ($q) => $q->where('client_id', $clientId))->whereDate('created_at', $today)->exists();

            // شرط «فاتورة/سند/إشعار دائن اليوم» غير قابلٍ للاستثناء — نفس
            // قاعدة ClientController::show()
            $hasTodayTransaction = ! $exchangeRequiresTransaction
                || $hasInvoiceToday || $hasCreditNoteToday || $hasReceiptToday || $hasPaymentToday;
        }

        return [
            'can_exchange' => $isItineraryDay && $hasTodayTransaction,
            'is_itinerary_day' => $isItineraryDay,
            'has_today_transaction' => $hasTodayTransaction,
        ];
    }
}
