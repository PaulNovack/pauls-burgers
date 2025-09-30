<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('item_topping', function (Blueprint $table) {
            $table->unsignedInteger('item_id');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');

            $table->unsignedBigInteger('topping_id');
            $table->foreign('topping_id')->references('id')->on('toppings')->onDelete('cascade');

            $table->primary(['item_id', 'topping_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('item_topping');
    }
};
