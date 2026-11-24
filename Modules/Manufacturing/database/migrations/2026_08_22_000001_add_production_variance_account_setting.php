<?php

use App\Models\Account;
use App\Models\AccountingSetting;
use App\Support\AccountingSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حساب «انحراف الإنتاج» — الطرف الذي يُقفل مخزون تحت التشغيل.
 *
 * ── لماذا ────────────────────────────────────────────────────────────
 *
 * قيود إتمام أمر التصنيع خمسة، وكلٌّ منها متوازن على حدة. لكن **المجموع
 * الدوري لحساب WIP لا يصفر أبداً**:
 *
 *   القيود 1+2+3 تُدين WIP بـ `$calculatedTotalCost` (مجموع البنود)
 *   القيدان 4+5 يُقرضانه بـ `$totalCost` (`last_total_cost` من الواجهة)
 *
 *   ⇒ الفرق = `$calculatedTotalCost − $totalCost`
 *
 * وهو ليس صفراً كلّما اختلف ما أرسلته الواجهة عن مجموع البنود — وهذا هو
 * الوضع الطبيعي لا الاستثناء. الفرق يتراكم في الحساب إلى الأبد ولا يظهر
 * في أي تقرير ولا تسوية.
 *
 * وفرقٌ ثانٍ يتراكم معه: الإذن والمخزون يحملان `actual_quantity × unitPrice`
 * بينما القيد الرابع يُدين البضاعة الجاهزة بتكلفة **الكمية المخطَّطة**،
 * فقيمة المخزون في الدفاتر تفوق قيمته في نظام المخزون كلّما نقصت الكمية
 * الفعلية — وهو ما يحدث دائماً لوجود الهالك.
 *
 * القيد السادس — الانحراف — يستوعب الفرقين معاً:
 *
 *   الانحراف = (المحمَّل على WIP) − (قيمة المنتج التام + الهالك)
 *
 * ── ما تفعله هذه الهجرة ──────────────────────────────────────────────
 *
 * تضيف مفتاح `production_variance` إلى شاشة «الحسابات المحاسبية»، وتحاول
 * ربطه بحساب موجود. إن لم تجد، تتركه **غير مربوط** فيظهر في الشاشة
 * ينتظر المحاسب — ولا تخترع حساباً في شجرته.
 *
 * ⚠️ ما دام غير مربوط، ترمي `requireAccountId` وتُلغى عملية الإتمام.
 *    هذا مقصود: الترحيل بلا حساب انحراف يعيد المشكلة نفسها.
 */
return new class extends Migration
{
    private const KEY = 'production_variance';

    public function up(): void
    {
        if (! class_exists(AccountingSetting::class)) {
            return;
        }

        if (AccountingSetting::where('key', self::KEY)->exists()) {
            return;
        }

        $account = $this->findVarianceAccount();

        AccountingSetting::create([
            'key' => self::KEY,
            'name' => 'انحراف تكاليف الإنتاج',
            'description' => 'الفرق بين التكلفة المحمَّلة على أمر التصنيع وقيمة ما دخل المخزون — يُقفل به حساب مخزون تحت التشغيل',
            'group' => 'المخزون والتصنيع',
            'account_id' => $account?->id,
            'account_code' => $account?->code ? (string) $account->code : null,
            'is_active' => true,
        ]);

        AccountingSettings::forget();

        if (! $account) {
            echo "\n  ⚠️  أُضيف مفتاح «انحراف تكاليف الإنتاج» بلا حساب مربوط.\n";
            echo "      اربطه من: الإعدادات ← الحسابات المحاسبية ← المخزون والتصنيع.\n";
            echo "      الأنسب: حساب مصروف تحت «مصروفات التصنيع».\n";
            echo "      بدونه لن يكتمل أي أمر تصنيع — وهو مقصود.\n\n";
        } else {
            echo "\n  ✓ رُبط «انحراف تكاليف الإنتاج» بالحساب #{$account->id} — {$account->name}\n\n";
        }
    }

    public function down(): void
    {
        AccountingSetting::where('key', self::KEY)->delete();
        AccountingSettings::forget();
    }

    /**
     * يبحث عن حساب انحراف قائم — ولا يُنشئ واحداً.
     *
     * إنشاء حساب في شجرة المحاسب من هجرة تدخّلٌ في اختصاصه: قد يكون له
     * حسابٌ يريده، أو يريده تحت أبٍ بعينه، أو يريد تفصيله إلى انحراف
     * كمية وانحراف سعر.
     */
    private function findVarianceAccount(): ?Account
    {
        try {
            // أولاً: حسابٌ باسمٍ صريح
            $byName = Account::where('name', 'like', '%انحراف%')
                ->where(function ($q) {
                    $q->where('name', 'like', '%إنتاج%')
                        ->orWhere('name', 'like', '%تصنيع%')
                        ->orWhere('name', 'like', '%تكاليف%');
                })
                ->first();

            if ($byName) {
                return $byName;
            }

            // ثانياً: أي حساب انحراف
            return Account::where('name', 'like', '%انحراف%')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
};
