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
        Schema::create('developers_technologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->constrained('developers', 'id')->onDelete('cascade');
            $table->string('technologie');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('developers_technologies');
    }
};
