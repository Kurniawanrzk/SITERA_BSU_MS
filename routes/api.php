<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\
{
    BSUController,
    ManajemenSampahController,
    PengajuanController
};
// Route yang hanya BSU yang bisa buka
// Route yang bisa dibuka oleh perusahaan dan nasabah
Route::get("/v1/bsu/cek-user/{user_id}",[BSUController::class, "cekUser"]);
Route::get("/v1/bsu/cek-bsu", [BSUController::class, "cekSemuaBSU"]);
Route::get("/v1/bsu/cek-bsu/{id}", [BSUController::class, "cekBSUBerdasarkanNoRegis"]);
// Route yang hanya BSU yang bisa buka

Route::middleware('checkIfBsu')->group(function(){
    Route::put("/v1/bsu/edit/profile", [BSUController::class, "editProfileBsu"]);
    Route::get("/v1/bsu/cek-sampah", [ManajemenSampahController::class, "cekSampahBerdasarkanBSU"]);
    Route::post("/v1/bsu/tambah-sampah", [ManajemenSampahController::class, "tambahSampahBerdasarkanBSU"]);
    Route::post('/v1/bsu/tambah-sampah-csv', [ManajemenSampahController::class, 'tambahSampahDariCSV']);
    Route::get("/v1/bsu/profile", [BSUController::class, "cekProfileBsu"]);
    Route::get("/v1/bsu/cek-semua-transaksi-bsu", [ManajemenSampahController::class, "cekTransaksiNasabahBsuId"]);
    Route::post("/v1/bsu/transaksi-sampah", [ManajemenSampahController::class, "transaksiSampahBSUNasabah"]);
    Route::get("/v1/bsu/cek-semua-transaksi-nasabah/{nik_nasabah}", [ManajemenSampahController::class, "cekSemuaTransaksiNasabahBSUnik"]);
    Route::put('/v1/bsu/edit-sampah/{id}', [ManajemenSampahController::class, 'editSampah']);
    Route::delete('/v1/bsu/hapus-sampah/{id}', [ManajemenSampahController::class, 'deleteSampah']);
    Route::post("/v1/bsu/proses-ajuan-penarikan/{pengajuan_id}", [PengajuanController::class, "BSUMemprosesAjuan"]);
    Route::get("v1/bsu/cek-rekapitulasi-bsu", [BSUController::class, "cekRekapitulasi"]);
    Route::get("v1/bsu/cek-ajuan-bsu", [PengajuanController::class, "cekAjuanBSU"]);

});

Route::middleware("checkifnasabah")->group(function(){
    Route::get("/v1/bsu/cek-transaksi-nasabah/{nik_nasabah}", [ManajemenSampahController::class, 'cekTransaksiNasabah']);
    Route::post("/v1/bsu/ajukan-penarikan-nasabah/", [PengajuanController::class, "nasabahMengajukanPenarikan"]);
    Route::get("/v1/bsu/cek-ajukan-penarikan-nasabah/{nik}/{bsu_id}", [PengajuanController::class, "nasabahCekAjuan"]);
});