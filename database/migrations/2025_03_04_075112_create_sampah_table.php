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
        Schema::create('sampah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid("bank_sampah_unit_id");
            $table->foreign("bank_sampah_unit_id")->references('id')->on('bank_sampah_units')->onDelete('cascade');
            $table->string("tipe");
            $table->string("nama");
            $table->decimal("harga_satuan", 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sampah');
    }
};
