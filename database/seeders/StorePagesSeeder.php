<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Store Pages Seeder
 * سيدر صفحات المتجر الثابتة
 */
class StorePagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * إدراج صفحات المتجر الأساسية
     */
    public function run(): void
    {
        $pages = [
            // صفحة من نحن
            [
                'title' => 'من نحن',
                'title_en' => 'About Us',
                'slug' => 'about-us',
                'content' => $this->getAboutUsContent(),
                'content_en' => $this->getAboutUsContentEn(),
                'meta_title' => 'من نحن - متجر فوت الرئيسي',
                'meta_description' => 'تعرف على قصتنا ورسالتنا في تقديم أفضل الخدمات والمنتجات لعملائنا الكرام',
                'type' => 'page',
                'is_active' => true,
                'sort_order' => 1
            ],

            // صفحة شروط الخدمة
            [
                'title' => 'شروط الخدمة',
                'title_en' => 'Terms of Service',
                'slug' => 'terms-of-service',
                'content' => $this->getTermsContent(),
                'content_en' => $this->getTermsContentEn(),
                'meta_title' => 'شروط الخدمة - متجر فوت الرئيسي',
                'meta_description' => 'اطلع على شروط وأحكام استخدام متجرنا الإلكتروني',
                'type' => 'policy',
                'is_active' => true,
                'sort_order' => 2
            ],

            // صفحة سياسة الخصوصية
            [
                'title' => 'سياسة الخصوصية',
                'title_en' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'content' => $this->getPrivacyContent(),
                'content_en' => $this->getPrivacyContentEn(),
                'meta_title' => 'سياسة الخصوصية - متجر فوت الرئيسي',
                'meta_description' => 'تعرف على كيفية حماية خصوصيتك وبياناتك الشخصية',
                'type' => 'policy',
                'is_active' => true,
                'sort_order' => 3
            ],

            // صفحة سياسة الشحن والتوصيل
            [
                'title' => 'سياسة الشحن والتوصيل',
                'title_en' => 'Shipping & Delivery Policy',
                'slug' => 'shipping-delivery-policy',
                'content' => $this->getShippingContent(),
                'content_en' => $this->getShippingContentEn(),
                'meta_title' => 'سياسة الشحن والتوصيل - متجر فوت الرئيسي',
                'meta_description' => 'معلومات مفصلة عن خدمات الشحن والتوصيل المتاحة',
                'type' => 'policy',
                'is_active' => true,
                'sort_order' => 4
            ],

            // صفحة سياسة الإرجاع والاستبدال
            [
                'title' => 'سياسة الإرجاع والاستبدال',
                'title_en' => 'Return & Exchange Policy',
                'slug' => 'return-exchange-policy',
                'content' => $this->getReturnContent(),
                'content_en' => $this->getReturnContentEn(),
                'meta_title' => 'سياسة الإرجاع والاستبدال - متجر فوت الرئيسي',
                'meta_description' => 'شروط وأحكام إرجاع واستبدال المنتجات',
                'type' => 'policy',
                'is_active' => true,
                'sort_order' => 5
            ],

            // صفحة الأسئلة الشائعة
            [
                'title' => 'الأسئلة الشائعة',
                'title_en' => 'FAQ',
                'slug' => 'faq',
                'content' => $this->getFaqContent(),
                'content_en' => $this->getFaqContentEn(),
                'meta_title' => 'الأسئلة الشائعة - متجر فوت الرئيسي',
                'meta_description' => 'إجابات على الأسئلة الأكثر شيوعاً حول متجرنا وخدماتنا',
                'type' => 'faq',
                'is_active' => true,
                'sort_order' => 6
            ],

            // صفحة اتصل بنا
            [
                'title' => 'اتصل بنا',
                'title_en' => 'Contact Us',
                'slug' => 'contact-us',
                'content' => $this->getContactContent(),
                'content_en' => $this->getContactContentEn(),
                'meta_title' => 'اتصل بنا - متجر فوت الرئيسي',
                'meta_description' => 'تواصل معنا للاستفسارات والدعم الفني',
                'type' => 'page',
                'is_active' => true,
                'sort_order' => 7
            ],

            // صفحة كيفية الطلب
            [
                'title' => 'كيفية الطلب',
                'title_en' => 'How to Order',
                'slug' => 'how-to-order',
                'content' => $this->getHowToOrderContent(),
                'content_en' => $this->getHowToOrderContentEn(),
                'meta_title' => 'كيفية الطلب - متجر فوت الرئيسي',
                'meta_description' => 'دليل شامل لكيفية إتمام طلبك بسهولة',
                'type' => 'page',
                'is_active' => true,
                'sort_order' => 8
            ]
        ];

        foreach ($pages as $page) {
            DB::table('store_pages')->updateOrInsert(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }

        $this->command->info('تم إدراج صفحات المتجر بنجاح');
    }

    /**
     * محتوى صفحة من نحن
     */
    private function getAboutUsContent(): string
    {
        return '<div class="about-us-content">
            <h2>من نحن</h2>
            <p>متجر فوت الرئيسي هو متجر إلكتروني رائد يهدف إلى تقديم أفضل المنتجات والخدمات لعملائنا الكرام في جميع أنحاء المملكة العربية السعودية.</p>
            
            <h3>رؤيتنا</h3>
            <p>أن نكون المتجر الإلكتروني الأول والأكثر ثقة في المنطقة، نقدم تجربة تسوق استثنائية تلبي توقعات عملائنا وتتجاوزها.</p>
            
            <h3>رسالتنا</h3>
            <p>نلتزم بتوفير منتجات عالية الجودة بأسعار منافسة، مع خدمة عملاء متميزة وتوصيل سريع وآمن.</p>
            
            <h3>قيمنا</h3>
            <ul>
                <li><strong>الجودة:</strong> نختار منتجاتنا بعناية فائقة لضمان أعلى معايير الجودة</li>
                <li><strong>الثقة:</strong> نبني علاقات طويلة الأمد مع عملائنا قائمة على الثقة والشفافية</li>
                <li><strong>السرعة:</strong> نوفر خدمة توصيل سريعة لتلبية احتياجاتكم في أسرع وقت ممكن</li>
                <li><strong>الابتكار:</strong> نسعى دائماً لتطوير خدماتنا ومواكبة أحدث التقنيات</li>
            </ul>
            
            <h3>لماذا نحن؟</h3>
            <ul>
                <li>أكثر من 10,000 منتج متنوع</li>
                <li>توصيل مجاني للطلبات أكثر من 200 ريال</li>
                <li>خدمة عملاء 24/7</li>
                <li>ضمان إرجاع المنتجات خلال 14 يوم</li>
                <li>طرق دفع متنوعة وآمنة</li>
            </ul>
        </div>';
    }

    /**
     * About Us content in English
     */
    private function getAboutUsContentEn(): string
    {
        return '<div class="about-us-content">
            <h2>About Us</h2>
            <p>Fawt Main Store is a leading e-commerce platform dedicated to providing the best products and services to our valued customers across Saudi Arabia.</p>
            
            <h3>Our Vision</h3>
            <p>To be the first and most trusted e-commerce store in the region, delivering an exceptional shopping experience that meets and exceeds our customers\' expectations.</p>
            
            <h3>Our Mission</h3>
            <p>We are committed to providing high-quality products at competitive prices, with outstanding customer service and fast, secure delivery.</p>
            
            <h3>Our Values</h3>
            <ul>
                <li><strong>Quality:</strong> We carefully select our products to ensure the highest quality standards</li>
                <li><strong>Trust:</strong> We build long-term relationships with our customers based on trust and transparency</li>
                <li><strong>Speed:</strong> We provide fast delivery service to meet your needs as quickly as possible</li>
                <li><strong>Innovation:</strong> We constantly strive to develop our services and keep up with the latest technologies</li>
            </ul>
            
            <h3>Why Choose Us?</h3>
            <ul>
                <li>Over 10,000 diverse products</li>
                <li>Free delivery for orders over 200 SAR</li>
                <li>24/7 customer service</li>
                <li>14-day return guarantee</li>
                <li>Various secure payment methods</li>
            </ul>
        </div>';
    }

    /**
     * محتوى شروط الخدمة
     */
    private function getTermsContent(): string
    {
        return '<div class="terms-content">
            <h2>شروط وأحكام الاستخدام</h2>
            <p><strong>تاريخ آخر تحديث:</strong> ' . date('Y-m-d') . '</p>
            
            <h3>1. قبول الشروط</h3>
            <p>باستخدامك لمتجر فوت الرئيسي، فإنك توافق على الالتزام بهذه الشروط والأحكام.</p>
            
            <h3>2. استخدام الموقع</h3>
            <ul>
                <li>يجب أن تكون 18 عاماً أو أكثر لاستخدام خدماتنا</li>
                <li>تلتزم بتقديم معلومات صحيحة ودقيقة</li>
                <li>تحافظ على سرية بيانات حسابك</li>
            </ul>
            
            <h3>3. الطلبات والأسعار</h3>
            <ul>
                <li>جميع الأسعار معروضة بالريال السعودي وتشمل ضريبة القيمة المضافة</li>
                <li>نحتفظ بالحق في تغيير الأسعار دون إشعار مسبق</li>
                <li>تأكيد الطلب يعتبر اتفاقية ملزمة</li>
            </ul>
            
            <h3>4. الدفع</h3>
            <p>نقبل الدفع بالطرق التالية:</p>
            <ul>
                <li>بطاقات الائتمان والخصم</li>
                <li>الدفع عند الاستلام</li>
                <li>المحفظة الإلكترونية</li>
                <li>التحويل البنكي</li>
            </ul>
            
            <h3>5. الشحن والتوصيل</h3>
            <ul>
                <li>نوفر خدمة التوصيل في جميع أنحاء المملكة</li>
                <li>مدة التوصيل من 1-5 أيام عمل</li>
                <li>الشحن مجاني للطلبات أكثر من 200 ريال</li>
            </ul>
            
            <h3>6. الإرجاع والاستبدال</h3>
            <ul>
                <li>يمكن إرجاع المنتجات خلال 14 يوم من تاريخ الاستلام</li>
                <li>يجب أن تكون المنتجات في حالتها الأصلية</li>
                <li>تكلفة الإرجاع على العميل ما لم يكن المنتج معيباً</li>
            </ul>
            
            <h3>7. المسؤولية</h3>
            <p>نحن غير مسؤولين عن أي أضرار غير مباشرة قد تنتج عن استخدام منتجاتنا أو خدماتنا.</p>
            
            <h3>8. التعديل على الشروط</h3>
            <p>نحتفظ بالحق في تعديل هذه الشروط في أي وقت. سيتم إشعار العملاء بأي تغييرات مهمة.</p>
            
            <h3>9. التواصل</h3>
            <p>للاستفسارات حول هذه الشروط، يرجى التواصل معنا على:</p>
            <ul>
                <li>البريد الإلكتروني: support@store.com</li>
                <li>الهاتف: +966501234567</li>
            </ul>
        </div>';
    }

    private function getTermsContentEn(): string
    {
        return '<div class="terms-content">
            <h2>Terms and Conditions of Use</h2>
            <p><strong>Last updated:</strong> ' . date('Y-m-d') . '</p>
            
            <h3>1. Acceptance of Terms</h3>
            <p>By using Fawt Main Store, you agree to comply with these terms and conditions.</p>
            
            <h3>2. Use of Website</h3>
            <ul>
                <li>You must be 18 years or older to use our services</li>
                <li>You agree to provide accurate and complete information</li>
                <li>You maintain the confidentiality of your account data</li>
            </ul>
            
            <h3>3. Orders and Prices</h3>
            <ul>
                <li>All prices are displayed in Saudi Riyal and include VAT</li>
                <li>We reserve the right to change prices without prior notice</li>
                <li>Order confirmation constitutes a binding agreement</li>
            </ul>
            
            <h3>4. Payment</h3>
            <p>We accept payment through the following methods:</p>
            <ul>
                <li>Credit and debit cards</li>
                <li>Cash on delivery</li>
                <li>Electronic wallet</li>
                <li>Bank transfer</li>
            </ul>
        </div>';
    }

    // باقي المحتوى للاختصار
    private function getPrivacyContent(): string
    {
        return '<div class="privacy-content">
            <h2>سياسة الخصوصية</h2>
            <p>نحن نحترم خصوصيتك ونلتزم بحماية المعلومات الشخصية التي تشاركها معنا.</p>
            <h3>جمع المعلومات</h3>
            <p>نجمع المعلومات التي تقدمها لنا عند التسجيل أو الشراء، بما في ذلك الاسم والبريد الإلكتروني ورقم الهاتف.</p>
            <h3>استخدام المعلومات</h3>
            <p>نستخدم معلوماتك لمعالجة طلباتك وتحسين خدماتنا والتواصل معك.</p>
            <h3>حماية المعلومات</h3>
            <p>نتخذ إجراءات أمنية متقدمة لحماية معلوماتك من الوصول غير المصرح به.</p>
        </div>';
    }

    private function getPrivacyContentEn(): string
    {
        return '<div class="privacy-content">
            <h2>Privacy Policy</h2>
            <p>We respect your privacy and are committed to protecting the personal information you share with us.</p>
            <h3>Information Collection</h3>
            <p>We collect information you provide when registering or purchasing, including name, email, and phone number.</p>
        </div>';
    }

    private function getShippingContent(): string
    {
        return '<div class="shipping-content">
            <h2>سياسة الشحن والتوصيل</h2>
            <h3>مناطق التوصيل</h3>
            <p>نوفر خدمة التوصيل في جميع أنحاء المملكة العربية السعودية.</p>
            <h3>أوقات التوصيل</h3>
            <ul>
                <li>التوصيل العادي: 3-5 أيام عمل</li>
                <li>التوصيل السريع: 1-2 أيام عمل</li>
            </ul>
            <h3>رسوم الشحن</h3>
            <ul>
                <li>مجاني للطلبات أكثر من 200 ريال</li>
                <li>25 ريال للطلبات الأقل من 200 ريال</li>
                <li>35 ريال للشحن السريع</li>
            </ul>
        </div>';
    }

    private function getShippingContentEn(): string
    {
        return '<div class="shipping-content">
            <h2>Shipping & Delivery Policy</h2>
            <h3>Delivery Areas</h3>
            <p>We provide delivery service throughout Saudi Arabia.</p>
        </div>';
    }

    private function getReturnContent(): string
    {
        return '<div class="return-content">
            <h2>سياسة الإرجاع والاستبدال</h2>
            <h3>شروط الإرجاع</h3>
            <ul>
                <li>يمكن إرجاع المنتجات خلال 14 يوم</li>
                <li>يجب أن تكون المنتجات في حالتها الأصلية</li>
                <li>يجب الاحتفاظ بالإيصال الأصلي</li>
            </ul>
            <h3>كيفية الإرجاع</h3>
            <p>تواصل معنا لتنسيق عملية الإرجاع والاستلام.</p>
        </div>';
    }

    private function getReturnContentEn(): string
    {
        return '<div class="return-content">
            <h2>Return & Exchange Policy</h2>
            <p>Products can be returned within 14 days in original condition.</p>
        </div>';
    }

    private function getFaqContent(): string
    {
        return '<div class="faq-content">
            <h2>الأسئلة الشائعة</h2>
            <div class="faq-item">
                <h3>كيف يمكنني تتبع طلبي؟</h3>
                <p>يمكنك تتبع طلبك من خلال حسابك أو رابط التتبع المرسل لك بالبريد الإلكتروني.</p>
            </div>
            <div class="faq-item">
                <h3>ما هي طرق الدفع المتاحة؟</h3>
                <p>نقبل البطاقات الائتمانية، الدفع عند الاستلام، والمحفظة الإلكترونية.</p>
            </div>
            <div class="faq-item">
                <h3>كم تستغرق عملية التوصيل؟</h3>
                <p>عادة من 1-5 أيام عمل حسب موقعك.</p>
            </div>
        </div>';
    }

    private function getFaqContentEn(): string
    {
        return '<div class="faq-content">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-item">
                <h3>How can I track my order?</h3>
                <p>You can track your order through your account or the tracking link sent to your email.</p>
            </div>
        </div>';
    }

    private function getContactContent(): string
    {
        return '<div class="contact-content">
            <h2>اتصل بنا</h2>
            <p>نحن هنا لمساعدتك. تواصل معنا عبر:</p>
            <div class="contact-info">
                <p><strong>البريد الإلكتروني:</strong> support@store.com</p>
                <p><strong>الهاتف:</strong> +966501234567</p>
                <p><strong>العنوان:</strong> الرياض، المملكة العربية السعودية</p>
                <p><strong>ساعات العمل:</strong> من السبت إلى الخميس، 9 صباحاً - 6 مساءً</p>
            </div>
        </div>';
    }

    private function getContactContentEn(): string
    {
        return '<div class="contact-content">
            <h2>Contact Us</h2>
            <p>We are here to help you. Contact us via:</p>
            <div class="contact-info">
                <p><strong>Email:</strong> support@store.com</p>
                <p><strong>Phone:</strong> +966501234567</p>
                <p><strong>Address:</strong> Riyadh, Saudi Arabia</p>
                <p><strong>Working Hours:</strong> Saturday to Thursday, 9 AM - 6 PM</p>
            </div>
        </div>';
    }

    private function getHowToOrderContent(): string
    {
        return '<div class="how-to-order-content">
            <h2>كيفية الطلب</h2>
            <div class="step">
                <h3>1. تصفح المنتجات</h3>
                <p>استعرض مجموعتنا الواسعة من المنتجات واختر ما يناسبك.</p>
            </div>
            <div class="step">
                <h3>2. أضف إلى السلة</h3>
                <p>اضغط على "إضافة إلى السلة" للمنتجات التي تريد شراءها.</p>
            </div>
            <div class="step">
                <h3>3. راجع طلبك</h3>
                <p>اذهب إلى السلة وراجع المنتجات والكمية.</p>
            </div>
            <div class="step">
                <h3>4. أدخل بيانات الشحن</h3>
                <p>أدخل عنوان التوصيل ومعلومات التواصل.</p>
            </div>
            <div class="step">
                <h3>5. اختر طريقة الدفع</h3>
                <p>اختر الطريقة المفضلة لك للدفع.</p>
            </div>
            <div class="step">
                <h3>6. أكد الطلب</h3>
                <p>راجع جميع التفاصيل وأكد طلبك.</p>
            </div>
        </div>';
    }

    private function getHowToOrderContentEn(): string
    {
        return '<div class="how-to-order-content">
            <h2>How to Order</h2>
            <div class="step">
                <h3>1. Browse Products</h3>
                <p>Explore our wide range of products and choose what suits you.</p>
            </div>
            <div class="step">
                <h3>2. Add to Cart</h3>
                <p>Click "Add to Cart" for products you want to purchase.</p>
            </div>
        </div>';
    }
}