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
       Schema::create('restaurants', function (Blueprint $table) {
    $table->id();
    $table->string('fsq_id')->unique(); // Foursquare place ID
    $table->string('name');
    $table->string('address')->nullable();
    $table->decimal('lat', 10, 7)->nullable();
    $table->decimal('lng', 10, 7)->nullable();
    $table->string('category')->nullable();
    $table->string('image_url')->nullable();
    $table->json('raw_data')->nullable(); // cache full Foursquare response
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
