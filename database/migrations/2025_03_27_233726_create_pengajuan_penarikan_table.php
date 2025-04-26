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
        Schema::create('pengajuan_penarikan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid("bank_sampah_unit_id");
            $table->foreign("bank_sampah_unit_id")->references('id')->on('bank_sampah_units')->onDelete('cascade');
            $table->string("nik");
            $table->decimal("total_penarikan", 10, 2)->default(0);
            $table->enum("status", ["proses", "berhasil", "gagal"])->default("proses");
            $table->text("keterangan")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_penarikan');
    }
};
