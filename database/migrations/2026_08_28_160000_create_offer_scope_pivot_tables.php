<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الجداول الوسيطة لعروض الحسم.
 *
 * `App\Models\Offer` تعرّف ثلاث علاقات `belongsToMany` على الجداول
 * `offer_clients` و`offer_products` و`offer_categories`، ولم يكن لأيٍّ منها
 * هجرة. فأيّ لمسة للعلاقة — `withCount('clients')` في القائمة،
 * `with(['clients','products','categories'])` في العرض والتعديل،
 * و`sync()` في الحفظ، و`$offer->clients` في `Invoice::checkOfferConditions`
 * — تنكسر بـ «Base table or view not found: offer_clients».
 *
 * بلا طوابع زمنية: العلاقات معرّفة بلا `withTimestamps()`، فـ `sync()`
 * لا تكتب `created_at`، وعمودٌ إلزاميّ بلا قيمة يكسر الإدراج.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offer_clients')) {
            Schema::create('offer_clients', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('offer_id')->index('offer_clients_offer_id_index');
                $table->unsignedBigInteger('client_id')->index('offer_clients_client_id_index');

                // العميل الواحد لا يتكرّر في العرض الواحد
                $table->unique(['offer_id', 'client_id'], 'offer_clients_unique');

                $table->foreign('offer_id', 'offer_clients_offer_fk')
                    ->references('id')->on('offers')->onDelete('cascade');
                $table->foreign('client_id', 'offer_clients_client_fk')
                    ->references('id')->on('clients')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('offer_products')) {
            Schema::create('offer_products', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('offer_id')->index('offer_products_offer_id_index');
                $table->unsignedBigInteger('product_id')->index('offer_products_product_id_index');

                $table->unique(['offer_id', 'product_id'], 'offer_products_unique');

                $table->foreign('offer_id', 'offer_products_offer_fk')
                    ->references('id')->on('offers')->onDelete('cascade');
                $table->foreign('product_id', 'offer_products_product_fk')
                    ->references('id')->on('products')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('offer_categories')) {
            Schema::create('offer_categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('offer_id')->index('offer_categories_offer_id_index');
                $table->unsignedBigInteger('category_id')->index('offer_categories_category_id_index');

                $table->unique(['offer_id', 'category_id'], 'offer_categories_unique');

                $table->foreign('offer_id', 'offer_categories_offer_fk')
                    ->references('id')->on('offers')->onDelete('cascade');
                $table->foreign('category_id', 'offer_categories_category_fk')
                    ->references('id')->on('categories')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_categories');
        Schema::dropIfExists('offer_products');
        Schema::dropIfExists('offer_clients');
    }
};
