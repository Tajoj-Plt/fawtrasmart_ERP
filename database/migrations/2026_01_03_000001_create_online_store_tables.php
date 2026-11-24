<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إنشاء جميع جداول المتجر الإلكتروني
     */
    public function up(): void
    {
        // 1. جدول البراندات (العلامات التجارية)
        //
        // كان `Schema::create('brands')` مكشوفاً، والجدول يُنشأ قبله في
        // `Modules/Stock/.../2025_11_05_120000_create_brands_table.php` —
        // فيسقط `migrate` على قاعدة جديدة بـ «Table brands already exists».
        //
        // ولا يصحّ تخطّيه ببساطة: النموذجان `App\Models\Store\Brand` و
        // `Modules\Stock\Models\Brand` كلاهما `$table = 'brands'` بأعمدةٍ
        // مختلفة — نسخة المتجر تريد logo/is_active/sort_order/meta_*،
        // ونسخة المخزون تريد logo_path/status/display_order/country_of_origin.
        // فتخطّي أيٍّ منهما يترك نموذجاً يشير إلى أعمدة غير موجودة.
        //
        // الحل هنا: إنشاءٌ عند الغياب، وإكمالُ الناقص عند الوجود — فيصير
        // الجدول مجموعَ الشكلين ويعمل النموذجان معاً. (التصادم نفسه يبقى
        // قائماً كمسألة تصميم: جدولٌ واحد لمفهومين.)
        if (! Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('logo')->nullable();
                $table->string('website')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->integer('sort_order')->default(0);
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('brands', function (Blueprint $table) {
                if (! Schema::hasColumn('brands', 'logo')) {
                    $table->string('logo')->nullable();
                }
                if (! Schema::hasColumn('brands', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('brands', 'sort_order')) {
                    $table->integer('sort_order')->default(0);
                }
                if (! Schema::hasColumn('brands', 'meta_title')) {
                    $table->string('meta_title')->nullable();
                }
                if (! Schema::hasColumn('brands', 'meta_description')) {
                    $table->text('meta_description')->nullable();
                }
            });
        }

        // إضافة عمود brand_id لجدول المنتجات إذا لم يكن موجوداً
        if (! Schema::hasColumn('products', 'brand_id')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'brand_id')) {
                    $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('brands')->nullOnDelete();
                }
            });
        }

        // 2. جدول عناوين العملاء للتوصيل
        if (! Schema::hasTable('client_addresses')) {
            Schema::create('client_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->string('title')->nullable(); // مثل: المنزل، العمل
                $table->string('receiver_name'); // اسم المستلم
                $table->string('receiver_phone'); // رقم هاتف المستلم
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->string('region')->nullable();
                $table->string('district')->nullable(); // الحي
                $table->string('street')->nullable();
                $table->string('building_number')->nullable();
                $table->string('floor')->nullable();
                $table->string('apartment')->nullable();
                $table->string('postal_code')->nullable();
                $table->text('address_line')->nullable(); // العنوان الكامل
                $table->text('notes')->nullable(); // ملاحظات للتوصيل
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['client_id', 'is_default']);
            });
        }

        // 3. جدول طرق الشحن
        if (! Schema::hasTable('shipping_methods')) {
            Schema::create('shipping_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->string('logo')->nullable();
                $table->enum('type', ['free', 'flat_rate', 'per_item', 'per_weight', 'per_distance', 'calculated'])->default('flat_rate');
                $table->decimal('cost', 10, 2)->default(0);
                $table->decimal('min_order_amount', 10, 2)->nullable(); // الحد الأدنى للطلب
                $table->decimal('free_shipping_threshold', 10, 2)->nullable(); // شحن مجاني فوق هذا المبلغ
                $table->integer('estimated_days_min')->nullable(); // أقل عدد أيام للتوصيل
                $table->integer('estimated_days_max')->nullable(); // أقصى عدد أيام للتوصيل
                $table->json('available_cities')->nullable(); // المدن المتاحة
                $table->json('available_regions')->nullable(); // المناطق المتاحة
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 4. جدول مناطق الشحن
        if (! Schema::hasTable('shipping_zones')) {
            Schema::create('shipping_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shipping_method_id')->constrained('shipping_methods')->cascadeOnDelete();
                $table->string('name');
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->string('region')->nullable();
                $table->decimal('cost', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 5. جدول السلة (Cart)
        if (! Schema::hasTable('carts')) {
            Schema::create('carts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
                $table->string('session_id')->nullable(); // للزوار غير المسجلين
                // العمود بلا قيدٍ هنا: `store_coupons` يُنشأ في الفقرة ١٤ من هذا
                // الملف نفسه — أي بعد السلة — فكان `constrained()` يسقط بـ
                // «Foreign key constraint is incorrectly formed». القيد يُضاف
                // في نهاية `up()` بعد اكتمال الجدولين
                $table->unsignedBigInteger('coupon_id')->nullable(); // كود الخصم
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('tax', 12, 2)->default(0);
                $table->decimal('shipping_cost', 10, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['client_id']);
                $table->index(['session_id']);
            });
        }

        // 6. جدول عناصر السلة
        if (! Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('store_house_id')->nullable()->constrained('store_houses')->nullOnDelete();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('tax', 12, 2)->default(0);
                $table->decimal('total', 12, 2);
                $table->json('options')->nullable(); // خيارات إضافية مثل اللون، الحجم
                $table->timestamps();

                $table->unique(['cart_id', 'product_id']);
            });
        }

        // 7. جدول المفضلة / قائمة الأمنيات (Wishlist)
        if (! Schema::hasTable('wishlists')) {
            Schema::create('wishlists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['client_id', 'product_id']);
                $table->index(['client_id']);
            });
        }

        // 8. جدول تقييمات المنتجات
        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete(); // للتأكد من الشراء
                $table->tinyInteger('rating')->unsigned(); // 1-5
                $table->string('title')->nullable();
                $table->text('comment')->nullable();
                $table->json('pros')->nullable(); // المميزات
                $table->json('cons')->nullable(); // العيوب
                $table->json('images')->nullable(); // صور من العميل
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->text('admin_reply')->nullable();
                $table->timestamp('replied_at')->nullable();
                $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
                $table->integer('helpful_count')->default(0); // عدد الإعجابات
                $table->boolean('is_verified_purchase')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->timestamps();

                $table->unique(['product_id', 'client_id']);
                $table->index(['product_id', 'status', 'rating']);
            });
        }

        // 9. جدول إعجابات التقييمات
        if (! Schema::hasTable('product_review_helpfuls')) {
            Schema::create('product_review_helpfuls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_id')->constrained('product_reviews')->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['review_id', 'client_id']);
            });
        }

        // 10. جدول إعدادات المتجر الإلكتروني
        if (! Schema::hasTable('online_store_settings')) {
            Schema::create('online_store_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string'); // string, boolean, integer, json, array
                $table->string('group')->default('general'); // general, appearance, checkout, shipping, payment, seo
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 11. جدول البانرات والإعلانات
        if (! Schema::hasTable('store_banners')) {
            Schema::create('store_banners', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('title_en')->nullable();
                $table->text('description')->nullable();
                $table->string('image');
                $table->string('mobile_image')->nullable();
                $table->string('link')->nullable();
                $table->enum('link_type', ['url', 'product', 'category', 'brand', 'page'])->default('url');
                $table->unsignedBigInteger('link_id')->nullable(); // معرف المنتج/التصنيف/البراند
                $table->enum('position', ['home_slider', 'home_banner', 'sidebar', 'category', 'footer', 'popup'])->default('home_slider');
                $table->integer('sort_order')->default(0);
                $table->timestamp('start_date')->nullable();
                $table->timestamp('end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['position', 'is_active']);
            });
        }

        // 12. جدول المنتجات التي تم مشاهدتها مؤخراً
        if (! Schema::hasTable('recently_viewed_products')) {
            Schema::create('recently_viewed_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
                $table->string('session_id')->nullable();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->timestamp('viewed_at');
                $table->timestamps();

                $table->index(['client_id', 'viewed_at']);
                $table->index(['session_id', 'viewed_at']);
            });
        }

        // 14. جدول الكوبونات الخاصة بالمتجر (إضافة للعروض الموجودة)
        if (! Schema::hasTable('store_coupons')) {
            Schema::create('store_coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->enum('type', ['percentage', 'fixed', 'free_shipping'])->default('percentage');
                $table->decimal('value', 10, 2);
                $table->decimal('min_order_amount', 10, 2)->nullable();
                $table->decimal('max_discount', 10, 2)->nullable(); // الحد الأقصى للخصم
                $table->integer('usage_limit')->nullable(); // الحد الأقصى للاستخدام الكلي
                $table->integer('usage_limit_per_user')->nullable(); // الحد الأقصى لكل مستخدم
                $table->integer('used_count')->default(0);
                $table->json('applicable_products')->nullable(); // منتجات محددة
                $table->json('applicable_categories')->nullable(); // تصنيفات محددة
                $table->json('excluded_products')->nullable(); // منتجات مستثناة
                $table->json('applicable_clients')->nullable(); // عملاء محددين
                $table->boolean('is_first_order_only')->default(false);
                $table->timestamp('start_date')->nullable();
                $table->timestamp('end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 15. جدول استخدام الكوبونات
        if (! Schema::hasTable('store_coupon_usages')) {
            Schema::create('store_coupon_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained('store_coupons')->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->decimal('discount_amount', 10, 2);
                $table->timestamps();

                $table->index(['coupon_id', 'client_id']);
            });
        }

        // 16. جدول أسئلة المنتجات
        if (! Schema::hasTable('product_questions')) {
            Schema::create('product_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->text('question');
                $table->text('answer')->nullable();
                $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('answered_at')->nullable();
                $table->enum('status', ['pending', 'answered', 'rejected'])->default('pending');
                $table->boolean('is_public')->default(true);
                $table->timestamps();

                $table->index(['product_id', 'status']);
            });
        }

        // 17. جدول صفحات المتجر الثابتة
        if (! Schema::hasTable('store_pages')) {
            Schema::create('store_pages', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('title_en')->nullable();
                $table->string('slug')->unique();
                $table->longText('content')->nullable();
                $table->longText('content_en')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->enum('type', ['page', 'faq', 'policy'])->default('page');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 18. جدول الطلبات التفصيلي
        if (! Schema::hasTable('online_orders')) {
            Schema::create('online_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number')->unique(); // رقم الطلب الفريد
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->foreignId('address_id')->nullable()->constrained('client_addresses')->nullOnDelete();
                $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();
                $table->foreignId('coupon_id')->nullable()->constrained('store_coupons')->nullOnDelete();

                // حالة الطلب
                $table->enum('status', [
                    'pending',           // قيد الانتظار
                    'confirmed',         // تم التأكيد
                    'processing',        // جاري التجهيز
                    'ready_for_shipping', // جاهز للشحن
                    'shipped',           // تم الشحن
                    'out_for_delivery',  // في الطريق
                    'delivered',         // تم التسليم
                    'cancelled',         // ملغي
                    'returned',          // مرتجع
                    'refunded',           // مسترد
                ])->default('pending');

                // طريقة الدفع وحالته
                $table->enum('payment_method', ['cash', 'card', 'wallet', 'bank_transfer', 'cod'])->default('cod'); // cod = الدفع عند الاستلام
                $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded', 'partial'])->default('pending');
                $table->timestamp('paid_at')->nullable();

                // المبالغ الأساسية
                $table->decimal('subtotal', 12, 2)->default(0); // المجموع الفرعي (المنتجات فقط)
                $table->decimal('discount_amount', 12, 2)->default(0); // قيمة الخصم
                $table->decimal('tax_amount', 12, 2)->default(0); // قيمة الضريبة

                // رسوم الشحن والتوصيل
                $table->decimal('shipping_cost', 10, 2)->default(0); // رسوم الشحن الأساسية
                $table->decimal('express_shipping_fee', 10, 2)->default(0); // رسوم الشحن السريع
                $table->decimal('remote_area_fee', 10, 2)->default(0); // رسوم المناطق النائية

                // الرسوم الإضافية
                $table->decimal('cod_fee', 10, 2)->default(0); // رسوم الدفع عند الاستلام
                $table->decimal('packaging_fee', 10, 2)->default(0); // رسوم التغليف الخاص
                $table->decimal('gift_wrap_fee', 10, 2)->default(0); // رسوم تغليف الهدايا
                $table->decimal('insurance_fee', 10, 2)->default(0); // رسوم التأمين على الشحنة
                $table->decimal('handling_fee', 10, 2)->default(0); // رسوم المناولة
                $table->decimal('service_fee', 10, 2)->default(0); // رسوم الخدمة
                $table->decimal('custom_fee', 10, 2)->default(0); // رسوم إضافية مخصصة
                $table->string('custom_fee_description')->nullable(); // وصف الرسوم المخصصة

                // إجمالي الرسوم الإضافية والمجموع النهائي
                $table->decimal('total_extra_fees', 12, 2)->default(0); // إجمالي الرسوم الإضافية
                $table->decimal('total_amount', 12, 2)->default(0); // المبلغ الإجمالي النهائي

                // معلومات الشحن والتتبع
                $table->string('tracking_number')->nullable(); // رقم التتبع
                $table->string('shipping_company')->nullable(); // شركة الشحن
                $table->string('shipping_company_phone')->nullable(); // رقم هاتف شركة الشحن
                $table->string('driver_name')->nullable(); // اسم السائق/المندوب
                $table->string('driver_phone')->nullable(); // رقم هاتف السائق
                $table->timestamp('estimated_delivery_date')->nullable(); // تاريخ التسليم المتوقع
                $table->timestamp('shipped_at')->nullable(); // تاريخ الشحن
                $table->timestamp('delivered_at')->nullable(); // تاريخ التسليم الفعلي

                // عنوان الشحن (نسخة مخزنة)
                $table->string('shipping_name')->nullable(); // اسم المستلم
                $table->string('shipping_phone')->nullable(); // رقم الهاتف
                $table->string('shipping_city')->nullable(); // المدينة
                $table->string('shipping_region')->nullable(); // المنطقة
                $table->text('shipping_address')->nullable(); // العنوان الكامل
                $table->decimal('shipping_latitude', 10, 8)->nullable();
                $table->decimal('shipping_longitude', 11, 8)->nullable();

                // خيارات إضافية
                $table->boolean('is_gift')->default(false); // هل الطلب هدية
                $table->text('gift_message')->nullable(); // رسالة الهدية
                $table->boolean('is_express')->default(false); // شحن سريع
                $table->boolean('requires_signature')->default(false); // يتطلب توقيع عند الاستلام
                $table->boolean('leave_at_door')->default(false); // ترك عند الباب

                // ملاحظات
                $table->text('customer_notes')->nullable(); // ملاحظات العميل
                $table->text('admin_notes')->nullable(); // ملاحظات الإدارة
                $table->text('delivery_notes')->nullable(); // ملاحظات التوصيل

                // سبب الإلغاء/الإرجاع
                $table->string('cancel_reason')->nullable();
                $table->text('cancel_notes')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

                // معلومات إدارية
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['client_id', 'status']);
                $table->index(['order_number']);
                $table->index(['status', 'created_at']);
                $table->index(['payment_status']);
            });
        }

        // 18.1 جدول عناصر الطلب
        if (! Schema::hasTable('online_order_items')) {
            Schema::create('online_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('online_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('store_house_id')->nullable()->constrained('store_houses')->nullOnDelete();
                $table->string('product_name'); // اسم المنتج وقت الطلب
                $table->string('product_sku')->nullable(); // كود المنتج
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 12, 2); // سعر الوحدة
                $table->decimal('discount', 12, 2)->default(0); // خصم على المنتج
                $table->decimal('tax', 12, 2)->default(0); // ضريبة المنتج
                $table->decimal('total', 12, 2); // الإجمالي
                $table->json('options')->nullable(); // خيارات (لون، حجم، إلخ)
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['order_id']);
            });
        }

        // 18.2 جدول سجل تتبع حالات الطلب
        if (! Schema::hasTable('online_order_status_history')) {
            Schema::create('online_order_status_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('online_orders')->cascadeOnDelete();
                $table->string('status');
                $table->text('notes')->nullable();
                $table->string('location')->nullable(); // موقع الشحنة
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['order_id', 'created_at']);
            });
        }

        // 18.3 جدول الرسوم الإضافية المخصصة للطلبات
        if (! Schema::hasTable('online_order_extra_fees')) {
            Schema::create('online_order_extra_fees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('online_orders')->cascadeOnDelete();
                $table->string('name'); // اسم الرسوم
                $table->text('description')->nullable();
                $table->decimal('amount', 10, 2);
                $table->enum('type', ['fixed', 'percentage'])->default('fixed');
                $table->boolean('is_taxable')->default(false); // هل تطبق عليها ضريبة
                $table->boolean('is_refundable')->default(true); // هل يمكن استردادها
                $table->timestamps();

                $table->index(['order_id']);
            });
        }

        // 19. جدول أجهزة العميل للإشعارات
        if (! Schema::hasTable('client_devices')) {
            Schema::create('client_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->string('device_token');
                $table->enum('platform', ['ios', 'android', 'web'])->default('web');
                $table->string('device_name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->unique(['client_id', 'device_token']);
            });
        }

        // 19.١ قيد كوبون السلة — مؤجَّل إلى هنا لأن `store_coupons` يُنشأ
        // بعد `carts` في هذا الملف، والقيد لا يصحّ قبل وجود الجدولين
        Schema::table('carts', function (Blueprint $table) {
            if (! SchemaGuard::hasForeignKey('carts', 'carts_coupon_id_foreign')) {
                $table->foreign('coupon_id')->references('id')->on('store_coupons')->nullOnDelete();
            }
        });

        // 20. إضافة حقول للمنتجات الخاصة بالمتجر الإلكتروني
        if (! Schema::hasColumn('products', 'weight')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'weight')) {
                    $table->decimal('weight', 10, 3)->nullable()->after('type'); // الوزن بالكيلو
                }
                if (! Schema::hasColumn('products', 'dimensions')) {
                    $table->json('dimensions')->nullable()->after('weight'); // الأبعاد
                }
                if (! Schema::hasColumn('products', 'is_returnable')) {
                    $table->boolean('is_returnable')->default(true)->after('featured_product');
                }
                if (! Schema::hasColumn('products', 'return_days')) {
                    $table->integer('return_days')->default(14)->after('is_returnable');
                }
                if (! Schema::hasColumn('products', 'view_count')) {
                    $table->integer('view_count')->default(0)->after('return_days');
                }
                if (! Schema::hasColumn('products', 'order_count')) {
                    $table->integer('order_count')->default(0)->after('view_count');
                }
            });
        }

        // 21. إضافة حقول للعملاء الخاصة بالمتجر
        if (! Schema::hasColumn('clients', 'avatar')) {
            Schema::table('clients', function (Blueprint $table) {
                if (! Schema::hasColumn('clients', 'avatar')) {
                    $table->string('avatar')->nullable()->after('attachments');
                }
                if (! Schema::hasColumn('clients', 'password')) {
                    $table->string('password')->nullable()->after('email');
                }
                if (! Schema::hasColumn('clients', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable()->after('password');
                }
                $table->rememberToken()->after('email_verified_at');
                if (! Schema::hasColumn('clients', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable();
                }
                if (! Schema::hasColumn('clients', 'last_login_ip')) {
                    $table->string('last_login_ip')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف الجداول بالترتيب العكسي
        Schema::dropIfExists('client_devices');
        Schema::dropIfExists('online_order_extra_fees');
        Schema::dropIfExists('online_order_status_history');
        Schema::dropIfExists('online_order_items');
        Schema::dropIfExists('online_orders');
        Schema::dropIfExists('store_pages');
        Schema::dropIfExists('product_questions');
        Schema::dropIfExists('store_coupon_usages');
        Schema::dropIfExists('store_coupons');
        Schema::dropIfExists('recently_viewed_products');
        Schema::dropIfExists('store_banners');
        Schema::dropIfExists('online_store_settings');
        Schema::dropIfExists('product_review_helpfuls');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('client_addresses');

        // إزالة العمود من جدول المنتجات
        if (Schema::hasColumn('products', 'brand_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['brand_id']);
                $table->dropColumn('brand_id');
            });
        }

        // إزالة الحقول الإضافية من المنتجات
        if (Schema::hasColumn('products', 'weight')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn([
                    'weight', 'dimensions', 'is_returnable', 'return_days',
                    'view_count', 'order_count',
                ]);
            });
        }

        // إزالة الحقول الإضافية من العملاء
        if (Schema::hasColumn('clients', 'avatar')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn([
                    'avatar', 'password', 'email_verified_at',
                    'remember_token', 'last_login_at', 'last_login_ip',
                ]);
            });
        }

        Schema::dropIfExists('brands');
    }
};
