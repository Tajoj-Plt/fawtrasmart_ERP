<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'reference_type')) {
                $table->string('reference_type')->nullable()->after('reference_number');
            }
            if (! Schema::hasColumn('journal_entries', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('reference_type');
            }
            if (! Schema::hasColumn('journal_entries', 'is_posted')) {
                $table->integer('is_posted')->default(0)->after('branch_id');
            }
            if (! Schema::hasColumn('journal_entries', 'linking_type')) {
                $table->enum('linking_type', ['single', 'multiple'])->default('single')->after('is_posted');
            }
            if (! Schema::hasColumn('journal_entries', 'batch_reference')) {
                $table->string('batch_reference')->nullable()->after('linking_type');
            }
            
            // Add foreign key constraint for branch
            if (! SchemaGuard::hasForeignKey('journal_entries', 'journal_entries_branch_id_foreign')) {
                $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['reference_type', 'branch_id', 'is_posted', 'linking_type', 'batch_reference']);
        });
    }
};