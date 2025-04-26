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
        Schema::create('inventaris_sampah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid("bank_sampah_unit_id");
            $table->foreign("bank_sampah_unit_id")->references('id')->on('bank_sampah_units')->onDelete('cascade');            
            $table->uuid("sampah_id");
            $table->foreign("sampah_id")->references('id')->on('sampah')->onDelete('cascade');            
            $table->decimal('berat_tersedia', 10, 2);
            $table->decimal('harga_perunit', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventaris_sampah');
    }
};
