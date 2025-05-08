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
        Schema::create('bank_sampah_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('nomor_registrasi')->unique();
            $table->string('nama_bsu');
            $table->enum('kategori', ['bsu', 'bsi'])->nullable();
            $table->text('alamat')->nullable();
            $table->string('jalan_dusun')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('desa')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('longitude', 100)->nullable();
            $table->string('latitude', 100)->nullable();
            $table->date('tanggal_berdiri')->nullable();
            $table->string('nama_pengurus')->nullable();
            $table->integer('jumlah_nasabah')->default(0)->nullable();
            $table->string('nomor_telepon')->nullable();
            $table->string('gambar_bsu')->nullable();
            $table->enum('reward_level', ['bronze', 'silver', 'gold'])->default('bronze')->nullable();
            $table->decimal('total_sampah', 10, 2)->default(0)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_sampah_units');
    }
};
