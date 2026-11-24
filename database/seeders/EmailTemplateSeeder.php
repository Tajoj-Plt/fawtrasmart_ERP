<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\SeedsMissingRows;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    use SeedsMissingRows;

    public function run(): void
    {
        $now = Carbon::now();

        $templates = [
            // ═══════════════════════════════════════════════════════════
            // ██ المصادقة - AUTH ██
            // ═══════════════════════════════════════════════════════════
            ['template_code' => 'auth.welcome', 'template_name' => 'ترحيب بعميل جديد', 'template_category' => 'auth', 'subject' => 'مرحباً بك في {{ store_name }} 🎉', 'html_content' => $this->t('<h2>مرحباً {{ customer_name }} 👋</h2><p>نحن سعداء بانضمامك إلى عائلة <strong>{{ store_name }}</strong>!</p><p>نقدم لك أفضل المنتجات بأسعار تنافسية.</p>'.$this->b('تسوق الآن', '{{ store_url }}')), 'text_content' => "مرحباً {{ customer_name }}\nنحن سعداء بانضمامك إلى {{ store_name }}!\nابدأ التسوق: {{ store_url }}", 'variables' => json_encode(['customer_name', 'customer_email', 'store_name', 'store_url']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            [
                'template_code' => 'auth.verify_email',
                'template_name' => 'تأكيد البريد الإلكتروني',
                'template_category' => 'auth',
                'subject' => 'تأكيد بريدك الإلكتروني - {{ store_name }}',
                'html_content' => $this->t('<h2>تأكيد البريد الإلكتروني 📧</h2><p>مرحباً {{ customer_name }}،</p><p>لتأكيد بريدك الإلكتروني:</p>'.$this->b('تأكيد البريد', '{{ verification_url }}', '#28a745').'<div style="text-align:center;margin:25px 0;"><p>أو رمز التحقق:</p><div style="background:#667eea;color:#fff;padding:15px;border-radius:8px;display:inline-block;font-size:28px;letter-spacing:8px;">{{ verification_code }}</div></div><p style="color:#999;">⏰ صالح لمدة {{ expiry_hours }} ساعة</p>'),
                'text_content' => "مرحباً {{ customer_name }}\nرابط التأكيد: {{ verification_url }}\nرمز: {{ verification_code }}\nصالح: {{ expiry_hours }} ساعة",
                'variables' => json_encode(['customer_name', 'verification_url', 'verification_code', 'expiry_hours', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            ['template_code' => 'auth.email_verified', 'template_name' => 'تم تأكيد البريد', 'template_category' => 'auth', 'subject' => 'تم تأكيد بريدك الإلكتروني ✅', 'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">✅</div><h2 style="color:#28a745;">تم تأكيد بريدك!</h2></div><p style="text-align:center;">مرحباً {{ customer_name }}، حسابك مفعّل بالكامل!</p>'.$this->b('ابدأ التسوق', '{{ store_url }}')), 'text_content' => "مرحباً {{ customer_name }}\nتم تأكيد بريدك!\nابدأ التسوق: {{ store_url }}", 'variables' => json_encode(['customer_name', 'store_name', 'store_url']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            ['template_code' => 'auth.password_reset', 'template_name' => 'إعادة تعيين كلمة المرور', 'template_category' => 'auth', 'subject' => 'إعادة تعيين كلمة المرور - {{ store_name }}', 'html_content' => $this->t('<h2>إعادة تعيين كلمة المرور 🔐</h2><p>مرحباً {{ customer_name }}،</p><p>تلقينا طلباً لإعادة تعيين كلمة المرور.</p>'.$this->b('إعادة تعيين', '{{ reset_url }}', '#dc3545').'<div style="text-align:center;margin:25px 0;"><p>أو رمز:</p><div style="background:#dc3545;color:#fff;padding:15px;border-radius:8px;display:inline-block;font-size:28px;letter-spacing:8px;">{{ reset_code }}</div></div><p style="color:#999;">⏰ صالح لمدة {{ expiry_minutes }} دقيقة</p>'), 'text_content' => "مرحباً {{ customer_name }}\nرابط إعادة التعيين: {{ reset_url }}\nرمز: {{ reset_code }}\nصالح: {{ expiry_minutes }} دقيقة", 'variables' => json_encode(['customer_name', 'reset_url', 'reset_code', 'expiry_minutes', 'store_name']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            ['template_code' => 'auth.password_changed', 'template_name' => 'تم تغيير كلمة المرور', 'template_category' => 'auth', 'subject' => 'تم تغيير كلمة المرور 🔒', 'html_content' => $this->t('<h2>تم تغيير كلمة المرور 🔒</h2><p>مرحباً {{ customer_name }}، تم تغيير كلمة مرورك بنجاح.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;"><p><strong>التاريخ:</strong> {{ changed_at }}</p><p><strong>IP:</strong> {{ ip_address }}</p></div><div style="background:#f8d7da;padding:15px;border-radius:8px;margin-top:20px;"><p style="color:#721c24;margin:0;">⚠️ إذا لم تقم بذلك، تواصل معنا: {{ support_email }}</p></div>'), 'text_content' => "مرحباً {{ customer_name }}\nتم تغيير كلمة المرور.\nالتاريخ: {{ changed_at }}\nIP: {{ ip_address }}", 'variables' => json_encode(['customer_name', 'changed_at', 'ip_address', 'store_name', 'support_email']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            [
                'template_code' => 'auth.new_login',
                'template_name' => 'تسجيل دخول جديد',
                'template_category' => 'auth',
                'subject' => 'تسجيل دخول جديد 🔔',
                'html_content' => $this->t('<h2>تسجيل دخول جديد 🔔</h2><p>مرحباً {{ customer_name }}، تم تسجيل دخول جديد.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;"><p><strong>التاريخ:</strong> {{ login_time }}</p><p><strong>الجهاز:</strong> {{ device }}</p><p><strong>المتصفح:</strong> {{ browser }}</p><p><strong>IP:</strong> {{ ip_address }}</p><p><strong>الموقع:</strong> {{ location }}</p></div><div style="background:#fff3cd;padding:15px;border-radius:8px;margin-top:20px;"><p style="color:#856404;margin:0;">إذا لم تكن أنت، غيّر كلمة المرور فوراً.</p></div>'),
                'text_content' => "مرحباً {{ customer_name }}\nتسجيل دخول جديد:\nالتاريخ: {{ login_time }}\nالجهاز: {{ device }}\nIP: {{ ip_address }}",
                'variables' => json_encode(['customer_name', 'login_time', 'device', 'browser', 'ip_address', 'location', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'auth.otp_code',
                'template_name' => 'رمز التحقق OTP',
                'template_category' => 'auth',
                'subject' => 'رمز التحقق: {{ otp_code }}',
                'html_content' => $this->t('<h2>رمز التحقق 🔢</h2><p style="text-align:center;">مرحباً {{ customer_name }}، رمزك:</p><div style="text-align:center;margin:30px 0;"><div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px 40px;border-radius:10px;display:inline-block;font-size:36px;font-weight:bold;letter-spacing:12px;">{{ otp_code }}</div></div><p style="color:#999;text-align:center;">⏰ صالح {{ expiry_minutes }} دقيقة</p><div style="background:#f8d7da;padding:15px;border-radius:8px;text-align:center;"><p style="color:#721c24;margin:0;">🚫 لا تشارك هذا الرمز</p></div>'),
                'text_content' => "مرحباً {{ customer_name }}\nرمز التحقق: {{ otp_code }}\nصالح: {{ expiry_minutes }} دقيقة",
                'variables' => json_encode(['customer_name', 'otp_code', 'expiry_minutes', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            ['template_code' => 'auth.account_deleted', 'template_name' => 'تم حذف الحساب', 'template_category' => 'auth', 'subject' => 'تم حذف حسابك - {{ store_name }}', 'html_content' => $this->t('<h2>تم حذف حسابك</h2><p>مرحباً {{ customer_name }}،</p><p>تم حذف حسابك في {{ deleted_at }}.</p><p>إذا غيرت رأيك، يمكنك إنشاء حساب جديد.</p><div style="background:#fff3cd;padding:15px;border-radius:8px;"><p style="color:#856404;margin:0;">⚠️ إذا لم تطلب ذلك، تواصل معنا: {{ support_email }}</p></div>'), 'text_content' => "مرحباً {{ customer_name }}\nتم حذف حسابك في {{ deleted_at }}.\nإذا لم تطلب ذلك: {{ support_email }}", 'variables' => json_encode(['customer_name', 'deleted_at', 'store_name', 'support_email']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            // ═══════════════════════════════════════════════════════════
            // ██ الطلبات - ORDERS ██
            // ═══════════════════════════════════════════════════════════
            [
                'template_code' => 'order.confirmed',
                'template_name' => 'تأكيد الطلب',
                'template_category' => 'orders',
                'subject' => 'تم استلام طلبك #{{ order_number }} ✅',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">✅</div><h2 style="color:#28a745;">تم استلام طلبك!</h2><p style="font-size:18px;">رقم الطلب: <strong>#{{ order_number }}</strong></p></div><p>مرحباً {{ customer_name }}، شكراً لطلبك!</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><h3>تفاصيل الطلب</h3>{{ order_items }}<hr><p><strong>المجموع:</strong> {{ subtotal }}</p><p><strong>الشحن:</strong> {{ shipping_cost }}</p><p><strong>الضريبة:</strong> {{ tax }}</p><p><strong>الخصم:</strong> -{{ discount }}</p><p style="font-size:20px;"><strong>الإجمالي:</strong> <span style="color:#667eea;">{{ total }}</span></p></div><div style="background:#f8f9fa;padding:15px;border-radius:8px;"><h4>📍 عنوان الشحن</h4><p>{{ shipping_address }}</p><h4>💳 الدفع</h4><p>{{ payment_method }}</p></div>'.$this->b('تتبع الطلب', '{{ tracking_url }}')),
                'text_content' => "مرحباً {{ customer_name }}\nتم استلام طلبك #{{ order_number }}\n{{ order_items }}\nالإجمالي: {{ total }}\nعنوان الشحن: {{ shipping_address }}\nتتبع: {{ tracking_url }}",
                'variables' => json_encode(['customer_name', 'order_number', 'order_date', 'order_items', 'subtotal', 'shipping_cost', 'tax', 'discount', 'total', 'shipping_address', 'payment_method', 'tracking_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            ['template_code' => 'order.processing', 'template_name' => 'الطلب قيد المعالجة', 'template_category' => 'orders', 'subject' => 'طلبك #{{ order_number }} قيد التجهيز 📦', 'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">📦</div><h2 style="color:#667eea;">طلبك قيد التجهيز!</h2></div><p>مرحباً {{ customer_name }}،</p><p>طلبك <strong>#{{ order_number }}</strong> قيد التجهيز الآن.</p>'.$this->b('تتبع الطلب', '{{ tracking_url }}')), 'text_content' => "مرحباً {{ customer_name }}\nطلبك #{{ order_number }} قيد التجهيز.\nتتبع: {{ tracking_url }}", 'variables' => json_encode(['customer_name', 'order_number', 'tracking_url', 'store_name']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            [
                'template_code' => 'order.shipped',
                'template_name' => 'تم شحن الطلب',
                'template_category' => 'shipping',
                'subject' => 'طلبك #{{ order_number }} في الطريق 🚚',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🚚</div><h2 style="color:#667eea;">تم شحن طلبك!</h2></div><p>مرحباً {{ customer_name }}،</p><p>تم شحن طلبك <strong>#{{ order_number }}</strong>.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>شركة الشحن:</strong> {{ shipping_company }}</p><p><strong>رقم التتبع:</strong> {{ tracking_number }}</p><p><strong>التسليم المتوقع:</strong> {{ estimated_delivery }}</p></div>'.$this->b('تتبع الشحنة', '{{ carrier_tracking_url }}')),
                'text_content' => "مرحباً {{ customer_name }}\nتم شحن طلبك #{{ order_number }}\nشركة الشحن: {{ shipping_company }}\nرقم التتبع: {{ tracking_number }}\nالتسليم: {{ estimated_delivery }}\nتتبع: {{ carrier_tracking_url }}",
                'variables' => json_encode(['customer_name', 'order_number', 'shipping_company', 'tracking_number', 'estimated_delivery', 'carrier_tracking_url', 'shipping_address', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            ['template_code' => 'order.out_for_delivery', 'template_name' => 'الطلب خارج للتوصيل', 'template_category' => 'shipping', 'subject' => 'طلبك #{{ order_number }} سيصل اليوم! 📍', 'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">📍</div><h2 style="color:#28a745;">طلبك سيصل اليوم!</h2></div><p>مرحباً {{ customer_name }}،</p><p>طلبك <strong>#{{ order_number }}</strong> في الطريق!</p><div style="background:#f8f9fa;padding:15px;border-radius:8px;"><h4>📍 عنوان التسليم</h4><p>{{ shipping_address }}</p></div>'), 'text_content' => "مرحباً {{ customer_name }}\nطلبك #{{ order_number }} سيصل اليوم!\nعنوان التسليم: {{ shipping_address }}", 'variables' => json_encode(['customer_name', 'order_number', 'shipping_address', 'tracking_number', 'store_name']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            [
                'template_code' => 'order.delivered',
                'template_name' => 'تم تسليم الطلب',
                'template_category' => 'shipping',
                'subject' => 'تم تسليم طلبك #{{ order_number }} ✨',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">✨</div><h2 style="color:#28a745;">تم تسليم طلبك!</h2></div><p>مرحباً {{ customer_name }}،</p><p>تم تسليم طلبك <strong>#{{ order_number }}</strong> بنجاح.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>التسليم:</strong> {{ delivery_date }}</p><p><strong>المستلم:</strong> {{ received_by }}</p></div><p>شاركنا رأيك!</p>'.$this->b('قيّم تجربتك', '{{ review_url }}', '#ffc107')),
                'text_content' => "مرحباً {{ customer_name }}\nتم تسليم طلبك #{{ order_number }}\nالتسليم: {{ delivery_date }}\nالمستلم: {{ received_by }}\nقيّم: {{ review_url }}",
                'variables' => json_encode(['customer_name', 'order_number', 'delivery_date', 'received_by', 'review_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'order.cancelled',
                'template_name' => 'تم إلغاء الطلب',
                'template_category' => 'orders',
                'subject' => 'تم إلغاء طلبك #{{ order_number }}',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">❌</div><h2 style="color:#dc3545;">تم إلغاء الطلب</h2></div><p>مرحباً {{ customer_name }}،</p><p>تم إلغاء طلبك <strong>#{{ order_number }}</strong>.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>السبب:</strong> {{ cancellation_reason }}</p><p><strong>الاسترداد:</strong> {{ refund_amount }}</p></div><p>الاسترداد خلال {{ refund_days }} أيام عمل.</p>'),
                'text_content' => "مرحباً {{ customer_name }}\nتم إلغاء طلبك #{{ order_number }}\nالسبب: {{ cancellation_reason }}\nالاسترداد: {{ refund_amount }} خلال {{ refund_days }} أيام",
                'variables' => json_encode(['customer_name', 'order_number', 'cancellation_reason', 'refund_days', 'refund_amount', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'order.return_requested',
                'template_name' => 'طلب استرجاع',
                'template_category' => 'orders',
                'subject' => 'تم استلام طلب الاسترجاع #{{ return_number }}',
                'html_content' => $this->t('<h2>تم استلام طلب الاسترجاع</h2><p>مرحباً {{ customer_name }}،</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>رقم الاسترجاع:</strong> #{{ return_number }}</p><p><strong>الطلب الأصلي:</strong> #{{ order_number }}</p><p><strong>السبب:</strong> {{ return_reason }}</p><p><strong>المنتجات:</strong></p>{{ return_items }}</div><p>سيتم الرد خلال {{ review_days }} أيام عمل.</p>'),
                'text_content' => "مرحباً {{ customer_name }}\nتم استلام طلب الاسترجاع #{{ return_number }}\nالطلب: #{{ order_number }}\nالسبب: {{ return_reason }}\nسيتم الرد خلال {{ review_days }} أيام",
                'variables' => json_encode(['customer_name', 'return_number', 'order_number', 'return_reason', 'return_items', 'review_days', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'order.return_approved',
                'template_name' => 'تم قبول الاسترجاع',
                'template_category' => 'orders',
                'subject' => 'تم قبول طلب الاسترجاع #{{ return_number }} ✅',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">✅</div><h2 style="color:#28a745;">تم قبول الاسترجاع!</h2></div><p>مرحباً {{ customer_name }}،</p><p>تم قبول طلب الاسترجاع <strong>#{{ return_number }}</strong>.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><h4>📍 أرسل إلى:</h4><p>{{ return_address }}</p><h4>📋 التعليمات:</h4><p>{{ return_instructions }}</p></div><p>الاسترداد خلال {{ refund_days }} أيام بعد الاستلام.</p>'),
                'text_content' => "مرحباً {{ customer_name }}\nتم قبول الاسترجاع #{{ return_number }}\nأرسل إلى: {{ return_address }}\nالتعليمات: {{ return_instructions }}\nالاسترداد: {{ refund_days }} أيام",
                'variables' => json_encode(['customer_name', 'return_number', 'return_address', 'return_instructions', 'refund_days', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            ['template_code' => 'order.return_rejected', 'template_name' => 'تم رفض الاسترجاع', 'template_category' => 'orders', 'subject' => 'تحديث طلب الاسترجاع #{{ return_number }}', 'html_content' => $this->t('<h2>تحديث طلب الاسترجاع</h2><p>مرحباً {{ customer_name }}،</p><p>نأسف، طلب الاسترجاع <strong>#{{ return_number }}</strong> لم يُقبل.</p><div style="background:#f8d7da;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>السبب:</strong> {{ rejection_reason }}</p></div><p>للاستفسار: {{ support_email }}</p>'), 'text_content' => "مرحباً {{ customer_name }}\nلم يُقبل طلب الاسترجاع #{{ return_number }}\nالسبب: {{ rejection_reason }}\nتواصل: {{ support_email }}", 'variables' => json_encode(['customer_name', 'return_number', 'rejection_reason', 'store_name', 'support_email']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            [
                'template_code' => 'order.refunded',
                'template_name' => 'تم استرداد المبلغ',
                'template_category' => 'orders',
                'subject' => 'تم استرداد مبلغ طلبك #{{ order_number }} 💰',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">💰</div><h2 style="color:#28a745;">تم استرداد المبلغ!</h2></div><p>مرحباً {{ customer_name }}،</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>الطلب:</strong> #{{ order_number }}</p><p><strong>المبلغ:</strong> {{ refund_amount }}</p><p><strong>الطريقة:</strong> {{ refund_method }}</p><p><strong>المرجع:</strong> {{ refund_reference }}</p></div><p>سيظهر خلال {{ processing_days }} أيام عمل.</p>'),
                'text_content' => "مرحباً {{ customer_name }}\nتم الاسترداد!\nالطلب: #{{ order_number }}\nالمبلغ: {{ refund_amount }}\nالطريقة: {{ refund_method }}\nسيظهر خلال {{ processing_days }} أيام",
                'variables' => json_encode(['customer_name', 'order_number', 'refund_amount', 'refund_method', 'refund_reference', 'processing_days', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ═══════════════════════════════════════════════════════════
            // ██ المدفوعات - PAYMENTS ██
            // ═══════════════════════════════════════════════════════════
            [
                'template_code' => 'payment.confirmed',
                'template_name' => 'تأكيد الدفع',
                'template_category' => 'payments',
                'subject' => 'تم استلام دفعتك ✅',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">✅</div><h2 style="color:#28a745;">تم استلام دفعتك!</h2></div><p>مرحباً {{ customer_name }}،</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>الطلب:</strong> #{{ order_number }}</p><p><strong>المبلغ:</strong> {{ amount }}</p><p><strong>الطريقة:</strong> {{ payment_method }}</p><p><strong>المرجع:</strong> {{ transaction_id }}</p><p><strong>التاريخ:</strong> {{ payment_date }}</p></div>'),
                'text_content' => "مرحباً {{ customer_name }}\nتم استلام دفعتك!\nالطلب: #{{ order_number }}\nالمبلغ: {{ amount }}\nالطريقة: {{ payment_method }}\nالمرجع: {{ transaction_id }}",
                'variables' => json_encode(['customer_name', 'order_number', 'amount', 'payment_method', 'transaction_id', 'payment_date', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'payment.failed',
                'template_name' => 'فشل الدفع',
                'template_category' => 'payments',
                'subject' => 'فشل الدفع - الطلب #{{ order_number }}',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">❌</div><h2 style="color:#dc3545;">فشل الدفع</h2></div><p>مرحباً {{ customer_name }}،</p><p>فشل الدفع للطلب <strong>#{{ order_number }}</strong>.</p><div style="background:#f8d7da;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>السبب:</strong> {{ failure_reason }}</p></div>'.$this->b('إكمال الدفع', '{{ payment_url }}', '#dc3545').'<p style="color:#999;">⏰ سيُلغى بعد {{ expiry_hours }} ساعة</p>'),
                'text_content' => "مرحباً {{ customer_name }}\nفشل الدفع للطلب #{{ order_number }}\nالسبب: {{ failure_reason }}\nأكمل الدفع: {{ payment_url }}\nسيُلغى بعد {{ expiry_hours }} ساعة",
                'variables' => json_encode(['customer_name', 'order_number', 'failure_reason', 'payment_url', 'expiry_hours', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            ['template_code' => 'payment.reminder', 'template_name' => 'تذكير بالدفع', 'template_category' => 'payments', 'subject' => 'تذكير: أكمل دفع طلبك #{{ order_number }}', 'html_content' => $this->t('<h2>تذكير بالدفع ⏰</h2><p>مرحباً {{ customer_name }}،</p><p>طلبك <strong>#{{ order_number }}</strong> في انتظار الدفع.</p><div style="background:#fff3cd;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>المبلغ:</strong> {{ amount }}</p><p><strong>ينتهي:</strong> {{ expiry_date }}</p></div>'.$this->b('إكمال الدفع', '{{ payment_url }}')), 'text_content' => "مرحباً {{ customer_name }}\nطلبك #{{ order_number }} في انتظار الدفع\nالمبلغ: {{ amount }}\nينتهي: {{ expiry_date }}\nأكمل: {{ payment_url }}", 'variables' => json_encode(['customer_name', 'order_number', 'amount', 'payment_url', 'expiry_date', 'store_name']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            [
                'template_code' => 'payment.invoice',
                'template_name' => 'فاتورة الطلب',
                'template_category' => 'payments',
                'subject' => 'فاتورة طلبك #{{ order_number }}',
                'html_content' => $this->t('<h2>فاتورة ضريبية</h2><p>مرحباً {{ customer_name }}،</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>رقم الفاتورة:</strong> {{ invoice_number }}</p><p><strong>الطلب:</strong> #{{ order_number }}</p><p><strong>التاريخ:</strong> {{ invoice_date }}</p><hr>{{ order_items }}<hr><p><strong>المجموع:</strong> {{ subtotal }}</p><p><strong>الضريبة:</strong> {{ tax }}</p><p style="font-size:18px;"><strong>الإجمالي:</strong> {{ total }}</p></div>'.$this->b('تحميل الفاتورة', '{{ invoice_url }}')),
                'text_content' => "مرحباً {{ customer_name }}\nفاتورة طلبك #{{ order_number }}\nرقم الفاتورة: {{ invoice_number }}\nالتاريخ: {{ invoice_date }}\nالإجمالي: {{ total }}\nتحميل: {{ invoice_url }}",
                'variables' => json_encode(['customer_name', 'invoice_number', 'order_number', 'invoice_date', 'order_items', 'subtotal', 'tax', 'total', 'invoice_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ═══════════════════════════════════════════════════════════
            // ██ سلة التسوق - CART ██
            // ═══════════════════════════════════════════════════════════
            ['template_code' => 'cart.abandoned', 'template_name' => 'سلة متروكة', 'template_category' => 'cart', 'subject' => 'نسيت شيئاً في سلتك! 🛒', 'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🛒</div><h2 style="color:#667eea;">نسيت شيئاً!</h2></div><p>مرحباً {{ customer_name }}،</p><p>تركت منتجات في سلتك.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;">{{ cart_items }}<hr><p style="font-size:18px;"><strong>المجموع:</strong> {{ cart_total }}</p></div><p>المنتجات قد تنفد!</p>'.$this->b('أكمل طلبك', '{{ checkout_url }}')), 'text_content' => "مرحباً {{ customer_name }}\nتركت منتجات في سلتك:\n{{ cart_items }}\nالمجموع: {{ cart_total }}\nأكمل: {{ checkout_url }}", 'variables' => json_encode(['customer_name', 'cart_items', 'cart_total', 'checkout_url', 'store_name']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            [
                'template_code' => 'cart.abandoned_discount',
                'template_name' => 'سلة متروكة مع خصم',
                'template_category' => 'cart',
                'subject' => 'خصم {{ discount_percent }}% ينتظرك! 🎁',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🎁</div><h2 style="color:#28a745;">عرض خاص لك!</h2></div><p>مرحباً {{ customer_name }}،</p><p>خصم <strong>{{ discount_percent }}%</strong> على سلتك!</p><div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px;border-radius:8px;text-align:center;margin:25px 0;"><p>كود الخصم</p><p style="font-size:28px;font-weight:bold;letter-spacing:4px;">{{ discount_code }}</p><p>صالح حتى {{ expiry_date }}</p></div><div style="background:#f8f9fa;padding:20px;border-radius:8px;">{{ cart_items }}<hr><p><s>قبل: {{ cart_total }}</s></p><p style="font-size:20px;color:#28a745;"><strong>بعد: {{ discounted_total }}</strong></p></div>'.$this->b('استخدم العرض', '{{ checkout_url }}', '#28a745')),
                'text_content' => "مرحباً {{ customer_name }}\nخصم {{ discount_percent }}% على سلتك!\nالكود: {{ discount_code }}\nصالح حتى: {{ expiry_date }}\nقبل: {{ cart_total }}\nبعد: {{ discounted_total }}\nأكمل: {{ checkout_url }}",
                'variables' => json_encode(['customer_name', 'discount_percent', 'discount_code', 'expiry_date', 'cart_items', 'cart_total', 'discounted_total', 'checkout_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ═══════════════════════════════════════════════════════════
            // ██ المنتجات - PRODUCTS ██
            // ═══════════════════════════════════════════════════════════
            [
                'template_code' => 'product.back_in_stock',
                'template_name' => 'المنتج متاح',
                'template_category' => 'products',
                'subject' => '{{ product_name }} متاح الآن! 🎉',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🎉</div><h2 style="color:#28a745;">المنتج متاح!</h2></div><p>مرحباً {{ customer_name }}،</p><p>المنتج الذي كنت تنتظره متاح الآن.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;text-align:center;"><img src="{{ product_image }}" style="max-width:200px;border-radius:8px;"><h3>{{ product_name }}</h3><p style="font-size:24px;color:#667eea;font-weight:bold;">{{ product_price }}</p></div>'.$this->b('اطلب الآن', '{{ product_url }}')),
                'text_content' => "مرحباً {{ customer_name }}\n{{ product_name }} متاح الآن!\nالسعر: {{ product_price }}\nاطلب: {{ product_url }}",
                'variables' => json_encode(['customer_name', 'product_name', 'product_price', 'product_url', 'product_image', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'product.price_drop',
                'template_name' => 'انخفاض السعر',
                'template_category' => 'products',
                'subject' => 'السعر انخفض! {{ product_name }} 📉',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">📉</div><h2 style="color:#28a745;">السعر انخفض!</h2></div><p>مرحباً {{ customer_name }}،</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;text-align:center;"><img src="{{ product_image }}" style="max-width:200px;border-radius:8px;"><h3>{{ product_name }}</h3><p><s style="color:#999;">{{ old_price }}</s></p><p style="font-size:28px;color:#28a745;font-weight:bold;">{{ new_price }}</p><p style="color:#dc3545;">وفر {{ savings }}!</p></div>'.$this->b('اشتر الآن', '{{ product_url }}', '#28a745')),
                'text_content' => "مرحباً {{ customer_name }}\nانخفض سعر {{ product_name }}!\nقديم: {{ old_price }}\nجديد: {{ new_price }}\nالتوفير: {{ savings }}\nاشتر: {{ product_url }}",
                'variables' => json_encode(['customer_name', 'product_name', 'old_price', 'new_price', 'savings', 'product_url', 'product_image', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'product.review_request',
                'template_name' => 'طلب تقييم',
                'template_category' => 'reviews',
                'subject' => 'كيف كانت تجربتك مع {{ product_name }}؟ ⭐',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">⭐</div><h2 style="color:#ffc107;">شاركنا رأيك!</h2></div><p>مرحباً {{ customer_name }}،</p><p>نأمل أن تكون سعيداً بمشترياتك!</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;text-align:center;"><img src="{{ product_image }}" style="max-width:150px;border-radius:8px;"><h3>{{ product_name }}</h3></div><p>رأيك يساعدنا على التحسين.</p>'.$this->b('أضف تقييمك', '{{ review_url }}', '#ffc107')),
                'text_content' => "مرحباً {{ customer_name }}\nكيف كانت تجربتك مع {{ product_name }}؟\nأضف تقييمك: {{ review_url }}",
                'variables' => json_encode(['customer_name', 'product_name', 'product_image', 'review_url', 'order_number', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ═══════════════════════════════════════════════════════════
            // ██ قائمة الأمنيات - WISHLIST ██
            // ═══════════════════════════════════════════════════════════
            ['template_code' => 'wishlist.reminder', 'template_name' => 'تذكير قائمة الأمنيات', 'template_category' => 'wishlist', 'subject' => 'منتجاتك المفضلة في انتظارك! 💝', 'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">💝</div><h2 style="color:#e91e63;">منتجاتك المفضلة!</h2></div><p>مرحباً {{ customer_name }}،</p><p>لديك <strong>{{ items_count }}</strong> منتجات في قائمة أمنياتك.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;">{{ wishlist_items }}</div>'.$this->b('تسوق الآن', '{{ wishlist_url }}', '#e91e63')), 'text_content' => "مرحباً {{ customer_name }}\nلديك {{ items_count }} منتجات في قائمة أمنياتك:\n{{ wishlist_items }}\nتسوق: {{ wishlist_url }}", 'variables' => json_encode(['customer_name', 'items_count', 'wishlist_items', 'wishlist_url', 'store_name']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            [
                'template_code' => 'wishlist.low_stock',
                'template_name' => 'منتج على وشك النفاد',
                'template_category' => 'wishlist',
                'subject' => '⚠️ {{ product_name }} على وشك النفاد!',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">⚠️</div><h2 style="color:#dc3545;">أسرع قبل النفاد!</h2></div><p>مرحباً {{ customer_name }}،</p><p>منتج في قائمة أمنياتك على وشك النفاد!</p><div style="background:#fff3cd;padding:20px;border-radius:8px;margin:25px 0;text-align:center;"><img src="{{ product_image }}" style="max-width:200px;border-radius:8px;"><h3>{{ product_name }}</h3><p style="color:#dc3545;font-size:18px;font-weight:bold;">متبقي {{ remaining_quantity }} فقط!</p></div>'.$this->b('اطلب الآن', '{{ product_url }}', '#dc3545')),
                'text_content' => "مرحباً {{ customer_name }}\n{{ product_name }} على وشك النفاد!\nمتبقي: {{ remaining_quantity }} فقط\nاطلب: {{ product_url }}",
                'variables' => json_encode(['customer_name', 'product_name', 'remaining_quantity', 'product_url', 'product_image', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ═══════════════════════════════════════════════════════════
            // ██ العروض - PROMOTIONS ██
            // ═══════════════════════════════════════════════════════════
            [
                'template_code' => 'promo.special_offer',
                'template_name' => 'عرض خاص',
                'template_category' => 'marketing',
                'subject' => '🔥 عرض خاص: {{ offer_title }}',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🔥</div><h2 style="color:#dc3545;">{{ offer_title }}</h2></div><p>مرحباً {{ customer_name }}،</p><p>{{ offer_description }}</p><div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px;border-radius:8px;text-align:center;margin:25px 0;"><p>كود الخصم</p><p style="font-size:28px;font-weight:bold;letter-spacing:4px;">{{ promo_code }}</p><p>من {{ start_date }} حتى {{ end_date }}</p></div>'.$this->b('تسوق الآن', '{{ offer_url }}', '#dc3545')),
                'text_content' => "مرحباً {{ customer_name }}\n{{ offer_title }}\n{{ offer_description }}\nالكود: {{ promo_code }}\nمن {{ start_date }} حتى {{ end_date }}\nتسوق: {{ offer_url }}",
                'variables' => json_encode(['customer_name', 'offer_title', 'offer_description', 'promo_code', 'discount_value', 'start_date', 'end_date', 'offer_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'promo.birthday',
                'template_name' => 'تهنئة عيد ميلاد',
                'template_category' => 'marketing',
                'subject' => '🎂 عيد ميلاد سعيد {{ customer_name }}!',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🎂</div><h2 style="color:#e91e63;">عيد ميلاد سعيد!</h2></div><p style="text-align:center;font-size:18px;">{{ customer_name }}، بمناسبة يومك المميز...</p><div style="background:linear-gradient(135deg,#e91e63,#9c27b0);color:#fff;padding:30px;border-radius:8px;text-align:center;margin:25px 0;"><p>هديتنا لك</p><p style="font-size:48px;font-weight:bold;">{{ discount_percent }}%</p><p>على جميع مشترياتك!</p><div style="background:#fff;color:#333;padding:10px 20px;border-radius:5px;margin-top:15px;display:inline-block;"><strong>{{ birthday_code }}</strong></div><p style="margin-top:15px;">صالح حتى {{ expiry_date }}</p></div>'.$this->b('تسوق الآن', '{{ store_url }}', '#e91e63')),
                'text_content' => "عيد ميلاد سعيد {{ customer_name }}! 🎂\nهديتك: خصم {{ discount_percent }}%\nالكود: {{ birthday_code }}\nصالح حتى: {{ expiry_date }}\nتسوق: {{ store_url }}",
                'variables' => json_encode(['customer_name', 'discount_percent', 'birthday_code', 'expiry_date', 'store_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'promo.loyalty_points',
                'template_name' => 'نقاط الولاء',
                'template_category' => 'loyalty',
                'subject' => '⭐ لديك {{ points_balance }} نقطة!',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">⭐</div><h2 style="color:#ffc107;">نقاط الولاء</h2></div><p>مرحباً {{ customer_name }}،</p><div style="background:linear-gradient(135deg,#ffc107,#ff9800);color:#333;padding:30px;border-radius:8px;text-align:center;margin:25px 0;"><p>رصيد نقاطك</p><p style="font-size:48px;font-weight:bold;">{{ points_balance }}</p><p>تساوي <strong>{{ points_value }}</strong></p></div><div style="background:#f8f9fa;padding:20px;border-radius:8px;"><p><strong>المكتسب هذا الشهر:</strong> {{ earned_this_month }}</p><p style="color:#dc3545;"><strong>ستنتهي:</strong> {{ expiring_points }} في {{ expiry_date }}</p></div>'.$this->b('استبدل نقاطك', '{{ redeem_url }}', '#ffc107')),
                'text_content' => "مرحباً {{ customer_name }}\nرصيد نقاطك: {{ points_balance }}\nتساوي: {{ points_value }}\nالمكتسب: {{ earned_this_month }}\nستنتهي: {{ expiring_points }} في {{ expiry_date }}\nاستبدل: {{ redeem_url }}",
                'variables' => json_encode(['customer_name', 'points_balance', 'points_value', 'earned_this_month', 'expiring_points', 'expiry_date', 'redeem_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'promo.referral',
                'template_name' => 'دعوة صديق',
                'template_category' => 'marketing',
                'subject' => '🎁 ادعُ أصدقائك واربح!',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🎁</div><h2 style="color:#9c27b0;">شارك واربح!</h2></div><p>مرحباً {{ customer_name }}،</p><p>شارك متعة التسوق مع أصدقائك!</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p>🎁 أنت تحصل: <strong>{{ referrer_reward }}</strong></p><p>🎁 صديقك يحصل: <strong>{{ referee_reward }}</strong></p></div><div style="background:linear-gradient(135deg,#9c27b0,#673ab7);color:#fff;padding:20px;border-radius:8px;text-align:center;margin:25px 0;"><p>كود الدعوة</p><p style="font-size:24px;font-weight:bold;letter-spacing:4px;">{{ referral_code }}</p></div><div style="background:#f8f9fa;padding:15px;border-radius:8px;"><p><strong>دعواتك:</strong> {{ total_referrals }}</p><p><strong>مكافآتك:</strong> {{ total_rewards }}</p></div>'.$this->b('شارك الآن', '{{ referral_url }}', '#9c27b0')),
                'text_content' => "مرحباً {{ customer_name }}\nادعُ أصدقائك!\nأنت: {{ referrer_reward }}\nصديقك: {{ referee_reward }}\nكود: {{ referral_code }}\nدعواتك: {{ total_referrals }}\nمكافآتك: {{ total_rewards }}\nشارك: {{ referral_url }}",
                'variables' => json_encode(['customer_name', 'referrer_reward', 'referee_reward', 'referral_code', 'referral_url', 'total_referrals', 'total_rewards', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'promo.newsletter',
                'template_name' => 'النشرة الإخبارية',
                'template_category' => 'marketing',
                'subject' => '📬 جديدنا هذا الأسبوع',
                'html_content' => $this->t('<h2>{{ newsletter_title }}</h2><p>مرحباً {{ customer_name }}،</p><div style="margin:25px 0;">{{ newsletter_content }}</div><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><h3>منتجات مميزة</h3>{{ featured_products }}</div>'.$this->b('اقرأ المزيد', '{{ newsletter_url }}').'<p style="text-align:center;margin-top:30px;font-size:12px;color:#999;"><a href="{{ unsubscribe_url }}" style="color:#999;">إلغاء الاشتراك</a></p>'),
                'text_content' => "مرحباً {{ customer_name }}\n{{ newsletter_title }}\n{{ newsletter_content }}\nاقرأ المزيد: {{ newsletter_url }}\nإلغاء الاشتراك: {{ unsubscribe_url }}",
                'variables' => json_encode(['customer_name', 'newsletter_title', 'newsletter_content', 'featured_products', 'newsletter_url', 'unsubscribe_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ═══════════════════════════════════════════════════════════
            // ██ الدعم - SUPPORT ██
            // ═══════════════════════════════════════════════════════════
            [
                'template_code' => 'support.ticket_created',
                'template_name' => 'تذكرة دعم جديدة',
                'template_category' => 'support',
                'subject' => 'تم استلام طلبك #{{ ticket_number }}',
                'html_content' => $this->t('<h2>تم استلام طلب الدعم</h2><p>مرحباً {{ customer_name }}،</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>رقم التذكرة:</strong> #{{ ticket_number }}</p><p><strong>الموضوع:</strong> {{ ticket_subject }}</p><p><strong>الأولوية:</strong> {{ priority }}</p><p><strong>رسالتك:</strong></p><div style="background:#fff;padding:15px;border-radius:5px;border-right:4px solid #667eea;">{{ ticket_message }}</div></div><p>سيتم الرد خلال {{ response_time }}.</p>'.$this->b('متابعة التذكرة', '{{ ticket_url }}')),
                'text_content' => "مرحباً {{ customer_name }}\nتم استلام طلب الدعم #{{ ticket_number }}\nالموضوع: {{ ticket_subject }}\nالأولوية: {{ priority }}\nسيتم الرد خلال {{ response_time }}\nمتابعة: {{ ticket_url }}",
                'variables' => json_encode(['customer_name', 'ticket_number', 'ticket_subject', 'ticket_message', 'priority', 'response_time', 'ticket_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            ['template_code' => 'support.ticket_reply', 'template_name' => 'رد على التذكرة', 'template_category' => 'support', 'subject' => 'رد جديد على تذكرتك #{{ ticket_number }}', 'html_content' => $this->t('<h2>رد جديد على تذكرتك</h2><p>مرحباً {{ customer_name }}،</p><p>تم إضافة رد على تذكرتك <strong>#{{ ticket_number }}</strong>.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>{{ agent_name }}:</strong></p><div style="background:#fff;padding:15px;border-radius:5px;border-right:4px solid #28a745;">{{ reply_content }}</div></div>'.$this->b('الرد أو المتابعة', '{{ ticket_url }}')), 'text_content' => "مرحباً {{ customer_name }}\nرد جديد على تذكرتك #{{ ticket_number }}\n{{ agent_name }}:\n{{ reply_content }}\nللرد: {{ ticket_url }}", 'variables' => json_encode(['customer_name', 'ticket_number', 'reply_content', 'agent_name', 'ticket_url', 'store_name']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            ['template_code' => 'support.ticket_closed', 'template_name' => 'تم إغلاق التذكرة', 'template_category' => 'support', 'subject' => 'تم إغلاق تذكرتك #{{ ticket_number }}', 'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">✅</div><h2 style="color:#28a745;">تم إغلاق التذكرة</h2></div><p>مرحباً {{ customer_name }}،</p><p>تم إغلاق تذكرة الدعم <strong>#{{ ticket_number }}</strong>.</p><p>نأمل أن تكون مشكلتك قد حُلت.</p>'.$this->b('قيّم تجربتك', '{{ feedback_url }}', '#ffc107').'<p style="text-align:center;margin-top:20px;color:#999;">للمساعدة: {{ support_email }}</p>'), 'text_content' => "مرحباً {{ customer_name }}\nتم إغلاق تذكرتك #{{ ticket_number }}\nقيّم: {{ feedback_url }}\nللمساعدة: {{ support_email }}", 'variables' => json_encode(['customer_name', 'ticket_number', 'feedback_url', 'store_name', 'support_email']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            ['template_code' => 'support.contact_received', 'template_name' => 'تم استلام الرسالة', 'template_category' => 'support', 'subject' => 'تم استلام رسالتك - {{ store_name }}', 'html_content' => $this->t('<h2>شكراً لتواصلك معنا</h2><p>مرحباً {{ customer_name }}،</p><p>تم استلام رسالتك بنجاح.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>ملخص رسالتك:</strong></p><div style="background:#fff;padding:15px;border-radius:5px;border-right:4px solid #667eea;">{{ message_summary }}</div></div><p>سنرد عليك في أقرب وقت ممكن.</p>'), 'text_content' => "مرحباً {{ customer_name }}\nتم استلام رسالتك:\n{{ message_summary }}\nسنرد عليك قريباً.", 'variables' => json_encode(['customer_name', 'message_summary', 'store_name', 'support_email']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            // ═══════════════════════════════════════════════════════════
            // ██ الاشتراكات - SUBSCRIPTIONS ██
            // ═══════════════════════════════════════════════════════════
            [
                'template_code' => 'subscription.confirmed',
                'template_name' => 'تأكيد الاشتراك',
                'template_category' => 'subscription',
                'subject' => 'تم تفعيل اشتراكك ✅',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">✅</div><h2 style="color:#28a745;">تم تفعيل اشتراكك!</h2></div><p>مرحباً {{ customer_name }}،</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>الخطة:</strong> {{ plan_name }}</p><p><strong>السعر:</strong> {{ plan_price }}</p><p><strong>البدء:</strong> {{ start_date }}</p><p><strong>التجديد:</strong> {{ renewal_date }}</p></div>'.$this->b('إدارة اشتراكك', '{{ subscription_url }}')),
                'text_content' => "مرحباً {{ customer_name }}\nتم تفعيل اشتراكك!\nالخطة: {{ plan_name }}\nالسعر: {{ plan_price }}\nالبدء: {{ start_date }}\nالتجديد: {{ renewal_date }}\nإدارة: {{ subscription_url }}",
                'variables' => json_encode(['customer_name', 'plan_name', 'plan_price', 'start_date', 'renewal_date', 'subscription_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'subscription.renewal_reminder',
                'template_name' => 'تذكير بالتجديد',
                'template_category' => 'subscription',
                'subject' => 'اشتراكك سيتجدد قريباً 🔔',
                'html_content' => $this->t('<h2>تذكير بالتجديد 🔔</h2><p>مرحباً {{ customer_name }}،</p><p>اشتراكك سيتجدد تلقائياً في <strong>{{ renewal_date }}</strong>.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>الخطة:</strong> {{ plan_name }}</p><p><strong>المبلغ:</strong> {{ renewal_amount }}</p></div><p>إذا كنت ترغب في التغيير أو الإلغاء، قم بذلك قبل التجديد.</p>'.$this->b('إدارة الاشتراك', '{{ subscription_url }}')),
                'text_content' => "مرحباً {{ customer_name }}\nاشتراكك سيتجدد في {{ renewal_date }}\nالخطة: {{ plan_name }}\nالمبلغ: {{ renewal_amount }}\nإدارة: {{ subscription_url }}",
                'variables' => json_encode(['customer_name', 'plan_name', 'renewal_date', 'renewal_amount', 'subscription_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'subscription.renewal_failed',
                'template_name' => 'فشل التجديد',
                'template_category' => 'subscription',
                'subject' => '⚠️ فشل تجديد اشتراكك',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">⚠️</div><h2 style="color:#dc3545;">فشل تجديد الاشتراك</h2></div><p>مرحباً {{ customer_name }}،</p><p>فشلت عملية تجديد اشتراكك.</p><div style="background:#f8d7da;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>السبب:</strong> {{ failure_reason }}</p></div><p>يرجى تحديث معلومات الدفع لتجنب انقطاع الخدمة.</p>'.$this->b('تحديث الدفع', '{{ update_payment_url }}', '#dc3545').'<p style="color:#999;">سيُلغى في {{ cancellation_date }} إذا لم يتم التحديث.</p>'),
                'text_content' => "مرحباً {{ customer_name }}\nفشل تجديد اشتراكك.\nالسبب: {{ failure_reason }}\nتحديث الدفع: {{ update_payment_url }}\nسيُلغى في {{ cancellation_date }}",
                'variables' => json_encode(['customer_name', 'failure_reason', 'update_payment_url', 'cancellation_date', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            ['template_code' => 'subscription.cancelled', 'template_name' => 'تم إلغاء الاشتراك', 'template_category' => 'subscription', 'subject' => 'تم إلغاء اشتراكك', 'html_content' => $this->t('<h2>تم إلغاء الاشتراك</h2><p>مرحباً {{ customer_name }}،</p><p>تم إلغاء اشتراكك كما طلبت.</p><div style="background:#fff3cd;padding:20px;border-radius:8px;margin:25px 0;"><p>ستستمر المزايا حتى <strong>{{ end_date }}</strong>.</p></div><p>نأسف لرؤيتك تغادر. يمكنك إعادة الاشتراك في أي وقت.</p>'.$this->b('إعادة الاشتراك', '{{ resubscribe_url }}')), 'text_content' => "مرحباً {{ customer_name }}\nتم إلغاء اشتراكك.\nستستمر المزايا حتى {{ end_date }}.\nإعادة الاشتراك: {{ resubscribe_url }}", 'variables' => json_encode(['customer_name', 'end_date', 'resubscribe_url', 'store_name']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            // ═══════════════════════════════════════════════════════════
            // ██ الحساب - ACCOUNT ██
            // ═══════════════════════════════════════════════════════════
            ['template_code' => 'account.address_added', 'template_name' => 'تم إضافة عنوان', 'template_category' => 'account', 'subject' => 'تم إضافة عنوان جديد إلى حسابك', 'html_content' => $this->t('<h2>تم إضافة عنوان جديد 📍</h2><p>مرحباً {{ customer_name }}،</p><p>تم إضافة عنوان جديد إلى حسابك.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><h4>العنوان:</h4><p>{{ address_details }}</p></div><div style="background:#fff3cd;padding:15px;border-radius:8px;"><p style="color:#856404;margin:0;">⚠️ إذا لم تقم بذلك، تواصل معنا فوراً.</p></div>'.$this->b('إدارة العناوين', '{{ addresses_url }}')), 'text_content' => "مرحباً {{ customer_name }}\nتم إضافة عنوان جديد:\n{{ address_details }}\nإدارة العناوين: {{ addresses_url }}", 'variables' => json_encode(['customer_name', 'address_details', 'addresses_url', 'store_name']), 'language' => 'ar', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],

            // ═══════════════════════════════════════════════════════════
            // ██ الكوبونات - COUPONS ██
            // ═══════════════════════════════════════════════════════════
            [
                'template_code' => 'coupon.created',
                'template_name' => 'كوبون جديد',
                'template_category' => 'coupons',
                'subject' => '🎁 كوبون خصم خاص لك!',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🎁</div><h2 style="color:#28a745;">كوبون خصم خاص!</h2></div><p>مرحباً {{ customer_name }}،</p><p>نقدم لك كوبون خصم حصري!</p><div style="background:linear-gradient(135deg,#28a745,#20c997);color:#fff;padding:30px;border-radius:8px;text-align:center;margin:25px 0;"><p style="font-size:48px;margin:0;">{{ discount_value }}</p><p style="font-size:18px;margin:10px 0;">خصم</p><div style="background:#fff;color:#333;padding:15px 30px;border-radius:5px;margin:20px auto;display:inline-block;"><p style="margin:0;font-size:24px;font-weight:bold;letter-spacing:4px;">{{ coupon_code }}</p></div><p style="margin:10px 0;">صالح حتى {{ expiry_date }}</p></div><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>الشروط:</strong></p><p>{{ conditions }}</p></div>'.$this->b('استخدم الآن', '{{ store_url }}', '#28a745')),
                'text_content' => "مرحباً {{ customer_name }}\nكوبون خصم خاص: {{ discount_value }}\nالكود: {{ coupon_code }}\nصالح حتى: {{ expiry_date }}\nالشروط: {{ conditions }}\nاستخدم: {{ store_url }}",
                'variables' => json_encode(['customer_name', 'coupon_code', 'discount_value', 'expiry_date', 'conditions', 'store_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'coupon.expiring_soon',
                'template_name' => 'كوبون على وشك الانتهاء',
                'template_category' => 'coupons',
                'subject' => '⏰ كوبونك ينتهي قريباً!',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">⏰</div><h2 style="color:#ffc107;">كوبونك ينتهي قريباً!</h2></div><p>مرحباً {{ customer_name }}،</p><p>كوبون الخصم الخاص بك على وشك الانتهاء.</p><div style="background:#fff3cd;padding:30px;border-radius:8px;text-align:center;margin:25px 0;"><p style="color:#856404;font-size:18px;margin:0 0 15px;">كود الخصم</p><div style="background:#fff;padding:15px 30px;border-radius:5px;border:2px dashed #ffc107;display:inline-block;"><p style="margin:0;font-size:24px;font-weight:bold;letter-spacing:4px;color:#333;">{{ coupon_code }}</p></div><p style="color:#dc3545;font-size:20px;margin:20px 0 0;font-weight:bold;">ينتهي في {{ expiry_date }}!</p></div>'.$this->b('استخدم الآن', '{{ store_url }}', '#ffc107')),
                'text_content' => "مرحباً {{ customer_name }}\nكوبونك ينتهي قريباً!\nالكود: {{ coupon_code }}\nينتهي: {{ expiry_date }}\nاستخدم: {{ store_url }}",
                'variables' => json_encode(['customer_name', 'coupon_code', 'expiry_date', 'store_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'coupon.used',
                'template_name' => 'تم استخدام الكوبون',
                'template_category' => 'coupons',
                'subject' => 'تم استخدام كوبونك بنجاح ✅',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">✅</div><h2 style="color:#28a745;">تم استخدام كوبونك!</h2></div><p>مرحباً {{ customer_name }}،</p><p>تم استخدام كوبون الخصم بنجاح على طلبك.</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>الكود:</strong> {{ coupon_code }}</p><p><strong>الطلب:</strong> #{{ order_number }}</p><p><strong>قيمة الخصم:</strong> {{ discount_amount }}</p></div>'),
                'text_content' => "مرحباً {{ customer_name }}\nتم استخدام كوبونك بنجاح!\nالكود: {{ coupon_code }}\nالطلب: #{{ order_number }}\nالخصم: {{ discount_amount }}",
                'variables' => json_encode(['customer_name', 'coupon_code', 'order_number', 'discount_amount', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ═══════════════════════════════════════════════════════════
            // ██ الإشعارات - NOTIFICATIONS ██
            // ═══════════════════════════════════════════════════════════
            [
                'template_code' => 'notification.general',
                'template_name' => 'إشعار عام',
                'template_category' => 'notifications',
                'subject' => '📢 {{ notification_title }}',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">📢</div><h2 style="color:#667eea;">{{ notification_title }}</h2></div><p>مرحباً {{ customer_name }}،</p><div style="background:#f8f9fa;padding:20px;border-radius:8px;margin:25px 0;">{{ notification_content }}</div>'.$this->b('اقرأ المزيد', '{{ notification_url }}')),
                'text_content' => "مرحباً {{ customer_name }}\n{{ notification_title }}\n{{ notification_content }}\nالمزيد: {{ notification_url }}",
                'variables' => json_encode(['customer_name', 'notification_title', 'notification_content', 'notification_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'notification.system',
                'template_name' => 'إشعار نظام',
                'template_category' => 'notifications',
                'subject' => '🔔 تحديث هام - {{ store_name }}',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🔔</div><h2 style="color:#17a2b8;">تحديث هام</h2></div><p>مرحباً {{ customer_name }}،</p><div style="background:#d1ecf1;padding:20px;border-radius:8px;border-right:4px solid #17a2b8;margin:25px 0;"><p style="color:#0c5460;margin:0;"><strong>{{ system_message }}</strong></p></div><p>{{ system_details }}</p>'),
                'text_content' => "مرحباً {{ customer_name }}\nتحديث هام:\n{{ system_message }}\n{{ system_details }}",
                'variables' => json_encode(['customer_name', 'system_message', 'system_details', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'notification.maintenance',
                'template_name' => 'إشعار صيانة',
                'template_category' => 'notifications',
                'subject' => '🔧 صيانة مجدولة - {{ store_name }}',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🔧</div><h2 style="color:#ffc107;">صيانة مجدولة</h2></div><p>مرحباً {{ customer_name }}،</p><p>سنقوم بصيانة مجدولة لتحسين خدماتنا.</p><div style="background:#fff3cd;padding:20px;border-radius:8px;margin:25px 0;"><p><strong>وقت البدء:</strong> {{ start_time }}</p><p><strong>وقت الانتهاء المتوقع:</strong> {{ end_time }}</p><p><strong>المدة:</strong> {{ duration }}</p></div><p>قد لا تتمكن من الوصول إلى الموقع خلال هذه الفترة.</p>'),
                'text_content' => "مرحباً {{ customer_name }}\nصيانة مجدولة:\nالبدء: {{ start_time }}\nالانتهاء: {{ end_time }}\nالمدة: {{ duration }}",
                'variables' => json_encode(['customer_name', 'start_time', 'end_time', 'duration', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'template_code' => 'notification.security_alert',
                'template_name' => 'تنبيه أمني',
                'template_category' => 'notifications',
                'subject' => '🚨 تنبيه أمني هام',
                'html_content' => $this->t('<div style="text-align:center;"><div style="font-size:60px;">🚨</div><h2 style="color:#dc3545;">تنبيه أمني</h2></div><p>مرحباً {{ customer_name }}،</p><div style="background:#f8d7da;padding:20px;border-radius:8px;border-right:4px solid #dc3545;margin:25px 0;"><p style="color:#721c24;margin:0;"><strong>{{ alert_message }}</strong></p></div><p>{{ alert_details }}</p><div style="background:#fff3cd;padding:15px;border-radius:8px;margin-top:20px;"><p style="color:#856404;margin:0;">💡 نوصي بـ: {{ recommended_action }}</p></div>'.$this->b('تأمين الحساب', '{{ security_url }}', '#dc3545')),
                'text_content' => "مرحباً {{ customer_name }}\nتنبيه أمني:\n{{ alert_message }}\n{{ alert_details }}\nنوصي: {{ recommended_action }}\nتأمين: {{ security_url }}",
                'variables' => json_encode(['customer_name', 'alert_message', 'alert_details', 'recommended_action', 'security_url', 'store_name']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ═══════════════════════════════════════════════════════════
            // ██ تفاعل المنتجات - PRODUCT FEEDBACK ██
            // ═══════════════════════════════════════════════════════════
            [
                'template_code' => 'review.replied',
                'template_name' => 'رد الإدارة على تقييم',
                'template_category' => 'notifications',
                'subject' => 'ردٌّ على تقييمك في {{ store_name }}',
                'html_content' => $this->t('<h2>ردٌّ على تقييمك 💬</h2><p>مرحباً {{ customer_name }}،</p><p>ردّت إدارة المتجر على تقييمك للمنتج <strong>{{ product_name }}</strong>:</p><div style="background:#eef0fc;border-right:3px solid #667eea;padding:14px 18px;border-radius:8px;margin:18px 0;">{{ admin_reply }}</div>'.$this->b('عرض المنتج', '{{ product_url }}')),
                'text_content' => "مرحباً {{ customer_name }}\nردّت الإدارة على تقييمك للمنتج {{ product_name }}:\n{{ admin_reply }}\nعرض المنتج: {{ product_url }}",
                'variables' => json_encode(['customer_name', 'product_name', 'review_title', 'admin_reply', 'product_url', 'store_name', 'store_url']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'template_code' => 'question.answered',
                'template_name' => 'الإجابة على سؤال منتج',
                'template_category' => 'notifications',
                'subject' => 'تمّت الإجابة على سؤالك في {{ store_name }}',
                'html_content' => $this->t('<h2>تمّت الإجابة على سؤالك ✅</h2><p>مرحباً {{ customer_name }}،</p><p>أجابت إدارة المتجر عن سؤالك حول المنتج <strong>{{ product_name }}</strong>:</p><p style="color:#64748b;"><strong>سؤالك:</strong> {{ question }}</p><div style="background:#e9f7ef;border-right:3px solid #28a745;padding:14px 18px;border-radius:8px;margin:18px 0;"><strong>الإجابة:</strong> {{ answer }}</div>'.$this->b('عرض المنتج', '{{ product_url }}')),
                'text_content' => "مرحباً {{ customer_name }}\nسؤالك عن {{ product_name }}: {{ question }}\nالإجابة: {{ answer }}\nعرض المنتج: {{ product_url }}",
                'variables' => json_encode(['customer_name', 'product_name', 'question', 'answer', 'product_url', 'store_name', 'store_url']),
                'language' => 'ar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $seeded = $this->seedMissingRows('email_templates', $templates, 'template_code', false);

        $this->reportSeeded($seeded, 'قوالب البريد');
    }

    /**
     * قالب HTML الأساسي المحسن - متوافق مع الجوال ومقلص
     * Enhanced responsive base HTML template - Mobile friendly & Compact
     */
    private function t(string $c): string
    {
        return '<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ store_name }}</title>
    <style type="text/css">
        @media only screen and (max-width: 480px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .email-content { padding: 20px 16px !important; }
            .email-header { padding: 20px 16px !important; }
            .email-footer { padding: 20px 16px !important; }
            .logo-icon { width: 44px !important; height: 44px !important; }
            .logo-emoji { font-size: 22px !important; }
            .store-title { font-size: 18px !important; }
            .content-text { font-size: 14px !important; }
            .otp-box { font-size: 24px !important; padding: 12px 20px !important; letter-spacing: 6px !important; }
            .cta-button { padding: 12px 28px !important; font-size: 14px !important; }
            .footer-text { font-size: 11px !important; }
            .copyright-text { font-size: 10px !important; }
        }
    </style>
    <!--[if mso]>
    <style type="text/css">
        table {border-collapse: collapse;}
        .fallback-font {font-family: Arial, sans-serif;}
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f5f7fa; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; direction: rtl; -webkit-font-smoothing: antialiased; -webkit-text-size-adjust: 100%;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f5f7fa;">
        <tr>
            <td align="center" style="padding: 24px 12px;">
                <!-- Main Container -->
                <table role="presentation" class="email-container" width="480" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06); overflow: hidden; max-width: 100%;">
                    
                    <!-- Header -->
                    <tr>
                        <td class="email-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px 20px; text-align: center;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <div class="logo-icon" style="width: 48px; height: 48px; background-color: rgba(255,255,255,0.15); border-radius: 50%; margin: 0 auto 10px; line-height: 48px; text-align: center;">
                                            <span class="logo-emoji" style="font-size: 24px; color: #fff;">🛒</span>
                                        </div>
                                        <h1 class="store-title" style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 600;">{{ store_name }}</h1>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Content Area -->
                    <tr>
                        <td class="email-content content-text" style="padding: 28px 24px; color: #4a5568; font-size: 14px; line-height: 1.7;">
                            '.$c.'
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td class="email-footer" style="background-color: #f8fafc; padding: 20px 24px; border-top: 1px solid #edf2f7;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <p class="footer-text" style="margin: 0 0 6px; color: #64748b; font-size: 13px; font-weight: 600;">{{ store_name }}</p>
                                        <p class="footer-text" style="margin: 0 0 4px; color: #94a3b8; font-size: 11px;">
                                            {{ store_phone }} • {{ support_email }}
                                        </p>
                                        <p class="footer-text" style="margin: 0; color: #94a3b8; font-size: 10px;">{{ store_address }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                </table>
                
                <!-- Copyright -->
                <table role="presentation" class="email-container" width="480" cellspacing="0" cellpadding="0" style="max-width: 100%;">
                    <tr>
                        <td align="center" style="padding: 16px 12px;">
                            <p class="copyright-text" style="margin: 0; color: #94a3b8; font-size: 10px;">
                                © '.date('Y').' {{ store_name }}
                            </p>
                        </td>
                    </tr>
                </table>
                
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * زر CTA مقلص ومتوافق مع الجوال
     * Compact responsive CTA button
     */
    private function b(string $t, string $u, string $c = '#667eea'): string
    {
        return '<div style="text-align: center; margin: 24px 0;">
            <a href="'.$u.'" class="cta-button" style="display: inline-block; background-color: '.$c.'; color: #ffffff; padding: 12px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">'.$t.'</a>
        </div>';
    }
}
