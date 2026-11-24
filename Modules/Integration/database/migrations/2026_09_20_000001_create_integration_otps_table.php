<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * رموز التحقق المرسَلة بالبريد لتطبيق الجوال — استعادة كلمة المرور
     * وتوثيق البريد الإلكتروني. الرمز يُخزَّن مُجزّأً (hash) لا نصاً صريحاً.
     */
    public function up(): void
    {
        Schema::create('integration_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // الغرض: password_reset أو email_verification
            $table->string('purpose', 50)->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_otps');
    }
};
