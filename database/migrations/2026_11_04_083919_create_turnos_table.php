<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_destino_id')->constrained('areas');
            $table->string('tipo_asignacion'); // atencion | conocimiento (RF-19)
            $table->string('estado')->default('pendiente'); // pendiente | en_tramite | resuelto | cancelado (RF-24)
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('instrucciones')->nullable();
            $table->date('plazo')->nullable();
            $table->boolean('es_turnado_inicial')->default(false); // desde Oficialía vs interno (RF-12, RF-37)
            $table->foreignId('turno_padre_id')->nullable()->constrained('turnos')->nullOnDelete();
            $table->foreignId('turnado_por_id')->constrained('users');
            $table->foreignId('resuelto_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'estado']);
            $table->index(['area_destino_id', 'estado']);
            $table->index(['responsable_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
