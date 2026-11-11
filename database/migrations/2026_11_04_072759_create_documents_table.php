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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->unsignedSmallInteger('anio')->index();
            $table->string('sentido'); // recibida | enviada (RF-01)
            $table->string('tipo_correspondencia'); // oficio, carta, memorando, solicitud, otro (RF-02)
            $table->date('fecha_documento');
            $table->date('fecha_recepcion')->nullable();
            $table->string('oficio_original')->nullable(); // independiente del folio (RF-10)
            $table->string('remitente')->nullable();
            $table->string('dependencia')->nullable();
            $table->string('cargo_remitente')->nullable();
            $table->string('asunto');
            $table->text('resumen')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('anexos')->nullable();
            $table->string('canal_recepcion')->nullable();
            $table->string('prioridad')->default('normal');
            $table->date('plazo')->nullable();
            $table->string('estado')->default('registrado');
            $table->foreignId('area_id')->nullable()->constrained();
            $table->json('datos_extra')->nullable(); // datos especializados por tipo (RF-05)
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->index(['estado', 'fecha_documento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
