<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول الربط بلوحة تحكّم فوترة.
 *
 * **لماذا نخزّن اللقطة أصلاً؟** دليل الـAPI ينصّ صراحةً: «خزّن آخر لقطة
 * ناجحة. إن تعذّر الوصول إلى فوترة اعمل باللقطة المحفوظة ولا توقف نظام
 * العميل لعطل شبكة عندنا». فالتخزين شرطُ ألّا يتعطّل النظام بانقطاع شبكة.
 *
 * وصفٌّ واحد لا سجلّ تاريخي: النظام مشترِكٌ واحد لا مزوّد اشتراكات. والتاريخ
 * الذي يهمّ (الدفعات، التنبيهات) يأتي داخل اللقطة نفسها.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fawtra_subscription')) {
            Schema::create('fawtra_subscription', function (Blueprint $table) {
                $table->id();

                // ---- ما يُقرأ كثيراً: مستخرَجٌ من اللقطة إلى أعمدة ----
                //
                // التكرار مقصود: «هل الاشتراك نشط؟» سؤالٌ قد يتكرّر في كل طلب،
                // وفكّ JSON كامل لأجل قيمة منطقية واحدة ثمنٌ بلا مقابل
                $table->boolean('is_active')->default(false);
                $table->boolean('is_expired')->default(false);
                $table->boolean('is_trial')->default(false);
                $table->string('status', 40)->nullable()->comment('active | suspended | expired …');
                $table->date('end_date')->nullable();
                $table->integer('days_remaining')->nullable();
                $table->boolean('auto_renew')->default(false);
                $table->string('plan_name')->nullable();
                $table->string('activation_serial', 120)->nullable();

                // ---- اللقطة كاملة ----
                $table->json('snapshot')->nullable()->comment('account + subscription + plan + usage + billing');
                $table->string('sync_token', 64)->nullable()->comment('يُمرَّر في /sync فيوفّر النقل والمعالجة');

                // ---- حالة المزامنة ----
                $table->timestamp('last_synced_at')->nullable()->comment('آخر مزامنة ناجحة — عليها يُبنى «قِدَم اللقطة»');
                $table->timestamp('last_attempt_at')->nullable();
                $table->string('last_error_code', 60)->nullable();
                $table->text('last_error')->nullable();

                // ٨ إخفاقات متتالية تقفل المفتاح ١٥ دقيقة عند فوترة، فالعدّاد
                // يُستعمل للتراجع الأسّي قبل بلوغ ذلك الحدّ
                $table->unsignedInteger('consecutive_failures')->default(0);
                $table->timestamp('retry_after')->nullable();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fawtra_alerts')) {
            Schema::create('fawtra_alerts', function (Blueprint $table) {
                $table->id();

                // معرّف التنبيه عند فوترة — عليه يُبنى الإقرار ومنعُ التكرار
                $table->unsignedBigInteger('remote_id')->unique();

                // subscription.expiring | subscription.expired | usage.threshold …
                // التفرّع على `type` لا على `title`: النصّ قد يتحسّن والرمز ثابت
                $table->string('type', 60);
                $table->string('severity', 20)->default('warning')->comment('info | warning | critical');
                $table->string('title');
                $table->text('body')->nullable();
                $table->json('payload')->nullable();

                $table->timestamp('remote_created_at')->nullable();
                $table->timestamp('acknowledged_at')->nullable()->comment('أُقرّ به محلياً وأُبلغت فوترة');
                $table->timestamp('dismissed_at')->nullable()->comment('أخفاه المستخدم من الشاشة');

                $table->timestamps();

                $table->index(['type', 'acknowledged_at']);
                $table->index('severity');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fawtra_alerts');
        Schema::dropIfExists('fawtra_subscription');
    }
};
