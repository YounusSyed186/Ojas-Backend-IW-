<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('specialization')->nullable()->after('role');
            $table->string('experience')->nullable()->after('specialization');
            $table->decimal('rating', 2, 1)->nullable()->after('experience');
            $table->text('bio')->nullable()->after('rating');
            $table->json('focus_areas')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'specialization',
                'experience',
                'rating',
                'bio',
                'focus_areas',
            ]);
        });
    }
};
