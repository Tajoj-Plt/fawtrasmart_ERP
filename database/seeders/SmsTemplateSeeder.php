<?php

namespace Database\Seeders;

use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * كتالوج قوالب الرسائل النصية.
 *
 * كانت القوالب سبعة تغطّي الفاتورة والدفعة والترحيب، وما عداها من
 * أحداث النظام — من صرف الراتب إلى انتهاء العضوية إلى رمز التحقّق —
 * بلا نصّ يُرسل. هنا الكتالوج كاملاً مرتّباً بتصنيفاته.
 *
 * **قواعد الصياغة المتّبعة في كل نصّ أدناه:**
 *
 * - العربية تُرسل بترميز Unicode، فحدّ الرسالة الواحدة 70 حرفاً لا 160.
 *   كل نصّ هنا مقيسٌ ليقع في جزء أو جزأين — الثالث كلفةٌ بلا فائدة.
 * - يبدأ النصّ بالمعلومة لا بالتحية: العميل يقرأ أوّل سطر في الإشعار.
 * - اسم المنشأة في آخر الرسالة لا أوّلها، فاسم المرسِل يسبقها أصلاً.
 * - لا رموز تعبيرية: تُقبل عربياً بلا كلفة إضافية لكنها تُفقد الرسالة
 *   رسميّتها في السياق المالي.
 *
 * **يُشغَّل بأمان أكثر من مرّة:** القالب يُطابَق برمزه، ونصُّ القالب
 * الموجود لا يُلمس — تعديل المستخدم أولى من القيمة الافتراضية. تُحدَّث
 * البيانات الوصفية وحدها (التصنيف والوصف والترتيب).
 *
 *     php artisan db:seed --class=SmsTemplateSeeder
 */
