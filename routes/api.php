<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\{
    BSUController,
    ManajemenSampahController,
    PengajuanController,
    PenjualanController,
    PaymentController
};

Route::prefix('v1/bsu')->group(function () {

    // Route umum (tanpa middleware)
    Route::get('/cek-user/{user_id}', [BSUController::class, "cekUser"]);
    Route::get('/cek-bsu', [BSUController::class, "cekSemuaBSU"]);
    Route::get('/cek-bsu/{id}', [BSUController::class, "cekBSUBerdasarkanNoRegis"]);

    // Hanya untuk BSU
    Route::middleware('checkIfBsu')->group(function () {
        Route::put('/edit/profile', [BSUController::class, "editProfileBsu"]);
        Route::get('/cek-sampah', [ManajemenSampahController::class, "cekSampahBerdasarkanBSU"]);
        Route::post('/tambah-sampah', [ManajemenSampahController::class, "tambahSampahBerdasarkanBSU"]);
        Route::post('/tambah-sampah-csv', [ManajemenSampahController::class, 'tambahSampahDariCSV']);
        Route::get('/profile', [BSUController::class, "cekProfileBsu"]);
        Route::get('/cek-semua-transaksi-bsu', [ManajemenSampahController::class, "cekTransaksiNasabahBsuId"]);
        Route::get('/cek-tipe-sampah', [ManajemenSampahController::class, "getTipeSampah"]);
        Route::get('/cek-sampah-berdasarkan-tipe/{tipe}', [ManajemenSampahController::class, "getSampahByTipe"]);
        Route::post('/transaksi-sampah', [ManajemenSampahController::class, "transaksiSampahBSUNasabah"]);
        Route::get('/cek-semua-transaksi-nasabah/{nik_nasabah}', [ManajemenSampahController::class, "cekSemuaTransaksiNasabahBSUnik"]);
        Route::put('/edit-sampah/{id}', [ManajemenSampahController::class, 'editSampah']);
        Route::delete('/hapus-sampah/{id}', [ManajemenSampahController::class, 'deleteSampah']);
        Route::post('/proses-ajuan-penarikan/{pengajuan_id}', [PengajuanController::class, "BSUMemprosesAjuan"]);
        Route::get('/cek-rekapitulasi-bsu', [BSUController::class, "cekRekapitulasi"]);
        Route::get('/cek-ajuan-bsu', [PengajuanController::class, "cekAjuanBSU"]);
        Route::get('/cek-data-utama-bsu', [BSUController::class, "cekDataUtamaBSU"]);
        Route::get('/cek-tren-pengumpulan-sampah', [BSUController::class, "cekTrenPengumpulanSampah"]);
        Route::get('/presentasi-sampah', [BSUController::class, "distribusiJenisSampah"]);
        Route::get('/cek-kontribusi-terbaik', [BSUController::class, "getTopContributors"]);
    });

    // Hanya untuk Nasabah
    Route::middleware("checkifnasabah")->group(function () {
        Route::get('/cek-transaksi-nasabah/{nik_nasabah}', [ManajemenSampahController::class, 'cekTransaksiNasabah']);
        Route::post('/ajukan-penarikan-nasabah', [PengajuanController::class, "nasabahMengajukanPenarikan"]);
        Route::get('/cek-ajukan-penarikan-nasabah/{nik}/{bsu_id}', [PengajuanController::class, "nasabahCekAjuan"]);
    });

    Route::middleware("checkifpemerintah")->group(function () {
        Route::get("/cek-semua-transaksi-bsu-pemerintah", [ManajemenSampahController::class, "cekSemuaTransaksiBSU"]); 
    });

    Route::middleware("checkifperusahaan")->group(function() {
        Route::post('/penjualan', [PenjualanController::class, 'store']);    
        Route::get('/penjualan/{id}/token', [PaymentController::class, 'getSnapToken']);
        Route::get("/penjualan/sampah", [PenjualanController::class, "getSampahDijual"]);
        Route::get("/penjualan/riwayat", [PenjualanController::class , "getByPerusahaan"]);
    });

    
});
