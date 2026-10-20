<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parts', function(Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('oem');
            $table->string('brand');
            $table->enum('condition', ['new', 'used'])->default('new');
            $table->integer('quantity')->unsigned()->default(0);
            $table->decimal('price', 8, 2)->unsigned()->default(0);
            $table->string('image');
            $table->string('category');
            $table->json('colours')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
