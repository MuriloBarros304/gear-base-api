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
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();

            // Relacionamento Um-para-Muitos com o mecânico (User)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->text('description');
            $table->string('vehicle_plate');
            $table->string('vehicle_model');
            $table->string('status')->default('pending');
            $table->decimal('labor_cost', 10, 2)->default(0);

            $table->dateTime('entry_date');
            $table->dateTime('delivery_date')->nullable(); // Pode estar nulo se não foi entregue

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
