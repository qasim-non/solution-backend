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
        Schema::create('developers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('mobile');
            $table->foreignId('city_id')->nullable()->constrained('cities', 'id')->onDelete('set null');
            $table->boolean('is_ready')->default(false);
            $table->enum('experience_years', [
                'أقل من سنة',
                '1 - 2 سنوات',
                '3 - 5 سنوات',
                'أكثر من 5 سنوات'
            ]);
            $table->boolean('has_institutional_experience')->default(false);
            $table->unsignedInteger('completed_projects_count')->nullable();
            $table->enum('contract_type', ['full_time', 'freelance', 'both'])->default('both');
            $table->enum('weekly_hours', ['10_20', '20_30', 'full_time_40'])->default('20_30');
            $table->boolean('is_remote_ready')->default(true);
            $table->string('bio', 400)->nullable();
            $table->timestamps();
            $table->enum('status', ['pending', 'under_review', 'shortlisted', 'approved', 'rejected', 'suspended'])->default('pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('developers');
    }
};
