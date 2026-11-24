<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateGroupMergeLogsTable extends Command
{
    protected $signature = 'table:create-merge-logs';
    protected $description = 'Create group_merge_logs table';

    public function handle()
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS group_merge_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_group_id BIGINT UNSIGNED NOT NULL COMMENT 'المجموعة المضمومة',
                target_group_id BIGINT UNSIGNED NOT NULL COMMENT 'المجموعة المستهدفة',
                source_group_name VARCHAR(255) NOT NULL COMMENT 'اسم المجموعة المضمومة',
                target_group_name VARCHAR(255) NOT NULL COMMENT 'اسم المجموعة المستهدفة',
                moved_count INT NOT NULL COMMENT 'عدد العملاء المنقولين',
                clients_backup LONGTEXT NOT NULL COMMENT 'بيانات العملاء قبل النقل (JSON)',
                is_reversed TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'هل تم التراجع عن الضم',
                reversed_at TIMESTAMP NULL COMMENT 'وقت التراجع',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        ");

        $this->info('✅ تم إنشاء جدول group_merge_logs بنجاح');
    }
}
