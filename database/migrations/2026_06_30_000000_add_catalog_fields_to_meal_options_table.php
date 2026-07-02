<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_options', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
            $table->string('tag')->nullable()->after('slug');
            $table->string('category_slug')->nullable()->index()->after('meal_type');
            $table->decimal('price', 10, 2)->default(0)->after('calories');
            $table->unsignedSmallInteger('protein')->nullable()->after('price');
            $table->unsignedSmallInteger('carbs')->nullable()->after('protein');
            $table->unsignedSmallInteger('fat')->nullable()->after('carbs');
            $table->json('ingredients')->nullable()->after('fat');
            $table->unsignedInteger('sort_order')->default(0)->after('ingredients');
        });
    }

    public function down(): void
    {
        Schema::table('meal_options', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex(['category_slug']);
            $table->dropColumn([
                'slug',
                'tag',
                'category_slug',
                'price',
                'protein',
                'carbs',
                'fat',
                'ingredients',
                'sort_order',
            ]);
        });
    }
};
