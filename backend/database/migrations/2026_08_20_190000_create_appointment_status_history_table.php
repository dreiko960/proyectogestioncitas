<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        
        Schema::create('historial_estados_cita', function (Blueprint $table) {
            $table->id();
            $table->uuid('appointment_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->uuid('by_user_id')->nullable();
            $table->timestampTz('at')->useCurrent();

            $table->foreign('appointment_id')->references('id')->on('citas')->cascadeOnDelete();
            $table->foreign('by_user_id')->references('id')->on('usuarios')->nullOnDelete();
            $table->index(['appointment_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_estados_cita');
    }
};
