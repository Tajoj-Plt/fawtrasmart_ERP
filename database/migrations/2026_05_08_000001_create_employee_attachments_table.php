<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_attachments')) {
            Schema::create('employee_attachments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('label')->nullable();
                $table->string('file_path');
                $table->string('original_name');
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')
                    ->references('id')->on('employees')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attachments');
    }
};
