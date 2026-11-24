<?php

namespace App\Services;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\CollectionsTarget;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\SalesTarget;
use App\Models\SalesTargetSettlement;
use App\Models\TargetSetting;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Services\Finance\InsufficientTreasuryBalanceException;
use App\Services\Finance\TreasuryBalanceGuard;
use App\Support\CurrencySettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * خدمة مركزية لتسوية أهداف المبيعات.
 *
 * تُستخدم من:
 *   - SalesTargetController::settle()       (تسوية يدوية)
 *   - ProcessTargetSettlements (Cron)       (تسوية مجدولة)
 *
 * تتولى:
 *   - التحقق من إعدادات الأهداف (وجود حساب العمولة)
 *   - التحقق من كفاية رصيد الخزينة لكل موظف (`TreasuryBalanceGuard`) قبل السند والقيد
 *   - إنشاء سند صرف Expense لكل موظف
 *   - إنشاء قيد محاسبي JournalEntry بالبيان المناسب
 *   - تسجيل SalesTargetSettlement
 *   - إرسال إشعار لكل موظف
 */
class TargetSettlementService
{
    /**
     * تنفيذ التسوية لهدف معين خلال فترة.
     *
     * @param  Model  $target  SalesTarget أو CollectionsTarget
     * @param  array  $rows  أسطر بصيغة: ['user_id', 'achieved_amount', 'commission_amount']
     * @param  int|null  $settledByUserId  المستخدم الذي يُسوّي (null = تلقائي/cron)
     * @return array ['success' => bool, 'settlements_created' => int, 'total_commission' => float, 'errors' => []]
     *
     * @throws \Exception إذا لم يتم اختيار حساب العمولة في الإعدادات
     */
    public function settle(
        Model $target,
        string $fromDate,
        string $toDate,
        array $rows,
        ?string $notes = null,
        ?int $settledByUserId = null,
        bool $automated = false
    ): array {
        // تحديد نوع الهدف لقراءة الإعدادات المناسبة
        $targetType = $target instanceof CollectionsTarget
            ? TargetSetting::TYPE_COLLECTIONS
            : TargetSetting::TYPE_SALES;
        $isCollections = $targetType === TargetSetting::TYPE_COLLECTIONS;
        $setting = TargetSetting::for($targetType);

        // ⛔ قاعدة: لا يمكن التسوية إن لم يكن حساب العمولة مُختاراً
        if (empty($setting->commission_account_id)) {
            throw new \Exception(
                'لا يمكن تنفيذ التسوية: يجب اختيار حساب العمولة في إعدادات الأهداف أولاً.'
            );
        }

        // التحقق من وجود الحساب المحاسبي فعلياً
        $commissionAccount = Account::find($setting->commission_account_id);
        if (! $commissionAccount) {
            throw new \Exception('حساب العمولة المختار في الإعدادات غير موجود في شجرة الحسابات.');
        }

        // فلترة الأسطر التي لها عمولة موجبة
        $rows = array_filter($rows, fn ($r) => ($r['commission_amount'] ?? 0) > 0);
        if (empty($rows)) {
            return [
                'success' => true,
                'settlements_created' => 0,
                'total_commission' => 0,
                'errors' => [],
                'message' => 'لا توجد عمولات مستحقة في هذه الفترة',
            ];
        }

        $created = 0;
        $totalAmount = 0.0;
        $errors = [];
        // settled_by NOT NULL في DB - fallback لأول مدير عام عند تشغيل cron بدون auth
        $settledBy = $settledByUserId
            ?: auth()->id()
            ?: User::where('role', 'main')->value('id')
            ?: User::min('id');

        if (! $settledBy) {
            throw new \Exception('لا يوجد مستخدم متاح لتسجيله كمنفّذ للتسوية (settled_by). يرجى التأكد من وجود مدير في النظام.');
        }

        DB::beginTransaction();
        try {
            /* قفلُ صفّ الهدف طوالَ التسوية.

               فحصُ «سُوّيت هذه الفترة؟» في المتحكّم وفي الأمر المجدول يجري
               قبل أيّ كتابة، فضغطتا «تسوية» متزامنتان — أو يدويّةٌ تلتقي
               بالمجدولة — كانتا تمرّان كلتاهما ثم تكتبان: عمولةٌ تُصرف مرّتين
               بسندَين وقيدَين. الثانيةُ تنتظر هنا حتى تنتهي الأولى، ثم ترى ما
               كتبته في فحص الموظف أدناه. والمفتاحُ الفريد على (الهدف، الموظف،
               الفترة) حارسٌ أخيرٌ تحته. */
            $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->first();

            foreach ($rows as $row) {
                $userId = (int) $row['user_id'];
                // مبلغٌ واحدٌ بالهللة في السند والقيد والتسوية — لا ثلاثُ دقّاتٍ مختلفة
                $commissionAmount = round((float) $row['commission_amount'], 2);
                $achievedAmount = (float) ($row['achieved_amount'] ?? 0);

                $user = User::with('employee')->find($userId);
                if (! $user) {
                    $errors[] = "المستخدم #$userId غير موجود";

                    continue;
                }

                $employeeId = $user->employee_id;
                if (! $employeeId) {
                    $errors[] = "المستخدم {$user->name} ليس مرتبطاً بسجل موظف";

                    continue;
                }

                /* تسويةٌ سابقةٌ **لهذا الموظف** تتداخل مع الفترة — تُقرأ بعد
                   القفل، فتُرى تسويةُ طلبٍ متزامنٍ سبق هذا. */
                $alreadySettled = $target->settlements()
                    ->where('employee_id', $employeeId)
                    ->where('from_date', '<=', $toDate)
                    ->where('to_date', '>=', $fromDate)
                    ->exists();

                if ($alreadySettled) {
                    $errors[] = "عمولة {$user->name} سُوّيت من قبل عن فترةٍ تتداخل مع هذه";

                    continue;
                }

                // تحديد الخزينة لهذا الموظف
                $treasuryId = $this->resolveTreasuryForUser($user, $setting);
                if (! $treasuryId) {
                    $errors[] = "لا توجد خزينة متاحة لـ {$user->name} ولا خزينة افتراضية في الإعدادات";

                    continue;
                }

                /* ⛔ كفايةُ رصيد الخزينة قبل السند والقيد — كانت التسويةُ تصرف
                   ولو لم يبقَ في الخزينة شيء، فتنتهي سالبة. الرصيدُ من الدفتر
                   تحت قفلٍ على صفّ الخزينة، ويُعاد حسابُه لكلّ موظف فيرى ما
                   صُرف لمن قبله في الحلقة نفسِها. ومن لم يكفِه الرصيدُ يُستثنى
                   وحده ويبقى مستحقّاً: لا تُكتب له تسوية، فتُعاد تسويتُه متى
                   غُذِّيت الخزينة. */
                try {
                    TreasuryBalanceGuard::lockAndAssert($treasuryId, $commissionAmount, "صرف عمولة {$user->name}");
                } catch (InsufficientTreasuryBalanceException $e) {
                    $errors[] = $e->getMessage();

                    continue;
                } catch (\RuntimeException $e) {
                    $errors[] = "عمولة {$user->name}: ".$e->getMessage();

                    continue;
                }

                // نسبة الإنجاز (للبيان)
                $achievement = $target->achievements->firstWhere('employee_id', $userId);
                $targetAmount = $achievement ? (float) $achievement->target_amount : (float) $target->target_value;
                $percentage = $targetAmount > 0 ? round(($achievedAmount / $targetAmount) * 100, 1) : 0;

                // ===== إنشاء سند صرف =====
                $expenseCode = Expense::generateCode();
                $description = $this->buildDescription(
                    $target, $user, $fromDate, $toDate, $achievedAmount, $targetAmount, $percentage, $commissionAmount, $automated, $isCollections
                );

                $expense = Expense::create([
                    'code' => $expenseCode,
                    'amount' => $commissionAmount,
                    'description' => $description,
                    'date' => now()->toDateString(),
                    'created_by' => $settledBy,
                    'treasury_id' => $treasuryId,
                    'account_id' => $setting->commission_account_id,
                    'is_recurring' => 0,
                    'tax1_amount' => 0, // مطلوب NOT NULL في DB
                    'tax2_amount' => 0, // مطلوب NOT NULL في DB
                ]);

                // ===== إنشاء القيد المحاسبي =====
                $journal = JournalEntry::create([
                    'reference_number' => $expense->code,
                    'reference_id' => $expense->id,
                    'date' => now()->toDateString(),
                    'description' => $description,
                    'status' => $setting->require_approval ? 0 : 1, // 1=معتمد، 0=بانتظار اعتماد
                    'currency' => CurrencySettings::code(),
                    'reference_type' => ReferenceType::EXPENSES,
                    'linking_type' => LinkingType::SINGLE,
                    'created_by_employee' => $settledBy,
                ]);

                // مدين: حساب العمولة (مصروف)
                JournalEntryDetail::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $setting->commission_account_id,
                    'description' => "عمولة {$user->name} - هدف '{$target->name}' - ".CurrencySettings::format($commissionAmount),
                    'debit' => $commissionAmount,
                    'credit' => 0,
                    'is_debit' => true,
                ]);

                // دائن: الخزينة (نقدية خرجت)
                JournalEntryDetail::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $treasuryId,
                    'description' => "صرف عمولة من الخزينة لـ {$user->name}",
                    'debit' => 0,
                    'credit' => $commissionAmount,
                    'is_debit' => false,
                ]);

                // ===== سجل التسوية =====
                // نستخدم علاقة النموذج لإنشاء التسوية المناسبة
                // (SalesTargetSettlement أو CollectionsTargetSettlement حسب نوع $target)
                $target->settlements()->create([
                    'employee_id' => $employeeId,
                    'commission_amount' => $commissionAmount,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'notes' => $notes,
                    'settled_by' => $settledBy,
                    'is_settled' => ! $setting->require_approval,
                    // تاريخُ الصرف الذي يقرؤه تقريرُ «العمولات المصروفة» — كان يبقى فارغاً
                    'settled_date' => $setting->require_approval ? null : now()->toDateString(),
                    /* الصرفُ تمّ هنا بسندٍ وقيد. فمع «يتطلب اعتماداً» تُكتب
                       التسويةُ `is_settled=false` و`paid_directly=false` —
                       وهو بالضبط شرطُ «معلّقة» في «عمولاتي الأسبوعية»، فكانت
                       تُصرف ثانيةً بقيدٍ جديد من خزينة الموظف. القيدُ المربوط
                       هنا هو ما تستثنيه تلك الشاشة. */
                    'direct_journal_entry_id' => $journal->id,
                    'direct_paid_at' => now(),
                ]);

                // ===== الإشعار للموظف =====
                if ($setting->notify_on_settlement) {
                    $this->notifyEmployee($user, $target, $fromDate, $toDate, $commissionAmount, $percentage, $automated, $isCollections);
                }

                $totalAmount += $commissionAmount;
                $created++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('TargetSettlementService::settle failed', [
                'target_id' => $target->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return [
            'success' => true,
            'settlements_created' => $created,
            'total_commission' => $totalAmount,
            'errors' => $errors,
            'message' => "تم إنشاء $created تسوية بإجمالي ".CurrencySettings::format($totalAmount),
        ];
    }

    /**
     * تحديد الخزينة التي تُصرف منها عمولة هذا الموظف.
     * - إذا فُعِّل pay_from_employee_treasury: نحاول خزينة الموظف، وإن فشلت نلجأ للافتراضية.
     * - وإلا: نستخدم الخزينة الافتراضية مباشرة.
     */
    private function resolveTreasuryForUser(User $user, TargetSetting $setting): ?int
    {
        if ($setting->pay_from_employee_treasury && $user->employee_id) {
            $te = TreasuryEmployee::where('employee_id', $user->employee_id)->first();
            if ($te && $te->treasury_id) {
                return (int) $te->treasury_id;
            }
        }

        return $setting->default_commission_treasury_id
            ? (int) $setting->default_commission_treasury_id
            : null;
    }

    /**
     * بناء بيان شامل ومفهوم للقيد وسند الصرف.
     */
    private function buildDescription(
        Model $target,
        User $user,
        string $fromDate,
        string $toDate,
        float $achieved,
        float $targetAmount,
        float $percentage,
        float $commission,
        bool $automated,
        bool $isCollections = false
    ): string {
        $type = $automated ? 'تسوية تلقائية' : 'تسوية يدوية';
        $byline = $automated ? 'النظام (Cron)' : (auth()->user()->name ?? 'المستخدم الحالي');
        $kindLabel = $isCollections ? 'عمولة هدف تحصيلات' : 'عمولة هدف مبيعات';
        // رمز العملة من «إعدادات العملات» — كان «ر.س» مكتوباً في البيان
        $symbol = CurrencySettings::symbol();

        return sprintf(
            '%s - %s | الموظف: %s | الفترة: %s إلى %s | الإنجاز: %s '.$symbol.' من %s '.$symbol.' (%s%%) | العمولة: %s '.$symbol.' | %s | بواسطة: %s',
            $kindLabel,
            $target->name,
            $user->name,
            $fromDate,
            $toDate,
            CurrencySettings::number($achieved, 2),
            CurrencySettings::number($targetAmount, 2),
            CurrencySettings::number($percentage, 1),
            CurrencySettings::number($commission, 2),
            $type,
            $byline
        );
    }

    /**
     * إرسال إشعار للموظف عند صرف عمولته.
     */
    private function notifyEmployee(
        User $user,
        Model $target,
        string $fromDate,
        string $toDate,
        float $commission,
        float $percentage,
        bool $automated,
        bool $isCollections = false
    ): void {
        try {
            $title = $isCollections
                ? '💰 تم صرف عمولة هدف تحصيلات'
                : '💰 تم صرف عمولة هدف مبيعات';
            $description = sprintf(
                'تم صرف عمولة بقيمة %s عن هدف "%s" للفترة من %s إلى %s. نسبة إنجازك: %s%%.%s',
                CurrencySettings::format($commission),
                $target->name,
                $fromDate,
                $toDate,
                CurrencySettings::number($percentage, 1),
                $automated ? ' (تسوية تلقائية)' : ''
            );

            // نستخدم DB::table مباشرة لأن نموذج Notification قديم وغير متطابق مع الـ schema
            DB::table('notifications')->insert([
                'user_id' => auth()->id() ?: $user->id, // المُرسِل
                'receiver_id' => $user->id,                  // المُستقبِل (الموظف)
                'title' => $title,
                'description' => $description,
                'type' => 'commission_settlement',
                'read' => 0,
                'is_sent' => 1,
                'is_scheduled' => 0,
                'popup_shown' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TargetSettlementService::notifyEmployee failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
