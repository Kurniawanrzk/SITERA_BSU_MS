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
        Schema::create('detail_transaksi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid("transaksi_id");
            $table->foreign("transaksi_id")->references('id')->on('transaksi')->onDelete('cascade');
            $table->uuid("sampah_id");
            $table->foreign("sampah_id")->references('id')->on('sampah')->onDelete('cascade');
            $table->decimal("berat", 10, 2);
            $table->decimal("harga_satuan", 10, 2);
            $table->decimal("subtotal", 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_transaksi');
    }
};
