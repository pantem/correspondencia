<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            // Entrega física registrada sin acuse digital todavía (RF-47, RF-48).
            $table->boolean('acuse_pendiente')->default(false)->after('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn('acuse_pendiente');
        });
    }
};
