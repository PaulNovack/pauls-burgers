<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('items', function (Blueprint $table) {
            // IDs come from your catalog (not auto-increment)
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('tenant_id');
            $table->string('name');
            $table->enum('type', ['burger','side','drink']);
            $table->string('category')->nullable(); // 'food' | 'drink'
            $table->enum('size', ['Regular','Large'])->nullable(); // burgers = null
            $table->decimal('price', 8, 2);

            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('items');
    }
};
