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
        Schema::create('request_social_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requests_id')->constrained('requests', 'id')->onDelete('cascade');
            $table->foreignId('platform_id')->constrained('social_media_platforms', 'id')->onDelete('cascade');
            $table->string('url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_social_media');
    }
};
