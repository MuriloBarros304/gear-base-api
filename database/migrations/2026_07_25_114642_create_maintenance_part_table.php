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
        Schema::create('maintenance_part', function (Blueprint $table) {
            $table->id();

            // Chaves estrangeiras ligando a Manutenção e a Peça
            $table->foreignId('maintenance_id')->constrained('maintenances')->onDelete('cascade');
            $table->foreignId('part_id')->constrained('parts')->onDelete('cascade');

            $table->integer('quantity')->default(1); // Quantidade daquela peça usada nesta manutenção

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_part');
    }
};
