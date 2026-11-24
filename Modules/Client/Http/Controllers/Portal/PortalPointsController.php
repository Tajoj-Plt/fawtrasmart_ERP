<?php

namespace Modules\Client\Http\Controllers\Portal;

use App\Enums\ConfirmationType;
use App\Enums\ReferralStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Ecosystem\PointsTransaction;
use App\Models\Ecosystem\Referral;
use App\Models\Ecosystem\ReferralSetting;
use App\Services\Ecosystem\ClientPointsOverview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Client\Http\Controllers\Concerns\GuardsClientPortal;
use Throwable;

/**
 * نقاطُ العميل ومكافآتُه وإحالاتُه — داخل بوابته الواحدة.
 *
 * ══════════ لماذا هنا؟ ══════════
 *
 * كانت للنقاط **بوابةٌ ثانيةٌ مستقلّة** على `/client-portal`: عنوانٌ آخر،
 * وقالبٌ آخر، ودخولٌ **برقم الهاتف وحده** — أي أنّ من يعرف رقم عميلٍ يدخل
 * حسابَه ويقرأ نقاطه وإحالاته. وطلب المستخدم (2026-09-19) «أبغى بوابة
 * واحدة»، فانتقل القسمُ إلى بوابة العميل: دخولٌ واحد بحسابٍ له كلمة مرور،
 * وحارسٌ واحد (`ClientAccessMiddleware` + مفتاح «صلاحيات بوابة العميل»)،
 * وتصميمٌ واحد. وعناوينُ البوابة القديمة تحوّل إلى هنا.
 *
 * والحسابُ نفسُه لم يُنسخ: `ClientPointsOverview` هي ما كان في متحكّم
 * البوابة القديمة، منقولاً إلى خدمة.
 */
class PortalPointsController extends Controller
{
    use GuardsClientPortal;

    /** مفتاحُ «صلاحيات بوابة العميل» الذي يفتح هذا القسم */
    private const PERMISSION = 'view_points_rewards';

    public function index()
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        $client = Client::findOrFail($clientId);

        return view('client::portal.points', ClientPointsOverview::for($client) + [
            'portalClient' => $client,
        ]);
    }

    /**
     * إحالةُ صديق — منقولةٌ من `ClientPortalController::submitReferral`
     * بلا تغييرٍ في القواعد: الحدود الشهرية والإجمالية، ومنعُ إحالة النفس
     * أو تكرار المُحال، والتأكيدُ الفوريّ إن كان شرطُ الإعدادات «التسجيل».
     */
    public function refer(Request $request)
    {
        [$deny, $clientId] = $this->guardPortal(self::PERMISSION);

        if ($deny) {
            return $deny;
        }

        $data = $request->validate([
            'referred_name' => ['required', 'string', 'max:255'],
            'referred_phone' => ['required', 'string', 'max:20'],
        ], [], [
            'referred_name' => 'اسم الشخص المُحال',
            'referred_phone' => 'رقم هاتف الشخص المُحال',
        ]);

        // عمودا الإحالة غائبان على بعض القواعد — انظر `ClientPointsOverview`
        if (! ClientPointsOverview::referralColumnsExist()) {
            return back()->with('error', 'نظام الإحالات غير متاح على هذا النظام.');
        }

        $client = Client::findOrFail($clientId);
        $settings = ReferralSetting::getActiveSettings($client->branch_id);

        if (! $settings || ! $settings->is_active) {
            return back()->with('error', 'نظام الإحالات غير مفعّل حالياً.');
        }

        if ($settings->max_referrals_per_month > 0) {
            $monthlyCount = Referral::where('referrer_id', $client->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            if ($monthlyCount >= $settings->max_referrals_per_month) {
                return back()->with('error', 'وصلتَ إلى الحدّ الأقصى من الإحالات هذا الشهر ('.$settings->max_referrals_per_month.').');
            }
        }

        if ($settings->max_referrals_total > 0
            && Referral::where('referrer_id', $client->id)->count() >= $settings->max_referrals_total) {
            return back()->with('error', 'وصلتَ إلى الحدّ الأقصى الإجمالي من الإحالات ('.$settings->max_referrals_total.').');
        }

        $referred = Client::where('phone', $data['referred_phone'])
            ->orWhere('mobile', $data['referred_phone'])
            ->first();

        if ($referred && $referred->id === $client->id) {
            return back()->withInput()->with('error', 'لا يمكنك إحالة نفسك.');
        }

        if ($referred && Referral::where('referrer_id', $client->id)->where('referred_id', $referred->id)->exists()) {
            return back()->withInput()->with('error', 'سبق أن أحلتَ هذا العميل.');
        }

        try {
            $message = DB::transaction(function () use ($client, $referred, $settings, $data) {
                $referral = Referral::create([
                    'referrer_id' => $client->id,
                    'referred_id' => $referred?->id,
                    'referral_code' => $client->referral_code,
                    'status' => ReferralStatus::PENDING->value,
                    'referrer_points' => $settings->referrer_points,
                    'referred_points' => $settings->referred_points,
                    'metadata' => [
                        'referred_name' => $data['referred_name'],
                        'referred_phone' => $data['referred_phone'],
                        // المصدرُ كما كان: تقاريرُ الإحالات تصنّف به
                        'source' => 'client_portal',
                        'created_at' => now()->toDateTimeString(),
                    ],
                    'expires_at' => now()->addDays(30),
                ]);

                if ($referred && ! $referred->referred_by) {
                    $referred->update([
                        'referred_by' => $client->id,
                        'referral_code' => $client->referral_code,
                    ]);

                    if ($settings->confirmation_type === ConfirmationType::REGISTRATION->value) {
                        $referral->update([
                            'status' => ReferralStatus::CONFIRMED->value,
                            'confirmed_at' => now(),
                        ]);

                        PointsTransaction::create([
                            'user_type' => UserType::CLIENT->value,
                            'client_id' => $client->id,
                            'points' => $settings->referrer_points,
                            'description' => 'نقاط من إحالة عميل: '.$data['referred_name'],
                            'status' => TransactionStatus::COMPLETED->value,
                            'transaction_type' => 'referral_reward',
                            'branch_id' => $client->branch_id,
                        ]);

                        return 'تم إرسال الإحالة، وأُضيفت لك '.$settings->referrer_points.' نقطة.';
                    }
                }

                return 'تم إرسال الإحالة. ستُضاف نقاطك عند تأكيدها.';
            });
        } catch (Throwable $e) {
            Log::error('تعذّر تسجيل إحالة من بوابة العميل', ['client' => $clientId, 'error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'تعذّر إرسال الإحالة. حاول مرّةً أخرى.');
        }

        return back()->with('success', $message);
    }
}
