<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained()->cascadeOnDelete();
            $table->string('tipo'); // turnado | reasignacion | seguimiento | resolucion | cancelacion (RF-14, RF-16)
            $table->foreignId('usuario_origen_id')->constrained('users');
            $table->foreignId('responsable_anterior_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamp('fecha');
            $table->timestamps();

            $table->index(['turno_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