class SmsTemplateSeeder extends Seeder
{
    /**
     * الكتالوج: التصنيف ← قوالبه.
     *
     * كل قالب: [الرمز، الاسم، الحدث، الوصف، النصّ] و`system` اختياري
     * للقوالب التي يستدعيها الكود برمزها فلا تُحذف.
     */
    protected const CATALOG = [

        // ==================== المبيعات والفواتير ====================
        'sales' => [
            [
                'code' => 'invoice_created',
                'name' => 'إشعار فاتورة جديدة',
                'event' => 'invoice.created',
                'description' => 'يُرسل فور اعتماد فاتورة المبيعات، ليصل العميل رقمها ومبلغها.',
                'body' => 'عميلنا {customer}، صدرت فاتورتك رقم {number} بمبلغ {amount} {currency}. شكراً لثقتك بـ {business}.',
            ],
            [
                'code' => 'invoice_updated',
                'name' => 'تعديل فاتورة',
                'event' => 'invoice.updated',
                'description' => 'يُعلم العميل بتغيّر بيانات فاتورة سبق أن استلمها.',
                'body' => 'عُدّلت الفاتورة {number}. المبلغ بعد التعديل {amount} {currency}. {business}',
            ],
            [
                'code' => 'invoice_cancelled',
                'name' => 'إلغاء فاتورة',
                'event' => 'invoice.cancelled',
                'description' => 'يُرسل عند إلغاء فاتورة، فلا يبقى لدى العميل مطالبة ملغاة.',
                'body' => 'أُلغيت الفاتورة {number} بمبلغ {amount} {currency}، ولا مطالبة عليك بشأنها. {business}',
            ],
            [
                'code' => 'invoice_settled',
                'name' => 'سداد الفاتورة كاملة',
                'event' => 'invoice.settled',
                'description' => 'إغلاق المطالبة: يؤكّد للعميل أن الفاتورة لم يعد عليها متبقٍّ.',
                'body' => 'سُدّدت الفاتورة {number} بالكامل. لا مستحقات عليك. شكراً لك. {business}',
            ],
            [
                'code' => 'due_reminder',
                'name' => 'تذكير قبل الاستحقاق',
                'event' => 'invoice.due',
                'description' => 'تنبيه مبكّر قبل حلول موعد السداد بأيام.',
                'body' => 'تذكير: الفاتورة {number} بمبلغ {amount} {currency} تستحق بتاريخ {due_date}. {business}',
            ],
            [
                'code' => 'due_today',
                'name' => 'الاستحقاق اليوم',
                'event' => 'invoice.due',
                'description' => 'يُرسل صباح يوم الاستحقاق — آخر تنبيه قبل التأخّر.',
                'body' => 'تستحق اليوم الفاتورة {number} بمبلغ {amount} {currency}. نرجو إتمام السداد. {business}',
            ],
            [
                'code' => 'overdue_notice',
                'name' => 'تنبيه تأخّر سداد',
                'event' => 'invoice.overdue',
                'description' => 'صياغة حازمة ومهذّبة بعد تجاوز موعد الاستحقاق.',
                'body' => 'عميلنا {customer}، تجاوزت الفاتورة {number} موعد استحقاقها بـ {days} يوماً. المتبقي {remaining} {currency}. {business}',
            ],
            [
                'code' => 'overdue_final',
                'name' => 'إشعار أخير قبل الإيقاف',
                'event' => 'invoice.overdue',
                'description' => 'آخر تنبيه قبل إيقاف التعامل — يُستعمل بحساب.',
                'body' => 'إشعار أخير: المستحق عليك {remaining} {currency} عن الفاتورة {number}. يُرجى السداد قبل {due_date} تفادياً لإيقاف الخدمة. {business}',
            ],
            [
                'code' => 'quote_sent',
                'name' => 'إرسال عرض سعر',
                'event' => 'quote.sent',
                'description' => 'يُبلغ العميل بجاهزية عرض السعر ورقمه.',
                'body' => 'عميلنا {customer}، أرسلنا عرض السعر {number} بمبلغ {amount} {currency}، وهو ساري حتى {due_date}. {business}',
            ],
            [
                'code' => 'quote_accepted',
                'name' => 'تأكيد قبول عرض السعر',
                'event' => 'quote.accepted',
                'description' => 'إشعار بدء التنفيذ بعد موافقة العميل على العرض.',
                'body' => 'استلمنا موافقتك على العرض {number}، وبدأنا التنفيذ. شكراً لك. {business}',
            ],
            [
                'code' => 'quote_expiring',
                'name' => 'قرب انتهاء عرض السعر',
                'event' => 'quote.expiring',
                'description' => 'تذكير قبل انتهاء سريان العرض بأيام.',
                'body' => 'ينتهي سريان العرض {number} بعد {days} أيام. للتأكيد تواصل معنا على {phone}. {business}',
            ],
            [
                'code' => 'sales_order_created',
                'name' => 'تأكيد طلب بيع',
                'event' => 'order.created',
                'description' => 'يؤكّد استلام الطلب قبل إصدار الفاتورة.',
                'body' => 'استلمنا طلبك رقم {number}، وسنوافيك بمستجداته. {business}',
            ],
            [
                'code' => 'sales_return',
                'name' => 'تأكيد مرتجع مبيعات',
                'event' => 'return.created',
                'description' => 'يؤكّد قبول المرتجع وقيمته.',
                'body' => 'قُبل مرتجعك على الفاتورة {number} بقيمة {amount} {currency}. {business}',
            ],
        ],

        // ==================== المدفوعات والتحصيل ====================
        'payments' => [
            [
                'code' => 'payment_received',
                'name' => 'استلام دفعة',
                'event' => 'payment.received',
                'description' => 'يؤكّد استلام المبلغ ويبيّن المتبقي — أكثر الرسائل طلباً.',
                'body' => 'استلمنا دفعتك بمبلغ {amount} {currency} على الفاتورة {number}. المتبقي {remaining} {currency}. {business}',
            ],
            [
                'code' => 'payment_partial',
                'name' => 'دفعة جزئية',
                'event' => 'payment.received',
                'description' => 'صياغة تركّز على المتبقي حين لا تُغطّي الدفعة الفاتورة.',
                'body' => 'شكراً {customer}. سُجّلت دفعة {amount} {currency}، ويتبقّى {remaining} {currency} تستحق في {due_date}. {business}',
            ],
            [
                'code' => 'payment_failed',
                'name' => 'فشل عملية دفع',
                'event' => 'payment.failed',
                'description' => 'يُعلم العميل بعدم اكتمال الدفع كي يعيد المحاولة.',
                'body' => 'لم تكتمل عملية الدفع بمبلغ {amount} {currency}. يُرجى إعادة المحاولة أو التواصل على {phone}. {business}',
            ],
            [
                'code' => 'payment_refunded',
                'name' => 'ردّ مبلغ',
                'event' => 'payment.refunded',
                'description' => 'يؤكّد تحويل المبلغ المرتجع ومدّة وصوله.',
                'body' => 'رُدّ إليك مبلغ {amount} {currency} عن الفاتورة {number}، ويصل خلال {days} أيام عمل. {business}',
            ],
            [
                'code' => 'receipt_issued',
                'name' => 'سند قبض',
                'event' => 'receipt.issued',
                'description' => 'إشعار بإصدار سند قبض باسم العميل.',
                'body' => 'صدر سند قبض رقم {number} بمبلغ {amount} {currency} بتاريخ {date}. {business}',
            ],
            [
                'code' => 'statement_ready',
                'name' => 'جاهزية كشف الحساب',
                'event' => 'statement.ready',
                'description' => 'يُرسل دورياً ليطّلع العميل على حركة حسابه.',
                'body' => 'كشف حسابك حتى {date} جاهز. الرصيد {balance} {currency}. للاستفسار {phone}. {business}',
            ],
            [
                'code' => 'balance_due',
                'name' => 'رصيد مستحق',
                'event' => 'balance.due',
                'description' => 'مطالبة بإجمالي المستحق حين تتعدّد الفواتير.',
                'body' => 'عميلنا {customer}، إجمالي المستحق عليك {remaining} {currency} عن {count} فواتير. {business}',
            ],
            [
                'code' => 'credit_limit_reached',
                'name' => 'بلوغ حدّ الائتمان',
                'event' => 'credit.limit',
                'description' => 'تنبيه قبل توقّف البيع الآجل.',
                'body' => 'بلغ رصيدك حدّ الائتمان المسموح ({balance} {currency}). يُرجى السداد لمتابعة الشراء الآجل. {business}',
            ],
        ],

        // ==================== المشتريات والموردون ====================
        'purchases' => [
            [
                'code' => 'purchase_order_sent',
                'name' => 'أمر شراء إلى مورّد',
                'event' => 'purchase.ordered',
                'description' => 'يُبلغ المورّد بصدور أمر شراء باسمه.',
                'body' => 'صدر أمر الشراء {number} بمبلغ {amount} {currency}، والتسليم المطلوب بتاريخ {due_date}. {business}',
            ],
            [
                'code' => 'purchase_received',
                'name' => 'تأكيد استلام بضاعة',
                'event' => 'purchase.received',
                'description' => 'يؤكّد للمورّد استلام الشحنة ومطابقتها.',
                'body' => 'استلمنا شحنتكم على أمر الشراء {number} بتاريخ {date}. شكراً لتعاونكم. {business}',
            ],
            [
                'code' => 'supplier_payment',
                'name' => 'إشعار دفع لمورّد',
                'event' => 'supplier.payment',
                'description' => 'يُعلم المورّد بتحويل مستحقاته.',
                'body' => 'حُوّل إليكم مبلغ {amount} {currency} عن الفاتورة {number} بتاريخ {date}. {business}',
            ],
        ],

        // ==================== العملاء ====================
        'clients' => [
            [
                'code' => 'customer_welcome',
                'name' => 'ترحيب بعميل جديد',
                'event' => 'customer.created',
                'description' => 'أول رسالة يستلمها العميل بعد تسجيله.',
                'body' => 'أهلاً بك {customer} في {business}. سعداء بانضمامك، وفريقنا في خدمتك على {phone}.',
            ],
            [
                'code' => 'customer_reactivated',
                'name' => 'إعادة تنشيط عميل',
                'event' => 'customer.reactivated',
                'description' => 'ترحيب بعودة عميل بعد انقطاع.',
                'body' => 'سعدنا بعودتك {customer}. حسابك لدى {business} مفعَّل، وفريقنا جاهز لخدمتك.',
            ],
            [
                'code' => 'customer_inactive',
                'name' => 'استعادة عميل خامل',
                'event' => 'customer.inactive',
                'description' => 'رسالة استرجاع بعد مدّة بلا تعامل.',
                'body' => 'اشتقنا لك {customer}. مضى {days} يوماً على آخر زيارة، وننتظرك في {business}.',
            ],
            [
                'code' => 'feedback_request',
                'name' => 'طلب تقييم',
                'event' => 'customer.feedback',
                'description' => 'يُرسل بعد إتمام الخدمة بمدّة قصيرة.',
                'body' => 'رأيك يهمّنا {customer}. كيف كانت تجربتك معنا؟ شاركنا عبر {link}. {business}',
            ],
        ],

        // ==================== المواعيد والزيارات ====================
        'appointments' => [
            [
                'code' => 'appointment_booked',
                'name' => 'تأكيد حجز موعد',
                'event' => 'appointment.booked',
                'description' => 'يؤكّد الموعد فور حجزه بتاريخه ووقته.',
                'body' => 'تأكّد موعدك {date} الساعة {time} لدى {business}. للتعديل اتصل على {phone}.',
            ],
            [
                'code' => 'appointment_reminder',
                'name' => 'تذكير بموعد',
                'event' => 'appointment.reminder',
                'description' => 'تذكير قبل الموعد بيوم أو ساعات — يقلّل عدم الحضور.',
                'body' => 'تذكير بموعدك غداً {date} الساعة {time} في {location}. {business}',
            ],
            [
                'code' => 'appointment_rescheduled',
                'name' => 'تغيير موعد',
                'event' => 'appointment.rescheduled',
                'description' => 'يُبلغ بالموعد الجديد بعد التأجيل.',
                'body' => 'أُجّل موعدك إلى {date} الساعة {time}. نعتذر عن التغيير. {business}',
            ],
            [
                'code' => 'appointment_cancelled',
                'name' => 'إلغاء موعد',
                'event' => 'appointment.cancelled',
                'description' => 'يؤكّد الإلغاء ويفتح باب إعادة الحجز.',
                'body' => 'أُلغي موعدك في {date}. لإعادة الحجز تواصل على {phone}. {business}',
            ],
            [
                'code' => 'visit_scheduled',
                'name' => 'جدولة زيارة',
                'event' => 'visit.scheduled',
                'description' => 'إبلاغ العميل بموعد زيارة المندوب.',
                'body' => 'سيزورك مندوبنا {employee} يوم {date} الساعة {time}. {business}',
            ],
            [
                'code' => 'visit_completed',
                'name' => 'إتمام زيارة',
                'event' => 'visit.completed',
                'description' => 'شكر بعد انتهاء الزيارة.',
                'body' => 'شكراً لاستقبالك مندوبنا اليوم. نسعد بخدمتك دائماً. {business}',
            ],
        ],

        // ==================== المخزون والتسليم ====================
        'stock' => [
            [
                'code' => 'delivery_ready',
                'name' => 'جاهزية الطلب للاستلام',
                'event' => 'delivery.ready',
                'description' => 'يُبلغ العميل بجاهزية طلبه ومكان الاستلام.',
                'body' => 'عميلنا {customer}، طلبك {number} جاهز للاستلام من {location}. {business}',
            ],
            [
                'code' => 'delivery_dispatched',
                'name' => 'خروج الشحنة',
                'event' => 'delivery.dispatched',
                'description' => 'إشعار الشحن مع رقم التتبّع.',
                'body' => 'شُحن طلبك {number}. رقم التتبّع {tracking}، والتسليم خلال {days} أيام. {business}',
            ],
            [
                'code' => 'delivery_delivered',
                'name' => 'تأكيد التسليم',
                'event' => 'delivery.delivered',
                'description' => 'يؤكّد وصول الطلب ويغلق دورة الشحن.',
                'body' => 'سُلّم طلبك {number} بتاريخ {date}. نتمنى أن ينال رضاك. {business}',
            ],
            [
                'code' => 'stock_available',
                'name' => 'توفّر صنف مطلوب',
                'event' => 'stock.available',
                'description' => 'يُرسل لمن طلب صنفاً كان نافداً.',
                'body' => 'توفّر لدينا {item} الذي طلبته. سارع بالحجز على {phone}. {business}',
            ],
        ],

        // ==================== الموارد البشرية ====================
        'hr' => [
            [
                'code' => 'salary_paid',
                'name' => 'إشعار صرف راتب',
                'event' => 'employee.salary',
                'description' => 'يُرسل للموظف عند تحويل راتب الشهر.',
                'body' => 'عزيزنا {employee}، حُوّل راتب شهر {date} بمبلغ {amount} {currency}. {business}',
            ],
            [
                'code' => 'advance_approved',
                'name' => 'قبول طلب سلفة',
                'event' => 'employee.advance',
                'description' => 'يُبلغ الموظف بالموافقة على سلفته.',
                'body' => 'وُوفق على طلب السلفة بمبلغ {amount} {currency}، وسيُصرف مع راتب {date}. {business}',
            ],
            [
                'code' => 'advance_rejected',
                'name' => 'رفض طلب سلفة',
                'event' => 'employee.advance',
                'description' => 'إبلاغ بالرفض مع إحالة إلى الموارد البشرية.',
                'body' => 'نعتذر، لم يُقبل طلب السلفة. للاستفسار راجع الموارد البشرية. {business}',
            ],
            [
                'code' => 'contract_expiring',
                'name' => 'قرب انتهاء العقد',
                'event' => 'employee.contract',
                'description' => 'تنبيه الموظف قبل انتهاء عقده بمدّة.',
                'body' => 'عزيزنا {employee}، ينتهي عقدك بتاريخ {due_date}. يُرجى مراجعة الموارد البشرية. {business}',
            ],
            [
                'code' => 'attendance_absence',
                'name' => 'رصد غياب أو تأخّر',
                'event' => 'employee.attendance',
                'description' => 'تنبيه فوري عند تسجيل غياب غير مبرّر.',
                'body' => 'سُجّل عليك غياب بتاريخ {date}. للتوضيح راجع مسؤولك المباشر. {business}',
            ],
        ],

        // ==================== الولاء والنقاط ====================
        'loyalty' => [
            [
                'code' => 'points_earned',
                'name' => 'اكتساب نقاط',
                'event' => 'points.earned',
                'description' => 'يُرسل بعد عملية شراء تُكسب نقاطاً.',
                'body' => 'كسبت {points} نقطة من مشترياتك. رصيدك الآن {balance} نقطة. {business}',
            ],
            [
                'code' => 'points_redeemed',
                'name' => 'استبدال نقاط',
                'event' => 'points.redeemed',
                'description' => 'يؤكّد خصم النقاط ويبيّن الرصيد بعده.',
                'body' => 'استبدلت {points} نقطة بقيمة {amount} {currency}. رصيدك المتبقي {balance} نقطة. {business}',
            ],
            [
                'code' => 'points_expiring',
                'name' => 'قرب انتهاء النقاط',
                'event' => 'points.expiring',
                'description' => 'تنبيه قبل إسقاط النقاط بمدّة كافية للاستفادة.',
                'body' => 'تنتهي {points} نقطة من رصيدك بتاريخ {due_date}. استفد منها قبل ذلك. {business}',
            ],
            [
                'code' => 'tier_upgraded',
                'name' => 'الترقّي لفئة أعلى',
                'event' => 'tier.upgraded',
                'description' => 'تهنئة عند بلوغ فئة ولاء أعلى.',
                'body' => 'تهانينا {customer}! ترقّيت إلى فئة {tier} بمزاياها الجديدة. {business}',
            ],
        ],

        // ==================== العضويات والاشتراكات ====================
        'memberships' => [
            [
                'code' => 'membership_activated',
                'name' => 'تفعيل عضوية',
                'event' => 'membership.activated',
                'description' => 'يؤكّد بدء العضوية وتاريخ انتهائها.',
                'body' => 'فُعّلت عضويتك {tier} حتى {due_date}. أهلاً بك في {business}.',
            ],
            [
                'code' => 'membership_expiring',
                'name' => 'قرب انتهاء العضوية',
                'event' => 'membership.expiring',
                'description' => 'تذكير بالتجديد قبل الانتهاء بأيام.',
                'body' => 'تنتهي عضويتك بعد {days} أيام. جدّدها الآن لتستمرّ مزاياك. {business}',
            ],
            [
                'code' => 'membership_expired',
                'name' => 'انتهاء العضوية',
                'event' => 'membership.expired',
                'description' => 'إشعار الانتهاء مع دعوة للتجديد.',
                'body' => 'انتهت عضويتك بتاريخ {date}. للتجديد تواصل على {phone}. {business}',
            ],
            [
                'code' => 'subscription_renewed',
                'name' => 'تجديد اشتراك',
                'event' => 'subscription.renewed',
                'description' => 'يؤكّد التجديد ومدّته والمبلغ.',
                'body' => 'جُدّد اشتراكك حتى {due_date} بمبلغ {amount} {currency}. شكراً لاستمرارك معنا. {business}',
            ],
        ],

        // ==================== الإيجارات والحجوزات ====================
        'rentals' => [
            [
                'code' => 'rental_started',
                'name' => 'بدء عقد إيجار',
                'event' => 'rental.started',
                'description' => 'يؤكّد سريان العقد وتاريخ انتهائه.',
                'body' => 'بدأ عقد الإيجار {number} من {date} حتى {due_date}. {business}',
            ],
            [
                'code' => 'rental_due',
                'name' => 'استحقاق دفعة إيجار',
                'event' => 'rental.due',
                'description' => 'تذكير بدفعة الإيجار قبل موعدها.',
                'body' => 'تستحق دفعة الإيجار {amount} {currency} بتاريخ {due_date} عن العقد {number}. {business}',
            ],
            [
                'code' => 'rental_ending',
                'name' => 'قرب انتهاء عقد الإيجار',
                'event' => 'rental.ending',
                'description' => 'تنبيه قبل الانتهاء لبحث التجديد.',
                'body' => 'ينتهي عقد الإيجار {number} بعد {days} يوماً. للتجديد تواصل على {phone}. {business}',
            ],
            [
                'code' => 'reservation_confirmed',
                'name' => 'تأكيد حجز',
                'event' => 'reservation.confirmed',
                'description' => 'يؤكّد الحجز بتاريخه ومكانه.',
                'body' => 'تأكّد حجزك رقم {number} بتاريخ {date} الساعة {time} في {location}. {business}',
            ],
        ],

        // ==================== المتجر والشحن ====================
        'store' => [
            [
                'code' => 'store_order_placed',
                'name' => 'طلب جديد من المتجر',
                'event' => 'store.order_placed',
                'description' => 'يؤكّد استلام الطلب الإلكتروني.',
                'body' => 'استلمنا طلبك {number} بمبلغ {amount} {currency}، ونجهّزه الآن. {business}',
            ],
            [
                'code' => 'store_order_shipped',
                'name' => 'شحن طلب المتجر',
                'event' => 'store.order_shipped',
                'description' => 'إشعار الشحن مع رابط التتبّع.',
                'body' => 'شُحن طلبك {number}. تتبّعه برقم {tracking} عبر {link}. {business}',
            ],
            [
                'code' => 'cart_abandoned',
                'name' => 'تذكير بسلة متروكة',
                'event' => 'store.cart_abandoned',
                'description' => 'استرجاع سلة لم تُكمَل — يُرسل بعد ساعات.',
                'body' => 'سلّتك ما زالت بانتظارك في {business}. أكمل طلبك عبر {link}.',
            ],
        ],

        // ==================== الأمان والتحقق ====================
        'security' => [
            [
                'code' => 'otp_code',
                'name' => 'رمز التحقّق',
                'event' => 'auth.otp',
                'system' => true,
                'description' => 'رمز الدخول لمرّة واحدة — يستدعيه النظام برمزه، فلا يُحذف.',
                'body' => 'رمز التحقّق: {code}. صالح {minutes} دقائق ولا تشاركه مع أحد. {business}',
            ],
            [
                'code' => 'password_reset',
                'name' => 'استعادة كلمة المرور',
                'event' => 'auth.password_reset',
                'system' => true,
                'description' => 'رمز إعادة تعيين كلمة المرور.',
                'body' => 'رمز استعادة كلمة المرور: {code}. إن لم تطلبه فتجاهل الرسالة. {business}',
            ],
            [
                'code' => 'new_login',
                'name' => 'دخول من جهاز جديد',
                'event' => 'auth.new_login',
                'description' => 'تنبيه أمني عند تسجيل دخول غير معتاد.',
                'body' => 'سُجّل دخول لحسابك من جهاز جديد بتاريخ {date} الساعة {time}. إن لم يكن أنت فتواصل على {phone}. {business}',
            ],
        ],

        // ==================== التسويق والمناسبات ====================
        'marketing' => [
            [
                'code' => 'campaign_offer',
                'name' => 'عرض ترويجي',
                'event' => 'marketing.campaign',
                'description' => 'قالب حملة عام — يُعدَّل نصّه لكل حملة.',
                'body' => 'خصم {discount}% على {item} حتى {due_date}. اطلب الآن من {business}.',
            ],
            [
                'code' => 'birthday_greeting',
                'name' => 'تهنئة بعيد الميلاد',
                'event' => 'marketing.occasion',
                'description' => 'تهنئة سنوية مع هدية اختيارية.',
                'body' => 'كل عام وأنت بخير {customer}. لك منّا خصم {discount}% اليوم. {business}',
            ],
            [
                'code' => 'eid_greeting',
                'name' => 'تهنئة العيد',
                'event' => 'marketing.occasion',
                'description' => 'تهنئة موسمية تُرسل للقائمة كاملة.',
                'body' => 'عيدكم مبارك، وكل عام وأنتم بخير. {business}',
            ],
            [
                'code' => 'national_day_greeting',
                'name' => 'تهنئة اليوم الوطني',
                'event' => 'marketing.occasion',
                'description' => 'تهنئة مناسبة وطنية.',
                'body' => 'كل عام ووطننا بخير. تهانينا باليوم الوطني. {business}',
            ],
        ],

        // ==================== النظام ====================
        'system' => [
            [
                'code' => 'test',
                'name' => 'رسالة تجريبية',
                'event' => 'system.test',
                'system' => true,
                'description' => 'تستعملها شاشة الإعدادات لفحص الاتصال بالبوابة — لا تُحذف.',
                'body' => 'رسالة تجريبية من {business}. إن وصلتك فإعدادات الرسائل تعمل بشكل صحيح.',
            ],
            [
                'code' => 'system_alert',
                'name' => 'تنبيه نظامي',
                'event' => 'system.alert',
                'description' => 'تنبيه عام يُرسل للمسؤولين عند حدث يستدعي الانتباه.',
                'body' => 'تنبيه من {business}: {message}',
            ],
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('sms_templates')) {
            $this->command?->error('جدول sms_templates غير موجود — شغّل php artisan migrate أولاً.');

            return;
        }

        // الأعمدة الجديدة قد لا تكون قد رُحّلت بعد؛ السيدر لا يجوز أن
        // يسقط بخطأ SQL غامض في هذه الحالة
        if (! Schema::hasColumn('sms_templates', 'category')) {
            $this->command?->error('جدول sms_templates لم يُوسَّع بعد — شغّل php artisan migrate أولاً.');

            return;
        }

        $created = 0;
        $refreshed = 0;
        $order = 0;

        foreach (self::CATALOG as $category => $templates) {
            foreach ($templates as $template) {
                $order += 10;

                $existing = SmsTemplate::where('code', $template['code'])->first();

                if ($existing) {
                    // النصّ والتفعيل يخصّان المستخدم؛ البيانات الوصفية
                    // تخصّ الكتالوج فتُحدَّث معه
                    $existing->update([
                        'category' => $category,
                        'description' => $template['description'],
                        'event' => $template['event'],
                        'is_system' => $template['system'] ?? false,
                        'sort_order' => $order,
                        'variables' => $existing->detectedVariables(),
                    ]);

                    $refreshed++;

                    continue;
                }

                SmsTemplate::create([
                    'code' => $template['code'],
                    'name' => $template['name'],
                    'category' => $category,
                    'description' => $template['description'],
                    'event' => $template['event'],
                    'body' => $template['body'],
                    'variables' => $this->variablesIn($template['body']),
                    'is_active' => true,
                    'is_system' => $template['system'] ?? false,
                    'sort_order' => $order,
                ]);

                $created++;
            }
        }

        $this->command?->info(sprintf(
            'قوالب الرسائل: أُضيف %d، وحُدّثت بيانات %d، والإجمالي %d قالباً في %d تصنيفاً.',
            $created,
            $refreshed,
            SmsTemplate::count(),
            count(self::CATALOG)
        ));
    }

    /** المتغيّرات المكتوبة داخل النص */
    private function variablesIn(string $body): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $body, $matches);

        return array_values(array_unique($matches[1]));
    }
}
