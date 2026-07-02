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
        Schema::create('meal_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_template_id')->constrained()->cascadeOnDelete();
            $table->string('meal_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('calories')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_options');
    }
};
