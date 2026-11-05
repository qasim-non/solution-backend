<?php

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
        Schema::create('developer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->constrained('developers')->onDelete('cascade');
            $table->string('file_name'); // Original file name (e.g., Eng_Mohammed_CV.pdf)
            $table->string('file_path'); // Storage path on disk/S3
            $table->string('file_type'); // e.g., 'resume', 'portfolio', 'certificate'
            $table->string('mime_type')->nullable(); // e.g., application/pdf
            $table->unsignedBigInteger('file_size')->nullable(); // Size in bytes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('developer_documents');
    }
};
