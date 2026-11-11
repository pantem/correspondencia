<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movimiento_id')->nullable()->constrained('movimientos')->nullOnDelete();
            $table->unsignedInteger('version'); // versión por turno (RF-36)
            $table->string('origen'); // recepcion_inicial | turnado_interno (RF-37)
            $table->string('path');
            $table->string('sha256', 64)->index();
            $table->foreignId('emitido_por')->constrained('users');
            $table->timestamp('emitido_at');
            $table->timestamps();

            $table->unique(['turno_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volantes');
    }
};
