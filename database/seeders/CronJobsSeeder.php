<?php

namespace Database\Seeders;

use App\Jobs\EvaluateCustomerTargetsJob;
use App\Jobs\ProcessAutoDepartures;
use App\Models\CronJob;
use App\Services\Cron\CronRegistry;
use App\Services\Cron\CronScheduleParser;
use Illuminate\Database\Seeder;

/**
 * مهام النظام المجدولة: ما نُقل من `app/Console/Kernel.php`، وما يقدّمه
 * النظام من دورات جاهزة، وخطّة دورات ERP التي لم تُنفَّذ بعد.
 *
 * الجدولة المنقولة مطابقة للسابقة تماماً حتى لا يتغيّر سلوك النظام بمجرّد
 * النقل — ما تغيّر هو أن تعديلها صار ممكناً من الإعدادات.
 *
 * قاعدة تفعيل مقصودة: لا يُفعَّل بالبذر إلا ما لا أثر جانبيّ له (تنظيف
 * وصيانة). كل ما يرسل رسالة أو يحرّك مالاً أو يكتب بيانات عمل يُبذَر
 * معطَّلاً — تشغيله قرار المسؤول لا نتيجة جانبية لتشغيل seeder.
 *
 * الـ seeder قابل لإعادة التشغيل: ما وُجد مفتاحه لا يُعاد إنشاؤه ولا
 * تُدهَس جدولته، فلا يمحو تشغيله ثانيةً ما عدّله المسؤول من الواجهة.
 * ما يُصحَّح دائماً هو الحقول البنيوية وحدها (المُعالِج، النوع، النظامية).
 */
class CronJobsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $this->register($definition);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function register(array $definition): void
    {
        $expression = $definition['cron_expression'] ?? CronScheduleParser::build(
            $definition['schedule_type'],
            $definition['schedule_time'] ?? null,
            $definition['schedule_day'] ?? null
        );

        $existing = CronJob::where('key', $definition['key'])->first();

        $protected = (bool) ($definition['is_protected'] ?? false);

        if ($existing) {
            $structural = [
                'handler' => $definition['handler'],
                'type' => $definition['type'],
                'is_system' => true,
                'is_protected' => $protected,
            ];

            // **المحميّة تُستعاد قسراً** — جدولتها وحالتها ليستا ملك المسؤول.
            //
            // فلو أُوقفت أو غُيّرت جدولتها بأي طريق (قبل إضافة الحماية، أو
            // بكتابة مباشرة على القاعدة) أعاد السيدر ضبطها. ومزامنة الاشتراك
            // إن أُوقفت عمل النظام بحدود اشتراكٍ قديم لا يعرف أنه انتهى.
            if ($protected) {
                $structural += [
                    // الوصف يُستعاد أيضاً: صفٌّ بُذر يوماً بوسم «(غير مطبَّقة
                    // بعد)» يبقى يقول ذلك للأبد وإن صار الأمر مكتوباً
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'schedule_type' => $definition['schedule_type'],
                    'cron_expression' => $expression,
                    'status' => CronJob::STATUS_ACTIVE,
                    'parameters' => $definition['parameters'] ?? [],
                    'timeout' => $definition['timeout'] ?? 300,
                ];
            }

            /* **مهمّةٌ صارت مكتوبة** — الوسم يُنزَع والمهمّة تُفعَّل.
             *
             * الصفّ يُبذَر معطَّلاً موسوماً بـ«(غير مطبَّقة بعد)» ما دام
             * أمرُه غير مسجَّلٍ في `CronRegistry`. ثمّ يُكتب الأمر —
             * `sales:generate-periodic-invoices` مثلاً — ويبقى الصفُّ
             * معطَّلاً موسوماً بذلك للأبد: الميزة تعمل والمهمّة لا
             * تعمل، ولا شيء في الشاشة يدلّ على السبب.
             *
             * الوسم نفسه هو الدليل على أنّ التعطيل من السيدر لا من
             * المسؤول، فلا يُفعَّل إلّا ما كان موسوماً به. */
            $stillFlagged = str_starts_with((string) $existing->description, '(غير مطبَّقة بعد)');

            if ($stillFlagged && CronRegistry::isAvailable($definition['handler'])) {
                $structural += [
                    'description' => $definition['description'],
                    'status' => $definition['status'] ?? CronJob::STATUS_ACTIVE,
                ];
            }

            // `forceFill` + `saveQuietly` يتخطّيان حارس النموذج عن قصد: هذا
            // هو المسار الوحيد المسموح له بضبط مهمّة محميّة
            $existing->forceFill($structural)->saveQuietly();

            if ($protected) {
                $existing->forceFill(['next_run_at' => $existing->nextRunDate()])->saveQuietly();
            }

            return;
        }

        // المهمة المخطَّطة تُبذَر معطَّلة دائماً مهما قيل في تعريفها:
        // أمرها غير مكتوب، وتفعيلها يعني فشلاً متكرّراً وتنبيهات بلا سبب
        $planned = ! CronRegistry::isAvailable($definition['handler']);
        $status = $planned
            ? CronJob::STATUS_DISABLED
            : ($definition['status'] ?? CronJob::STATUS_ACTIVE);

        $job = CronJob::create([
            'key' => $definition['key'],
            'name' => $definition['name'],
            'description' => $planned
                ? '(غير مطبَّقة بعد) '.$definition['description']
                : $definition['description'],
            'type' => $definition['type'],
            'handler' => $definition['handler'],
            'parameters' => $definition['parameters'] ?? [],
            'schedule_type' => $definition['schedule_type'],
            'cron_expression' => $expression,
            'schedule_time' => $definition['schedule_time'] ?? null,
            'schedule_day' => $definition['schedule_day'] ?? null,
            'status' => $status,
            'is_system' => true,
            'is_protected' => $protected,
            'prevent_overlap' => $definition['prevent_overlap'] ?? true,
            'timeout' => $definition['timeout'] ?? 300,
            'notify_on_failure' => $definition['notify_on_failure'] ?? true,
            'notify_on_success' => false,
        ]);

        // المهام المعطَّلة بلا موعد تالٍ: إظهار موعد لن يأتي يضلّل
        if ($job->isActive()) {
            $job->forceFill(['next_run_at' => $job->nextRunDate()])->save();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function definitions(): array
    {
        return [
            // ============ إدارة العقارات — تكتب بيانات عملٍ وترسل تنبيهات، فتُزرع معطّلة ============
            [
                'key' => 'realestate_sweep',
                'name' => 'العقارات: كنس العقود والاستحقاقات',
                'description' => 'يُنهي العقود المنقضية ويحرّر وحداتها ويحدّث حالات الاستحقاقات والعروض.',
                'type' => 'maintenance',
                'handler' => 'realestate:sweep',
                'schedule_type' => 'daily',
                'schedule_time' => '00:30',
                'status' => CronJob::STATUS_DISABLED,
                'parameters' => [],
                'timeout' => 600,
            ],
            [
                'key' => 'realestate_alerts',
                'name' => 'العقارات: تنبيهات العقود والدفعات',
                'description' => 'ينبّه بقرب انتهاء العقود وباستحقاق الدفعات وتأخّرها.',
                'type' => 'notification',
                'handler' => 'realestate:alerts',
                'schedule_type' => 'daily',
                'schedule_time' => '08:00',
                'status' => CronJob::STATUS_DISABLED,
                'parameters' => [],
                'timeout' => 600,
            ],

            // ============ منقولة من Kernel.php بجدولتها الأصلية ============
            [
                'key' => 'auto_departures',
                'name' => 'مغادرة الزوار تلقائياً',
                'description' => 'ينهي جلسات الزوار الذين تجاوزوا المدّة دون تسجيل مغادرة.',
                'type' => 'job',
                'handler' => ProcessAutoDepartures::class,
                'schedule_type' => 'every_minute',
                'timeout' => 120,
            ],
            [
                'key' => 'scheduled_notifications',
                'name' => 'إرسال الإشعارات المجدولة',
                'description' => 'يرسل الإشعارات التي حان موعدها ولم تُرسَل بعد.',
                'type' => 'notification',
                'handler' => 'notifications:process-scheduled',
                'schedule_type' => 'every_minute',
                'timeout' => 120,
            ],
            // كانتا مكتوبتين في `Console\Kernel` وحدَهما — فلم يصلهما
            // المجدولُ الداخلي الذي يُنفّذ `cron:run-due` لا `schedule:run`
            [
                'key' => 'sms_flush',
                'name' => 'إرسال الرسائل النصية المؤجَّلة',
                'description' => 'يرسل الرسائل المؤجَّلة إلى ما بعد ساعات الهدوء ويعيد محاولة الفاشلة وينظّف السجل.',
                'type' => 'notification',
                'handler' => 'sms:flush',
                'schedule_type' => 'every_5_minutes',
                'timeout' => 300,
            ],
            [
                'key' => 'sessions_reconcile',
                'name' => 'مصالحة سجلّ الجلسات',
                'description' => 'يغلق جلسات سجلّ الجلسات التي انتهت بلا حدث خروج، وينظّف القديم منها.',
                'type' => 'maintenance',
                'handler' => 'sessions:reconcile',
                'schedule_type' => 'every_10_minutes',
                'timeout' => 300,
            ],
            // للتثبيتات الجديدة؛ القائمة يضيفه لها ترحيل
            // `2026_09_29_100100_create_zatca_documents_table`
            [
                'key' => 'zatca_submit_pending',
                'name' => 'إرسال الفواتير الإلكترونية المعلَّقة',
                'description' => 'يعيد إرسال ما تعذّر بلوغه هيئة الزكاة والضريبة والجمارك، ويرسل ما لم يُرسَل من الفواتير حين يكون الإرسال التلقائي مفعَّلاً. لا يفعل شيئاً والخدمة معطَّلة.',
                'type' => 'sales',
                'handler' => 'zatca:submit-pending',
                'schedule_type' => 'every_15_minutes',
                'timeout' => 600,
            ],
            [
                'key' => 'fawtra_subscription_sync',
                'name' => 'مزامنة الاشتراك مع لوحة فوترة',
                'description' => 'يجلب حالة الاشتراك والباقة وحدود الاستخدام والتنبيهات. الفترة كما توصي وثيقة الواجهة: كل ربع ساعة.',
                'type' => 'maintenance',
                'handler' => 'fawtra:sync',
                'schedule_type' => 'every_15_minutes',
                'timeout' => 120,

                // محميّة: لا تُعدَّل ولا تُوقَف ولا تُحذف ولا تظهر في الشاشة.
                // بها يعرف النظام حدود اشتراكه، وإيقافُها يجعله يعمل بحدود
                // لقطةٍ قديمة لا يعرف أنها انتهت
                'is_protected' => true,
            ],
            // تكتب بيانات عمل (تنقل بطاقاتٍ بين أعمدة اللوحة) — فتُزرع
            // معطّلة كنظيراتها، وتشغيلُها قرارُ المسؤول
            [
                'key' => 'tasks_mark_overdue',
                'name' => 'المهام: وضع المتأخّر في حالة «متأخرة»',
                'description' => 'يضع المهامّ التي فات استحقاقها ولم تكتمل في حالة «متأخرة».',
                'type' => 'maintenance',
                'handler' => 'tasks:mark-overdue',
                'schedule_type' => 'daily',
                'schedule_time' => '00:10',
                'status' => CronJob::STATUS_DISABLED,
                'parameters' => [],
                'timeout' => 300,
            ],
            [
                'key' => 'project_invites_cleanup',
                'name' => 'تنظيف دعوات المشاريع المنتهية',
                'description' => 'يحذف دعوات المشاريع التي انتهت صلاحيتها.',
                'type' => 'cleanup',
                'handler' => 'project-invites:cleanup',
                'schedule_type' => 'daily',
                'schedule_time' => '00:00',
            ],
            /*
                تنبيهات الأقساط — تُبذَر **معطَّلة**: القاعدة أن كلّ ما
                يرسل رسالةً لا يُفعَّل بالبذر، وتشغيلُه قرارُ المسؤول.
            */
            [
                'key' => 'installments_due_alerts',
                'name' => 'تنبيه استحقاق الأقساط',
                'description' => 'ينبّه بالأقساط التي حان استحقاقها أو قاربته أو تأخّرت، مرّةً في اليوم للقسط الواحد.',
                'type' => 'notification',
                'handler' => 'installments:due-alerts',
                'schedule_type' => 'daily',
                'schedule_time' => '07:30',

                // أيامُ التذكير الاستباقي تُضبط من الشاشة — ثلاثةٌ افتراضاً
                'status' => CronJob::STATUS_DISABLED,
                'parameters' => [],
                'timeout' => 600,
            ],

            /*
                غرامات التأخير — تُبذَر معطَّلة كذلك، وهي أولى بذلك: تكتب
                في دفتر الأستاذ وتزيد ذمّةَ عميل. ولا تفعل شيئاً قبل أن
                تُضبَط سياسةُ غرامةٍ في اتفاقية، لكنّ تفعيلَها يبقى قراراً
                يُتَّخذ لا أثراً جانبياً لتشغيل سيدر.
            */
            [
                'key' => 'installments_late_fees',
                'name' => 'احتساب غرامات تأخير الأقساط',
                'description' => 'يحمّل غرامة التأخير على الأقساط المتأخّرة بحسب سياسة اتفاقيتها ويرحّلها إلى حساب «غرامات تأخير الأقساط».',
                'type' => 'maintenance',
                'handler' => 'installments:late-fees',
                'schedule_type' => 'daily',
                'schedule_time' => '01:45',
                'status' => CronJob::STATUS_DISABLED,
                'parameters' => [],
                'timeout' => 900,
            ],
            [
                'key' => 'quotations_expiry',
                'name' => 'إنهاء عروض الأسعار المنتهية',
                'description' => 'ينقل عروض الأسعار التي تجاوز تاريخُ صلاحيتها اليومَ إلى حالة «منتهي الصلاحية»، فلا تُحوَّل إلى فواتير بأسعارٍ انقضت.',
                'type' => 'maintenance',
                'handler' => 'quotations:expire',
                'schedule_type' => 'daily',
                'schedule_time' => '01:15',

                /* المهلة تُضبط من الشاشة: `--grace=2` مثلاً لمنشأةٍ تتفاوض
                   يوماً أو يومين بعد الموعد. الصفر هو الافتراض. */
                'parameters' => [],
                'timeout' => 600,
            ],
            [
                'key' => 'itinerary_mark_missed_visits',
                'name' => 'تصنيف زيارات خط السير الفائتة',
                'description' => 'بعد منتصف الليل: كل زيارة خط سير انقضى يومُها ولم تكتمل (لم تبدأ، أو بدأت ولم تُنهَ) تُصنَّف «فائتة».',
                'type' => 'maintenance',
                'handler' => 'itinerary:mark-missed-visits',
                'schedule_type' => 'daily',
                'schedule_time' => '00:05',
                'parameters' => [],
                'timeout' => 300,
            ],            [
                'key' => 'weekly_commissions',
                'name' => 'حساب العمولات الأسبوعية',
                'description' => 'يحسب عمولات المندوبين عن الأسبوع المنتهي، كل سبت.',
                'type' => 'maintenance',
                'handler' => 'commissions:calculate-weekly',
                'schedule_type' => 'weekly',
                'schedule_time' => '00:01',
                'schedule_day' => 6, // السبت
                'timeout' => 900,
            ],
            [
                'key' => 'contract_expirations',
                'name' => 'فحص العقود المنتهية قريباً',
                'description' => 'ينبّه على العقود التي أوشكت على الانتهاء.',
                'type' => 'notification',
                'handler' => 'contracts:check-expirations',
                'schedule_type' => 'daily',
                'schedule_time' => '09:00',
            ],
            [
                'key' => 'payroll_process',
                'name' => 'معالجة قسائم الرواتب',
                'description' => 'يولّد قسائم الرواتب الجديدة ويصرف المستحقّة منها.',
                'type' => 'maintenance',
                'handler' => 'payroll:process',
                'schedule_type' => 'daily',
                'schedule_time' => '06:00',
                'timeout' => 1800,
            ],
            [
                'key' => 'attendance_process',
                'name' => 'معالجة الحضور اليومي',
                'description' => 'يحسب التأخير والغياب لكل موظف نشط ويطبّق الخصميات.',
                'type' => 'maintenance',
                'handler' => 'attendance:process',
                'schedule_type' => 'daily',
                'schedule_time' => '23:55',
                'timeout' => 1800,
            ],
            [
                'key' => 'monthly_top_performer',
                'name' => 'نقاط أفضل موظف شهرياً',
                'description' => 'يمنح نقاط أفضل أداء في آخر يوم من كل شهر.',
                'type' => 'maintenance',
                'handler' => 'points:monthly-top-performer',
                // `L` = آخر يوم في الشهر. كان هذا في Kernel شرطاً في PHP
                // (`when(...)`) لأن cron النظام لا يفهمه؛ ومحلّل التعبير
                // المستعمل هنا يفهمه، فصار جزءاً من الجدولة نفسها
                'schedule_type' => CronJob::SCHEDULE_CRON,
                'cron_expression' => '55 23 L * *',
                'timeout' => 600,
            ],
            [
                'key' => 'client_targets_evaluation',
                'name' => 'التقييم الديناميكي للعملاء',
                'description' => 'يعيد تقييم تصنيف العملاء حسب نشاطهم.',
                'type' => 'maintenance',
                'handler' => 'clients:evaluate-targets',
                'schedule_type' => 'daily',
                'schedule_time' => '01:00',
                'timeout' => 1800,
            ],

            /* نشطةٌ ابتداءً — وهي تحرّك مالاً، خلافاً لقاعدة الملف: لا تُصدر
               إلّا ما علَّمه المستخدم «سند مكرَّر» بنفسه، والشاشةُ تَعِده بأنّه
               «يُعاد إنشاؤه تلقائياً». تعطيلُها ابتداءً يُبقي الوعدَ كاذباً.
               والهجرة `2026_09_16_170000_add_recurring_tracking_to_finance_vouchers`
               تضيف الصفَّ للتثبيتات القائمة. */
            [
                'key' => 'finance_recurring_vouchers',
                'name' => 'إصدار سندات القبض والصرف المكرَّرة',
                'description' => 'يُصدر النسخة المستحقّة من كل سند قبض أو صرف مكرَّر بمسار الحفظ نفسه (القيد والخزينة والترقيم)، حتى تاريخ نهاية التكرار، ويتخطّى الفترات المقفلة.',
                'type' => 'maintenance',
                'handler' => 'finance:generate-recurring-vouchers',
                'schedule_type' => 'daily',
                'schedule_time' => '00:35',
                'timeout' => 900,
            ],

            /* نشطةٌ ابتداءً: لا تولّد إلّا ما علَّمه المستخدم «موعد متكرر» بنفسه
               وحدّد نهايته — وبدونها يبقى المربّعُ وعداً لا يُنفَّذ. */
            [
                'key' => 'appointments_generate_recurring',
                'name' => 'توليد المواعيد المتكررة',
                'description' => 'ينشئ الموعد التالي لكل موعد متكرر قبل حلوله بأسبوع، بنفس العميل والموظفين والوقت، حتى تاريخ نهاية التكرار.',
                'type' => 'maintenance',
                'handler' => 'appointments:generate-recurring',
                'schedule_type' => 'daily',
                'schedule_time' => '00:40',
                'timeout' => 600,
            ],

            /* ── ما تُشغّله «إعدادات وحدات العملاء» ──
               الاثنتان معطَّلتان ابتداءً: كلٌّ منهما يُغيّر بيانات (يُقفل
               مواعيد، يقبل تحويلات)، وتفعيلُها بلا أن يضبط المسؤول إعدادَها
               المقابل يعني نظاماً يتصرّف بما لم يطلبه أحد. وكلٌّ منها —
               فوق ذلك — يخرج بلا عملٍ ما دام إعدادُه مُطفأً. */
            [
                'key' => 'client_appointment_reminders',
                'name' => 'تذكير مواعيد العملاء',
                'description' => 'يذكّر الموظف بموعده قبل حلوله بالمدّة المضبوطة، ويُقفل المواعيد الفائتة إن كان الإقفال التلقائي مفعَّلاً.',
                'type' => 'notification',
                'handler' => 'clients:appointment-reminders',
                // الدقيقة وحدها ما يعنيه النمط الساعي — و15/45 حتى لا
                // تتزاحم المهمّتان على رأس الساعة
                'schedule_type' => 'hourly',
                'schedule_time' => '00:15',
                'timeout' => 600,
                'status' => CronJob::STATUS_DISABLED,
            ],

            /* كانت في `plannedDefinitions()` وأمرُها مكتوبٌ منذ أيلول —
               فورثت من قالب المخطَّطات `notify_on_failure => false` (فشلُها
               صامت) وجدولةً يوميّةً لا تناسب مهلاً قصيرة: تشغيلةُ 18:00
               بمهلة «قبل ساعتين» لا تلتقط إلا حجوزات 18:00–20:00.
               ساعيّةً بنافذة 60 = كلُّ مهلة تُدرَك في ساعتها بلا تكرار
               (`reservation_reminders` يمنع الإعادة أصلاً). نشطةٌ ابتداءً
               كتوليد المواعيد: تخرج بلا عملٍ ما دام `notify_reminder` مُطفأً
               في إعدادات الحجوزات. */
            [
                'key' => 'reservations_reminders',
                'name' => 'تذكير الحجوزات القادمة',
                'description' => 'يذكّر العميل بحجزه المؤكَّد قبل موعده حسب المهل المضبوطة في إعدادات الحجوزات، مرّةً واحدة لكل مهلة.',
                'type' => 'notification',
                'handler' => 'reservations:reminders',
                'schedule_type' => 'hourly',
                'schedule_time' => '00:45',
                'parameters' => ['window' => 60],
                'timeout' => 600,
            ],

            // ============ مهام النظام الجديدة ============
            [
                'key' => 'scheduled_backup',
                'name' => 'النسخ الاحتياطي المجدول',
                'description' => 'ينفّذ النسخة الاحتياطية حسب إعدادات النسخ الاحتياطي ثم يطبّق سياسة الاحتفاظ.',
                'type' => 'backup',
                'handler' => 'backup:scheduled-run',
                'schedule_type' => 'daily',
                'schedule_time' => '02:00',
                'timeout' => 3600,
                // معطَّلة حتى يُفعِّل المسؤول النسخ الاحتياطي من شاشته —
                // تفعيلها هنا يبدأ بملء القرص دون أن يطلب أحد ذلك
                'status' => CronJob::STATUS_DISABLED,
            ],
            [
                'key' => 'backup_cleanup',
                'name' => 'تنظيف النسخ الاحتياطية القديمة',
                'description' => 'يحذف النسخ التي تجاوزت سياسة الاحتفاظ المضبوطة.',
                'type' => 'backup',
                'handler' => 'backup:cleanup',
                'schedule_type' => 'daily',
                'schedule_time' => '03:00',
                'timeout' => 900,
                'status' => CronJob::STATUS_DISABLED,
            ],
            [
                'key' => 'cron_executions_prune',
                'name' => 'تنظيف سجلّ تنفيذ المهام',
                'description' => 'يحذف سجلّات التنفيذ الأقدم من 30 يوماً حتى لا ينمو الجدول بلا حدّ.',
                'type' => 'cleanup',
                'handler' => 'cron:prune-executions',
                'parameters' => ['days' => 30],
                'schedule_type' => 'weekly',
                'schedule_time' => '04:00',
                'schedule_day' => 0, // الأحد
                'timeout' => 600,
            ],

            // ============ أوامر مكتوبة بلا جدولة — تُتاح للمسؤول ============
            // كانت مسجّلة في CronRegistry ومنفَّذة في الكود، لكن لا صفّ لها
            // في الجدول، فلم تكن تظهر في الشاشة أصلاً ولا تعمل أبداً
            [
                'key' => 'password_resets_cleanup',
                'name' => 'تنظيف روابط استعادة كلمة المرور',
                'description' => 'يحذف رموز استعادة كلمة المرور المنتهية.',
                'type' => 'cleanup',
                'handler' => 'auth:clear-resets',
                'schedule_type' => 'daily',
                'schedule_time' => '03:30',
                'timeout' => 120,
            ],
            [
                'key' => 'failed_jobs_prune',
                'name' => 'تنظيف مهام الطابور الفاشلة',
                'description' => 'يحذف سجلّات المهام الفاشلة الأقدم من أسبوع.',
                'type' => 'cleanup',
                'handler' => 'queue:prune-failed',
                'parameters' => ['hours' => 168],
                'schedule_type' => 'weekly',
                'schedule_time' => '04:30',
                'schedule_day' => 0, // الأحد
                'timeout' => 300,
            ],
            [
                'key' => 'targets_settlements',
                'name' => 'تسوية أهداف المبيعات والتحصيلات',
                'description' => 'مفتاح الصرف التلقائي لعمولات الأهداف: تفعيلُها هنا هو ما يجعل العمولات تُصرف في يوم التسوية ووقتها المضبوطَين في «إعدادات الأهداف» (سند صرف وقيد لكل موظف). تعمل كل عشر دقائق لكنها لا تصرف إلا في موعدها، ومرّةً واحدة في اليوم.',
                'type' => 'maintenance',
                'handler' => 'targets:process-settlements',
                // كل عشر دقائق لا كل ساعة: الأمرُ يفحص موعدَه بنفسه ويصرف
                // مرّةً في اليوم، فالتكرارُ هنا يحدّد فقط كم يتأخّر الصرفُ عن
                // الوقت المضبوط (عشر دقائق على الأكثر)
                'schedule_type' => 'every_10_minutes',
                'timeout' => 900,
                // تسوية مالية: تفعيلها قرار إداري لا نتيجة بذر
                'status' => CronJob::STATUS_DISABLED,
            ],
            [
                'key' => 'customer_targets_evaluation',
                'name' => 'تقييم أهداف العملاء',
                'description' => 'يعيد احتساب تحقيق أهداف العملاء من حركتهم.',
                'type' => 'job',
                'handler' => EvaluateCustomerTargetsJob::class,
                'schedule_type' => 'daily',
                'schedule_time' => '01:30',
                'timeout' => 1800,
                // يتقاطع مع «التقييم الديناميكي للعملاء»: يُختار أحدهما
                'status' => CronJob::STATUS_DISABLED,
            ],
            [
                'key' => 'daily_employee_report',
                'name' => 'تقرير الموظفين اليومي',
                'description' => 'يرسل ملخّص أداء الموظفين اليومي عبر القناة المضبوطة.',
                'type' => 'report',
                'handler' => 'reports:daily_employee',
                'schedule_type' => 'daily',
                'schedule_time' => '23:50',
                'timeout' => 900,
                // يرسل رسائل خارجية: لا يُفعَّل دون قرار
                'status' => CronJob::STATUS_DISABLED,
            ],
            [
                'key' => 'permissions_sync',
                'name' => 'مزامنة الصلاحيات',
                'description' => 'يزامن قائمة الصلاحيات من الكود إلى قاعدة البيانات.',
                'type' => 'maintenance',
                'handler' => 'permissions:sync',
                'schedule_type' => 'weekly',
                'schedule_time' => '05:00',
                'schedule_day' => 0, // الأحد
                'timeout' => 600,
                // يكتب في جدول الصلاحيات: يُشغَّل عند النشر عادةً لا دورياً
                'status' => CronJob::STATUS_DISABLED,
            ],
            [
                'key' => 'backup_monitor',
                'name' => 'مراقبة صحّة النسخ الاحتياطية',
                'description' => 'يفحص وجود نسخة حديثة وسلامة أحجامها ويطلق تنبيهاً عند الخلل.',
                'type' => 'backup',
                'handler' => 'backup:monitor',
                'schedule_type' => 'daily',
                'schedule_time' => '05:30',
                'timeout' => 300,
                // تابعة لعائلة النسخ الاحتياطي المعطَّلة حتى يُفعّلها المسؤول
                'status' => CronJob::STATUS_DISABLED,
            ],

            // ============ خطّة دورات ERP — معلَنة ولم تُنفَّذ بعد ============
            // مُعالِجاتها مسجّلة في CronRegistry بـ available=false، فتُبذَر
            // معطَّلة ويرفض المُشغّل تشغيلها برسالة صريحة. عند كتابة أمر
            // منها: احذف available=false من سجلّه ثم فعّل المهمة من الشاشة
            ...$this->plannedDefinitions(),
        ];
    }

    /**
     * دورات ERP القياسية التي ينبغي أن يملكها النظام.
     *
     * الجدولة هنا اقتراح مدروس لا إلزام: رُتّبت الساعات حتى لا تتزاحم
     * الدورات الثقيلة في دقيقة واحدة — الإقفالات والتوليد بعد منتصف الليل،
     * والتنبيهات في ساعات الدوام، والتقارير آخر اليوم.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function plannedDefinitions(): array
    {
        $plan = [
            // ---------- الفوترة والمبيعات ----------
            ['points_expiry', 'points:expire-due', 'إسقاط النقاط المنتهية', 'daily', '00:10'],
            ['accounting_recurring_entries', 'accounting:post-recurring-entries', 'ترحيل القيود المتكرّرة', 'daily', '00:15'],
            ['subscriptions_renewal', 'subscriptions:renew-due', 'تجديد الاشتراكات المستحقّة', 'daily', '00:20'],
            ['memberships_expiry', 'memberships:expire', 'إنهاء العضويات المنتهية', 'daily', '00:25'],
            /* الأمر `invoices:generate-recurring` لا وجود له في المشروع،
               ولا في `CronRegistry` — وهي قائمةٌ بيضاء تردّ ما ليس فيها
               قبل `Artisan::call`. فالمهمّة كانت تشير إلى أمرٍ وهميّ:
               لو فُعِّلت لأخفقت، وهي معطَّلة أصلاً. الأمر الحقيقي
               `sales:generate-periodic-invoices`. */
            ['recurring_invoices', 'sales:generate-periodic-invoices', 'توليد الفواتير الدورية', 'daily', '00:30'],
            ['invoices_overdue', 'invoices:mark-overdue', 'تعليم الفواتير المتأخّرة', 'daily', '00:45'],
            ['inventory_costs', 'inventory:recalculate-costs', 'إعادة احتساب متوسّط التكلفة', 'daily', '02:30'],

            // ---------- تنبيهات ساعات الدوام ----------
            ['inventory_low_stock', 'inventory:low-stock-alerts', 'تنبيه حدّ إعادة الطلب', 'daily', '07:00'],
            ['inventory_expiry', 'inventory:expiry-alerts', 'تنبيه الأصناف قاربت انتهاء صلاحيتها', 'daily', '07:15'],
            ['clients_credit_limit', 'clients:credit-limit-alerts', 'تنبيه تجاوز حدّ الائتمان', 'daily', '08:00'],
            ['suppliers_due_payments', 'suppliers:due-payments-alerts', 'تنبيه مستحقّات الموردين', 'daily', '08:15'],
            ['checks_due', 'checks:due-alerts', 'تنبيه الشيكات المستحقّة', 'daily', '08:30'],
            ['hr_document_expiry', 'hr:document-expiry-alerts', 'تنبيه انتهاء وثائق الموظفين', 'daily', '08:45'],
            ['rentals_overdue', 'rentals:overdue-alerts', 'تنبيه تأخّر الإيجارات', 'daily', '09:15'],
            ['invoices_reminders', 'invoices:payment-reminders', 'تذكير العملاء بالفواتير المستحقّة', 'daily', '09:30'],
            ['store_abandoned_carts', 'store:abandoned-cart-reminders', 'تذكير السلال المتروكة', 'daily', '12:00'],
            // `reservations_reminders` انتقلت إلى `definitions()` — أمرُها مكتوب،
            // وبقاؤها هنا يورّثها notify_on_failure=false وجدولةً يوميّة خاطئة
            ['pos_close_sessions', 'pos:auto-close-sessions', 'إغلاق جلسات الكاشير المفتوحة', 'daily', '23:59'],

            // ---------- أسبوعية ----------
            ['purchases_reorder', 'purchases:reorder-suggestions', 'اقتراح أوامر الشراء', 'weekly', '06:00', 0],
            ['clients_inactivity', 'clients:inactivity-alerts', 'تنبيه العملاء الخاملين', 'weekly', '08:30', 0],
            ['activity_logs_prune', 'activity-logs:prune', 'تنظيف سجلّ النشاط', 'weekly', '04:45', 0],

            // ---------- شهرية ----------
            ['accounting_period_close', 'accounting:close-period', 'إقفال الفترة المحاسبية', 'monthly', '01:00', 1],
            ['hr_leave_accrual', 'hr:accrue-leave-balances', 'استحقاق أرصدة الإجازات', 'monthly', '00:30', 1],
            ['clients_aging_report', 'clients:aging-report', 'تقرير أعمار الديون', 'monthly', '07:00', 1],

            // ---------- متكرّرة خلال اليوم ----------
            ['store_inventory_sync', 'store:sync-inventory', 'مزامنة مخزون المتجر', 'hourly', '00:10'],
            ['system_health_check', 'system:health-check', 'فحص صحّة النظام', 'hourly', '00:40'],
        ];

        // الوصف والنوع مصدرهما السجلّ لا نسخة ثانية هنا: تعريف واحد للمهمة
        // يمنع أن يفترق ما تقوله الشاشة عمّا يقوله السجلّ
        return array_map(function (array $row) {
            [$key, $handler, $name, $scheduleType, $time] = $row;
            $meta = CronRegistry::find($handler) ?? [];

            return [
                'key' => $key,
                'name' => $name,
                'description' => $meta['description'] ?? $name,
                'type' => $meta['type'] ?? 'maintenance',
                'handler' => $handler,
                'schedule_type' => $scheduleType,
                'schedule_time' => $time,
                'schedule_day' => $row[5] ?? null,
                'timeout' => 900,
                // مهمة غير مكتوبة لا تُزعج أحداً بتنبيه فشل لو شُغّلت يدوياً
                'notify_on_failure' => false,
            ];
        }, $plan);
    }
}
