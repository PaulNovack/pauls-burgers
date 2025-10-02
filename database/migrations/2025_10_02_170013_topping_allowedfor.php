<?php

// database/migrations/2025_10_02_000001_add_fields_to_toppings_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('toppings', function (Blueprint $table) {
            // Array of categories this topping is allowed for: ["burger","side","drink"]
            if (!Schema::hasColumn('toppings','allowed_for')) {
                $table->json('allowed_for')->nullable()->after('name');
            }
            // Optional synonyms: ["cheddar","extra cheddar"]
            if (!Schema::hasColumn('toppings','synonyms')) {
                $table->json('synonyms')->nullable()->after('allowed_for');
            }
        });
    }
    public function down(): void {
        Schema::table('toppings', function (Blueprint $table) {
            if (Schema::hasColumn('toppings','synonyms'))    $table->dropColumn('synonyms');
            if (Schema::hasColumn('toppings','allowed_for')) $table->dropColumn('allowed_for');
        });
    }
};
