<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_data', function (Blueprint $table) {
            if (!Schema::hasColumn('business_data', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('business_data', 'logo')) {
                $table->string('logo')->nullable()->after('business_name');
            }
            if (!Schema::hasColumn('business_data', 'stamp')) {
                $table->string('stamp')->nullable()->after('logo');
            }
            if (!Schema::hasColumn('business_data', 'signature')) {
                $table->string('signature')->nullable()->after('stamp');
            }
            if (!Schema::hasColumn('business_data', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('signature');
            }
            if (!Schema::hasColumn('business_data', 'tax_number')) {
                $table->string('tax_number')->nullable()->after('country');
            }
            if (!Schema::hasColumn('business_data', 'commercial_registration')) {
                $table->string('commercial_registration')->nullable()->after('tax_number');
            }
            if (!Schema::hasColumn('business_data', 'business_email')) {
                $table->string('business_email')->nullable()->after('commercial_registration');
            }
            if (!Schema::hasColumn('business_data', 'region')) {
                $table->string('region')->nullable()->after('city');
            }
            if (!Schema::hasColumn('business_data', 'website')) {
                $table->string('website')->nullable()->after('business_email');
            }
            if (!Schema::hasColumn('business_data', 'business_type')) {
                $table->enum('business_type', ['products', 'services', 'both'])->nullable()->after('website');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_data', function (Blueprint $table) {
            $cols = ['user_id','logo','stamp','signature','cover_image','tax_number','commercial_registration','business_email','region','website','business_type'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('business_data', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
