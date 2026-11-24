<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('account_settings', 'stamp')) {
                $table->string('stamp')->nullable()->after('attachments');
            }
            if (!Schema::hasColumn('account_settings', 'signature')) {
                $table->string('signature')->nullable()->after('stamp');
            }
            if (!Schema::hasColumn('account_settings', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('signature');
            }
            if (!Schema::hasColumn('account_settings', 'date_format')) {
                $table->string('date_format')->default('Y-m-d')->after('language');
            }
            if (!Schema::hasColumn('account_settings', 'time_format')) {
                $table->string('time_format')->default('12')->after('date_format');
            }
            if (!Schema::hasColumn('account_settings', 'dark_mode')) {
                $table->boolean('dark_mode')->default(false)->after('time_format');
            }
            if (!Schema::hasColumn('account_settings', 'notification_email')) {
                $table->boolean('notification_email')->default(true)->after('dark_mode');
            }
            if (!Schema::hasColumn('account_settings', 'notification_sms')) {
                $table->boolean('notification_sms')->default(false)->after('notification_email');
            }
            if (!Schema::hasColumn('account_settings', 'notification_browser')) {
                $table->boolean('notification_browser')->default(true)->after('notification_sms');
            }
            if (!Schema::hasColumn('account_settings', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('notification_browser');
            }
            if (!Schema::hasColumn('account_settings', 'two_factor_secret')) {
                $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');
            }
            if (!Schema::hasColumn('account_settings', 'backup_auto')) {
                $table->boolean('backup_auto')->default(false)->after('two_factor_secret');
            }
            if (!Schema::hasColumn('account_settings', 'backup_frequency')) {
                $table->string('backup_frequency')->nullable()->after('backup_auto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $cols = ['stamp','signature','cover_image','date_format','time_format','dark_mode','notification_email','notification_sms','notification_browser','two_factor_enabled','two_factor_secret','backup_auto','backup_frequency'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('account_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
