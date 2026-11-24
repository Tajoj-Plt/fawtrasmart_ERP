<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            if (!Schema::hasColumn('logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('icon');
            }
            if (!Schema::hasColumn('logs', 'browser')) {
                $table->string('browser')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('logs', 'os')) {
                $table->string('os')->nullable()->after('browser');
            }
            if (!Schema::hasColumn('logs', 'device')) {
                $table->string('device')->nullable()->after('os');
            }
            if (!Schema::hasColumn('logs', 'route')) {
                $table->string('route')->nullable()->after('device');
            }
            if (!Schema::hasColumn('logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('route');
            }
            if (!Schema::hasColumn('logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }
        });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $cols = ['ip_address','browser','os','device','route','old_values','new_values'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
