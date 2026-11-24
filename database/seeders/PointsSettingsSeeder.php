<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsMissingRows;
use Illuminate\Database\Seeder;

class PointsSettingsSeeder extends Seeder
{
    use SeedsMissingRows;

    /**
     * Run the database seeds.
     *
     * أنواع الحساب:
     * - fixed: نقاط ثابتة لكل عملية
     * - percentage: نسبة من قيمة المعاملة (calculation_value = 1 تعني 1%)
     * - per_amount: نقطة لكل X ريال (calculation_value = 50 تعني نقطة لكل 50 ريال)
     * - tiered: مستويات متدرجة حسب قيمة المعاملة (tiers_config)
     */
    public function run(): void
    {
        // إعدادات النقاط للعملاء
        $clientSettings = [
            // === الفواتير - متدرجة حسب القيمة ===
            [
                'user_type' => 'client',
                'action_type' => 'invoice_created',
                'action_name' => 'إنشاء فاتورة',
                'description' => 'نقاط متدرجة حسب قيمة الفاتورة',
                'points' => 5, // القيمة الافتراضية
                'calculation_type' => 'tiered',
                'calculation_value' => null,
                'min_amount' => 50, // لا نقاط للفواتير أقل من 50 ريال
                'max_amount' => null,
                'max_points' => 100, // سقف 100 نقطة
                'tiers_config' => json_encode([
                    ['min' => 50, 'max' => 100, 'points' => 5],
                    ['min' => 100, 'max' => 300, 'points' => 10],
                    ['min' => 300, 'max' => 500, 'points' => 20],
                    ['min' => 500, 'max' => 1000, 'points' => 35],
                    ['min' => 1000, 'max' => 2000, 'points' => 50],
                    ['min' => 2000, 'max' => null, 'points' => 75],
                ]),
                'is_active' => true,
            ],
            // === دفع فاتورة - نسبة 1% من المبلغ المدفوع ===
            [
                'user_type' => 'client',
                'action_type' => 'invoice_paid',
                'action_name' => 'دفع فاتورة',
                'description' => '1% من قيمة الفاتورة المدفوعة',
                'points' => 10, // القيمة الافتراضية
                'calculation_type' => 'percentage',
                'calculation_value' => 1.0, // 1%
                'min_amount' => 100, // لا نقاط لأقل من 100 ريال
                'max_amount' => null,
                'max_points' => 200, // سقف 200 نقطة
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === سند قبض - نقطة لكل 50 ريال ===
            [
                'user_type' => 'client',
                'action_type' => 'receipt_created',
                'action_name' => 'سند قبض',
                'description' => 'نقطة واحدة لكل 50 ريال',
                'points' => 5, // القيمة الافتراضية
                'calculation_type' => 'per_amount',
                'calculation_value' => 50, // نقطة لكل 50 ريال
                'min_amount' => 50,
                'max_amount' => null,
                'max_points' => 50, // سقف 50 نقطة
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === سند صرف - خصم ثابت ===
            [
                'user_type' => 'client',
                'action_type' => 'payment_voucher',
                'action_name' => 'سند صرف',
                'description' => 'خصم نقاط ثابت',
                'points' => -5,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === مرتجع - خصم نسبة 0.5% ===
            [
                'user_type' => 'client',
                'action_type' => 'return_invoice',
                'action_name' => 'مرتجع',
                'description' => 'خصم 0.5% من قيمة المرتجع',
                'points' => -10,
                'calculation_type' => 'percentage',
                'calculation_value' => 0.5, // 0.5%
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null, // لا حد أقصى للخصم
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === دفعة مستلمة - لكل 100 ريال ===
            [
                'user_type' => 'client',
                'action_type' => 'payment_received',
                'action_name' => 'دفعة مستلمة',
                'description' => 'نقطة لكل 100 ريال مدفوعة',
                'points' => 5,
                'calculation_type' => 'per_amount',
                'calculation_value' => 100,
                'min_amount' => 100,
                'max_amount' => null,
                'max_points' => 30,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === زيارة مكتملة - ثابت ===
            [
                'user_type' => 'client',
                'action_type' => 'visit_completed',
                'action_name' => 'زيارة مكتملة',
                'description' => 'نقاط ثابتة لكل زيارة',
                'points' => 3,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === إحالة عميل - ثابت ===
            [
                'user_type' => 'client',
                'action_type' => 'referral',
                'action_name' => 'إحالة عميل',
                'description' => 'نقاط مكافأة لإحالة عميل جديد',
                'points' => 50,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === طلب إلكتروني مكتمل - متدرج ===
            [
                'user_type' => 'client',
                'action_type' => 'order_completed',
                'action_name' => 'طلب مكتمل',
                'description' => 'نقاط متدرجة حسب قيمة الطلب',
                'points' => 20,
                'calculation_type' => 'tiered',
                'calculation_value' => null,
                'min_amount' => 30,
                'max_amount' => null,
                'max_points' => 150,
                'tiers_config' => json_encode([
                    ['min' => 30, 'max' => 100, 'points' => 10],
                    ['min' => 100, 'max' => 500, 'points' => 25],
                    ['min' => 500, 'max' => 1000, 'points' => 50],
                    ['min' => 1000, 'max' => null, 'points' => 100],
                ]),
                'is_active' => true,
            ],
            // === تقديم تقييم - ثابت ===
            [
                'user_type' => 'client',
                'action_type' => 'review_submitted',
                'action_name' => 'تقديم تقييم',
                'description' => 'نقاط مكافأة لتقديم تقييم أو مراجعة',
                'points' => 10,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === إكمال الملف الشخصي - ثابت ===
            [
                'user_type' => 'client',
                'action_type' => 'profile_completed',
                'action_name' => 'إكمال الملف الشخصي',
                'description' => 'مكافأة لمرة واحدة عند إكمال الملف الشخصي',
                'points' => 25,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === أول عملية شراء - ثابت ===
            [
                'user_type' => 'client',
                'action_type' => 'first_purchase',
                'action_name' => 'أول عملية شراء',
                'description' => 'مكافأة ترحيبية للعميل الجديد',
                'points' => 100,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === مكافأة عيد الميلاد - ثابت ===
            [
                'user_type' => 'client',
                'action_type' => 'birthday_bonus',
                'action_name' => 'مكافأة عيد الميلاد',
                'description' => 'نقاط هدية بمناسبة عيد ميلاد العميل',
                'points' => 50,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تجديد اشتراك - نسبة من قيمة الاشتراك ===
            [
                'user_type' => 'client',
                'action_type' => 'subscription_renewed',
                'action_name' => 'تجديد اشتراك',
                'description' => '2% من قيمة الاشتراك المجدد',
                'points' => 15,
                'calculation_type' => 'percentage',
                'calculation_value' => 2.0,
                'min_amount' => 100,
                'max_amount' => null,
                'max_points' => 100,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === ترقية العضوية - ثابت ===
            [
                'user_type' => 'client',
                'action_type' => 'membership_upgraded',
                'action_name' => 'ترقية العضوية',
                'description' => 'مكافأة عند ترقية مستوى العضوية',
                'points' => 75,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === حجز مؤكد - ثابت ===
            [
                'user_type' => 'client',
                'action_type' => 'reservation_confirmed',
                'action_name' => 'حجز مؤكد',
                'description' => 'نقاط عند تأكيد الحجز',
                'points' => 10,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === إلغاء حجز - خصم ===
            [
                'user_type' => 'client',
                'action_type' => 'reservation_cancelled',
                'action_name' => 'إلغاء حجز',
                'description' => 'خصم نقاط عند إلغاء الحجز',
                'points' => -15,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === شيك مستلم - لكل 500 ريال ===
            [
                'user_type' => 'client',
                'action_type' => 'cheque_received',
                'action_name' => 'شيك مستلم',
                'description' => 'نقطة لكل 500 ريال من قيمة الشيك',
                'points' => 5,
                'calculation_type' => 'per_amount',
                'calculation_value' => 500,
                'min_amount' => 500,
                'max_amount' => null,
                'max_points' => 30,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === إيجار مدفوع - نسبة ===
            [
                'user_type' => 'client',
                'action_type' => 'rental_paid',
                'action_name' => 'إيجار مدفوع',
                'description' => '1% من قيمة الإيجار المدفوع',
                'points' => 10,
                'calculation_type' => 'percentage',
                'calculation_value' => 1.0,
                'min_amount' => 100,
                'max_amount' => null,
                'max_points' => 50,
                'tiers_config' => null,
                'is_active' => true,
            ],
        ];

        // إعدادات النقاط للموظفين
        $employeeSettings = [
            // === الفواتير - لكل 200 ريال ===
            [
                'user_type' => 'employee',
                'action_type' => 'invoice_created',
                'action_name' => 'إنشاء فاتورة',
                'description' => 'نقطة لكل 200 ريال من قيمة الفاتورة',
                'points' => 5,
                'calculation_type' => 'per_amount',
                'calculation_value' => 200,
                'min_amount' => 100,
                'max_amount' => null,
                'max_points' => 25, // سقف 25 نقطة
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === سند قبض - لكل 500 ريال ===
            [
                'user_type' => 'employee',
                'action_type' => 'receipt_created',
                'action_name' => 'سند قبض',
                'description' => 'نقطة لكل 500 ريال محصلة',
                'points' => 3,
                'calculation_type' => 'per_amount',
                'calculation_value' => 500,
                'min_amount' => 500,
                'max_amount' => null,
                'max_points' => 20,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === سند صرف - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'payment_voucher',
                'action_name' => 'سند صرف',
                'description' => 'نقاط ثابتة لكل سند صرف',
                'points' => 2,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تحصيل دفعة - 0.5% من المبلغ ===
            [
                'user_type' => 'employee',
                'action_type' => 'payment_collected',
                'action_name' => 'تحصيل دفعة',
                'description' => '0.5% من المبلغ المحصل',
                'points' => 5,
                'calculation_type' => 'percentage',
                'calculation_value' => 0.5,
                'min_amount' => 200,
                'max_amount' => null,
                'max_points' => 15,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === إتمام زيارة - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'visit_completed',
                'action_name' => 'إتمام زيارة',
                'description' => 'نقاط ثابتة لكل زيارة',
                'points' => 5,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === اكتساب عميل جديد - متدرج حسب أول فاتورة ===
            [
                'user_type' => 'employee',
                'action_type' => 'new_client_acquired',
                'action_name' => 'اكتساب عميل جديد',
                'description' => 'نقاط متدرجة حسب قيمة أول فاتورة',
                'points' => 25,
                'calculation_type' => 'tiered',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => 100,
                'tiers_config' => json_encode([
                    ['min' => 0, 'max' => 500, 'points' => 25],
                    ['min' => 500, 'max' => 1000, 'points' => 40],
                    ['min' => 1000, 'max' => 5000, 'points' => 60],
                    ['min' => 5000, 'max' => null, 'points' => 100],
                ]),
                'is_active' => true,
            ],
            // === مكافأة أداء - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'performance_bonus',
                'action_name' => 'مكافأة أداء',
                'description' => 'نقاط مكافأة على الأداء المتميز',
                'points' => 30,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تأخر عن العمل - خصم ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'late_arrival',
                'action_name' => 'تأخر عن العمل',
                'description' => 'خصم نقاط ثابت',
                'points' => -10,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === غياب - خصم ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'absence',
                'action_name' => 'غياب',
                'description' => 'خصم نقاط عند الغياب غير المبرر',
                'points' => -20,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === حضور في الموعد - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'attendance_on_time',
                'action_name' => 'حضور في الموعد',
                'description' => 'مكافأة يومية للحضور في الموعد',
                'points' => 2,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === إتمام مهمة - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'task_completed',
                'action_name' => 'إتمام مهمة',
                'description' => 'نقاط لكل مهمة مكتملة',
                'points' => 5,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تحقيق هدف المبيعات - متدرج ===
            [
                'user_type' => 'employee',
                'action_type' => 'sales_target_achieved',
                'action_name' => 'تحقيق هدف المبيعات',
                'description' => 'نقاط متدرجة حسب نسبة تحقيق الهدف',
                'points' => 50,
                'calculation_type' => 'tiered',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => 200,
                'tiers_config' => json_encode([
                    ['min' => 80, 'max' => 100, 'points' => 50],   // 80-100%
                    ['min' => 100, 'max' => 120, 'points' => 100], // 100-120%
                    ['min' => 120, 'max' => 150, 'points' => 150], // 120-150%
                    ['min' => 150, 'max' => null, 'points' => 200], // 150%+
                ]),
                'is_active' => true,
            ],
            // === معالجة مرتجع - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'return_processed',
                'action_name' => 'معالجة مرتجع',
                'description' => 'نقاط لمعالجة عملية مرتجع',
                'points' => 3,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === جرد مخزون - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'inventory_count',
                'action_name' => 'جرد مخزون',
                'description' => 'نقاط للمشاركة في جرد المخزون',
                'points' => 15,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === إتمام تدريب - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'training_completed',
                'action_name' => 'إتمام تدريب',
                'description' => 'مكافأة لإتمام دورة تدريبية',
                'points' => 20,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === رضا العميل - متدرج حسب التقييم ===
            [
                'user_type' => 'employee',
                'action_type' => 'customer_satisfaction',
                'action_name' => 'رضا العميل',
                'description' => 'نقاط حسب تقييم العميل (1-5)',
                'points' => 5,
                'calculation_type' => 'tiered',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => 15,
                'tiers_config' => json_encode([
                    ['min' => 1, 'max' => 3, 'points' => 2],
                    ['min' => 3, 'max' => 4, 'points' => 5],
                    ['min' => 4, 'max' => 5, 'points' => 10],
                    ['min' => 5, 'max' => null, 'points' => 15],
                ]),
                'is_active' => true,
            ],
            // === شكوى العميل - خصم ===
            [
                'user_type' => 'employee',
                'action_type' => 'customer_complaint',
                'action_name' => 'شكوى عميل',
                'description' => 'خصم نقاط عند تلقي شكوى عميل',
                'points' => -15,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === عمل إضافي - لكل ساعة ===
            [
                'user_type' => 'employee',
                'action_type' => 'overtime_hours',
                'action_name' => 'عمل إضافي',
                'description' => 'نقاط لكل ساعة عمل إضافية',
                'points' => 3,
                'calculation_type' => 'per_amount',
                'calculation_value' => 1, // لكل ساعة
                'min_amount' => 1,
                'max_amount' => 8, // حد أقصى 8 ساعات
                'max_points' => 24,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === إنجاز قبل الموعد - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'early_completion',
                'action_name' => 'إنجاز قبل الموعد',
                'description' => 'مكافأة لإنجاز المهمة قبل الموعد المحدد',
                'points' => 10,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تأخر في التسليم - خصم ===
            [
                'user_type' => 'employee',
                'action_type' => 'late_delivery',
                'action_name' => 'تأخر في التسليم',
                'description' => 'خصم نقاط للتأخر في تسليم المهام',
                'points' => -5,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === مبيعات نقدية - نسبة ===
            [
                'user_type' => 'employee',
                'action_type' => 'cash_sale',
                'action_name' => 'مبيعات نقدية',
                'description' => '0.25% من قيمة المبيعات النقدية',
                'points' => 2,
                'calculation_type' => 'percentage',
                'calculation_value' => 0.25,
                'min_amount' => 100,
                'max_amount' => null,
                'max_points' => 20,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تحصيل ديون متأخرة - نسبة ===
            [
                'user_type' => 'employee',
                'action_type' => 'overdue_collection',
                'action_name' => 'تحصيل ديون متأخرة',
                'description' => '1% من قيمة الديون المتأخرة المحصلة',
                'points' => 10,
                'calculation_type' => 'percentage',
                'calculation_value' => 1.0,
                'min_amount' => 500,
                'max_amount' => null,
                'max_points' => 50,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تعاون بين الأقسام - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'collaboration',
                'action_name' => 'تعاون بين الأقسام',
                'description' => 'نقاط للتعاون مع أقسام أخرى في مشروع مشترك',
                'points' => 10,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],

            // ╔════════════════════════════════════════════════════════╗
            // ║  إجراءات الموظفين الجديدة - إيجابية                   ║
            // ╚════════════════════════════════════════════════════════╝

            // === تسجيل ملاحظة - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'note_created',
                'action_name' => 'تسجيل ملاحظة',
                'description' => 'نقاط لتسجيل ملاحظة مفيدة أو تقرير متابعة',
                'points' => 2,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تقديم تقرير - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'report_submitted',
                'action_name' => 'تقديم تقرير',
                'description' => 'نقاط مكافأة لتقديم تقرير عمل',
                'points' => 5,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تقديم اقتراح تحسين - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'suggestion_submitted',
                'action_name' => 'تقديم اقتراح تحسين',
                'description' => 'نقاط لتقديم اقتراح لتحسين العمل أو الخدمات',
                'points' => 10,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === إرشاد زميل - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'mentoring',
                'action_name' => 'إرشاد زميل',
                'description' => 'نقاط لتدريب أو إرشاد زميل جديد',
                'points' => 15,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === أفضل موظف شهري - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'monthly_top_performer',
                'action_name' => 'أفضل موظف شهري',
                'description' => 'مكافأة شهرية لأفضل موظف أداءً',
                'points' => 50,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === حل مشكلة عميل - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'client_issue_resolved',
                'action_name' => 'حل مشكلة عميل',
                'description' => 'نقاط لحل مشكلة عميل بنجاح',
                'points' => 8,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تغطية وردية زميل - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'shift_coverage',
                'action_name' => 'تغطية وردية زميل',
                'description' => 'نقاط لتغطية وردية زميل غائب',
                'points' => 10,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],

            // ╔════════════════════════════════════════════════════════╗
            // ║  إجراءات الموظفين الجديدة - سلبية (خصم)               ║
            // ╚════════════════════════════════════════════════════════╝

            // === خطأ في المبيعات - خصم ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'sales_error',
                'action_name' => 'خطأ في المبيعات',
                'description' => 'خصم نقاط عند ارتكاب خطأ في فاتورة أو طلب',
                'points' => -8,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === تسبب في مرتجع - نسبة من قيمة المرتجع ===
            [
                'user_type' => 'employee',
                'action_type' => 'return_caused',
                'action_name' => 'تسبب في مرتجع',
                'description' => 'خصم 0.5% من قيمة المرتجع الذي تسبب فيه الموظف',
                'points' => -5,
                'calculation_type' => 'percentage',
                'calculation_value' => 0.5,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === مخالفة سياسات - خصم ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'policy_violation',
                'action_name' => 'مخالفة سياسات',
                'description' => 'خصم نقاط عند مخالفة سياسات أو إجراءات العمل',
                'points' => -25,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === عجز في الخزينة - لكل 100 ريال ===
            [
                'user_type' => 'employee',
                'action_type' => 'cash_shortage',
                'action_name' => 'عجز في الخزينة',
                'description' => 'خصم نقطة لكل 100 ريال عجز في الخزينة',
                'points' => -1,
                'calculation_type' => 'per_amount',
                'calculation_value' => 100,
                'min_amount' => 10,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === فقدان عميل - خصم ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'client_lost',
                'action_name' => 'فقدان عميل',
                'description' => 'خصم نقاط عند فقدان عميل بسبب سوء الخدمة',
                'points' => -20,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === عدم تحقيق الهدف - خصم ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'target_missed',
                'action_name' => 'عدم تحقيق الهدف',
                'description' => 'خصم نقاط عند عدم تحقيق الهدف الشهري',
                'points' => -15,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === مخالفة سلامة - خصم ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'safety_violation',
                'action_name' => 'مخالفة سلامة',
                'description' => 'خصم نقاط عند مخالفة إجراءات السلامة',
                'points' => -10,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === إكمال خط السير اليومي - ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'itinerary_completed',
                'action_name' => 'إكمال خط السير اليومي',
                'description' => 'نقاط مكافأة عند إكمال جميع زيارات خط السير لليوم',
                'points' => 10,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
            // === خط سير غير مكتمل - خصم ثابت ===
            [
                'user_type' => 'employee',
                'action_type' => 'itinerary_incomplete',
                'action_name' => 'خط سير غير مكتمل',
                'description' => 'خصم نقاط عند وجود زيارات غير مكتملة في خط السير',
                'points' => -5,
                'calculation_type' => 'fixed',
                'calculation_value' => null,
                'min_amount' => null,
                'max_amount' => null,
                'max_points' => null,
                'tiers_config' => null,
                'is_active' => true,
            ],
        ];

        // دمج جميع الإعدادات
        $allSettings = array_merge($clientSettings, $employeeSettings);

        // المفتاح الطبيعي هو الفهرس الفريد نفسه: `points_settings_unique`
        $seeded = $this->seedMissingRows('points_settings', $allSettings, ['user_type', 'action_type', 'branch_id']);

        $this->reportSeeded($seeded, 'إعدادات النقاط');
    }
}
