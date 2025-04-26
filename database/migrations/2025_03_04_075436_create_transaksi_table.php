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
        Schema::create('transaksi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string("nik", 20)->nullable();
            $table->uuid("bank_sampah_unit_id");
            $table->foreign("bank_sampah_unit_id")->references('id')->on('bank_sampah_units')->onDelete('cascade');
            $table->decimal("total_harga", 10, 2);
            $table->date("waktu_transaksi");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
