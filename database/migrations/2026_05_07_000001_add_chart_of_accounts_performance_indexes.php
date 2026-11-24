<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * تحسين أداء صفحة دليل الحسابات وعرض القيود.
     * يضيف فهارس على الأعمدة الأكثر استخداماً في الاستعلامات الجديدة:
     *  - accounts.parent_id (شجرة الحسابات + cascading lookups)
     *  - accounts (parent_id, branch_id) - شجرة مفلترة بالفرع
     *  - accounts (branch_id, code) - ترتيب أكواد ضمن الفرع
     *  - journal_entry_details.account_id - مفتاح الحساب
     *  - journal_entry_details (account_id, journal_entry_id) - الجدول الموحَّد
     *  - journal_entries (date, branch_id) - فلاتر القيود السريعة
     *  - journal_entries.reference_type - تصفية حسب نوع المرجع
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (!$this->indexExists('accounts', 'idx_accounts_parent_id')) {
                if (! SchemaGuard::hasIndex('accounts', 'idx_accounts_parent_id')) {
                    $table->index('parent_id', 'idx_accounts_parent_id');
                }
            }
            if (!$this->indexExists('accounts', 'idx_accounts_parent_branch')) {
                if (! SchemaGuard::hasIndex('accounts', 'idx_accounts_parent_branch')) {
                    $table->index(['parent_id', 'branch_id'], 'idx_accounts_parent_branch');
                }
            }
            if (!$this->indexExists('accounts', 'idx_accounts_branch_code')) {
                if (! SchemaGuard::hasIndex('accounts', 'idx_accounts_branch_code')) {
                    $table->index(['branch_id', 'code'], 'idx_accounts_branch_code');
                }
            }
            if (!$this->indexExists('accounts', 'idx_accounts_account_type')) {
                if (! SchemaGuard::hasIndex('accounts', 'idx_accounts_account_type')) {
                    $table->index('account_type', 'idx_accounts_account_type');
                }
            }
            if (!$this->indexExists('accounts', 'idx_accounts_is_active')) {
                if (! SchemaGuard::hasIndex('accounts', 'idx_accounts_is_active')) {
                    $table->index('is_active', 'idx_accounts_is_active');
                }
            }
        });

        Schema::table('journal_entry_details', function (Blueprint $table) {
            if (!$this->indexExists('journal_entry_details', 'idx_jed_account_id')) {
                if (! SchemaGuard::hasIndex('journal_entry_details', 'idx_jed_account_id')) {
                    $table->index('account_id', 'idx_jed_account_id');
                }
            }
            if (!$this->indexExists('journal_entry_details', 'idx_jed_account_entry')) {
                if (! SchemaGuard::hasIndex('journal_entry_details', 'idx_jed_account_entry')) {
                    $table->index(['account_id', 'journal_entry_id'], 'idx_jed_account_entry');
                }
            }
            if (!$this->indexExists('journal_entry_details', 'idx_jed_entry_account')) {
                if (! SchemaGuard::hasIndex('journal_entry_details', 'idx_jed_entry_account')) {
                    $table->index(['journal_entry_id', 'account_id'], 'idx_jed_entry_account');
                }
            }
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            if (!$this->indexExists('journal_entries', 'idx_je_date')) {
                if (! SchemaGuard::hasIndex('journal_entries', 'idx_je_date')) {
                    $table->index('date', 'idx_je_date');
                }
            }
            if (!$this->indexExists('journal_entries', 'idx_je_date_branch')) {
                if (! SchemaGuard::hasIndex('journal_entries', 'idx_je_date_branch')) {
                    $table->index(['date', 'branch_id'], 'idx_je_date_branch');
                }
            }
            if (!$this->indexExists('journal_entries', 'idx_je_reference_type')) {
                if (! SchemaGuard::hasIndex('journal_entries', 'idx_je_reference_type')) {
                    $table->index('reference_type', 'idx_je_reference_type');
                }
            }
            if (!$this->indexExists('journal_entries', 'idx_je_status_date')) {
                if (! SchemaGuard::hasIndex('journal_entries', 'idx_je_status_date')) {
                    $table->index(['status', 'date'], 'idx_je_status_date');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if ($this->indexExists('accounts', 'idx_accounts_parent_id')) $table->dropIndex('idx_accounts_parent_id');
            if ($this->indexExists('accounts', 'idx_accounts_parent_branch')) $table->dropIndex('idx_accounts_parent_branch');
            if ($this->indexExists('accounts', 'idx_accounts_branch_code')) $table->dropIndex('idx_accounts_branch_code');
            if ($this->indexExists('accounts', 'idx_accounts_account_type')) $table->dropIndex('idx_accounts_account_type');
            if ($this->indexExists('accounts', 'idx_accounts_is_active')) $table->dropIndex('idx_accounts_is_active');
        });

        Schema::table('journal_entry_details', function (Blueprint $table) {
            if ($this->indexExists('journal_entry_details', 'idx_jed_account_id')) $table->dropIndex('idx_jed_account_id');
            if ($this->indexExists('journal_entry_details', 'idx_jed_account_entry')) $table->dropIndex('idx_jed_account_entry');
            if ($this->indexExists('journal_entry_details', 'idx_jed_entry_account')) $table->dropIndex('idx_jed_entry_account');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            if ($this->indexExists('journal_entries', 'idx_je_date')) $table->dropIndex('idx_je_date');
            if ($this->indexExists('journal_entries', 'idx_je_date_branch')) $table->dropIndex('idx_je_date_branch');
            if ($this->indexExists('journal_entries', 'idx_je_reference_type')) $table->dropIndex('idx_je_reference_type');
            if ($this->indexExists('journal_entries', 'idx_je_status_date')) $table->dropIndex('idx_je_status_date');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $database = DB::getDatabaseName();
            $result = DB::select(
                'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                [$database, $table, $index]
            );
            return ($result[0]->c ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
