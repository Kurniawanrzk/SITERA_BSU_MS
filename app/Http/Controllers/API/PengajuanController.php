<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\RequestException;
use App\Models\{PengajuanPenarikan, BankSampahUnit};

class PengajuanController extends Controller
{
    public function nasabahMengajukanPenarikan(Request $request)
    {
        $user_id_nasabah = $request->get("user_id");
        
        $request->validate([
            "total_penarikan" => "required",
            "nik" => "required",
            "bsu_id" => "required"
        ]);
        
        
        $pengajuan = new PengajuanPenarikan;
        $pengajuan->bank_sampah_unit_id = $request->bsu_id;
        $pengajuan->nik = $request->nik;
        $pengajuan->total_penarikan = $request->total_penarikan;

        try {
            $pengajuan->save();
            return response()
                ->json([
                    "success" => true,
                    "message" => "Pengajuan Berhasil"
                ], 200);
        } catch (\Exception $e) {
            return response()
                ->json([
                    "success" => false,
                    "message" => "Pengajuan Gagal: " . $e->getMessage()
                ], 500);
        }
    }   

    public function nasabahCekAjuan(Request $request, $nik, $bsu_id)
    {

    
        return response()
        ->json([
            "status" => true,
            "data" => PengajuanPenarikan::where("nik", $nik)->where("bank_sampah_unit_id", $bsu_id)->get()
        ]);
    }
    
    public function cekAjuanBSU(Request $request)
    {
        // Dapatkan data BSU
        $bsu = BankSampahUnit::where("user_id", $request->get("user_id"))->first();
        
        // Dapatkan pengajuan penarikan
        $pengajuanData = PengajuanPenarikan::where("bank_sampah_unit_id", $bsu->id)->get();
        
        // Jika tidak ada pengajuan, kembalikan response kosong
        if ($pengajuanData->isEmpty()) {
            return response()->json([
                "status" => true,
                "data" => []
            ]);
        }
        
        // Siapkan array untuk hasil akhir
        $result = [];
        
        // Inisialisasi Guzzle Client
        $client = new \GuzzleHttp\Client([
            'timeout' => 5,
        ]);

        // Proses setiap pengajuan untuk mendapatkan data nasabah
        foreach ($pengajuanData as $pengajuan) {
            try {
                // Ambil data nasabah dari API external dengan token
                $response = $client->request('GET', 'http://145.79.10.111:8004/api/v1/nasabah/cek-nasabah-bsu', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $request->get("token"),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ]
                ]);
                
                // Parsing response
                $responseData = json_decode($response->getBody()->getContents(), true);
                
                // Cari nasabah dengan NIK yang sesuai
                $nasabahData = null;
                if (isset($responseData['status']) && $responseData['status'] && !empty($responseData['data'])) {
                    foreach ($responseData['data'] as $nasabah) {
                        if ($nasabah['nik'] == $pengajuan->nik) {
                            $nasabahData = $nasabah;
                            break;
                        }
                    }
                }
            } catch (RequestException $e) {
                // Handle error jika terjadi masalah saat pemanggilan API
                $nasabahData = null;
                // Opsional: log error
                \Log::error('Error fetching nasabah data: ' . $e->getMessage());
            }
            
            // Gabungkan data pengajuan dengan data nasabah
            $mergedData = [
                'pengajuan' => $pengajuan,
                'nasabah' => $nasabahData
            ];
            
            $result[] = $mergedData;
        }
        
        return response()->json([
            "status" => true,
            "data" => $result
        ]);
    }

    public function BSUMemprosesAjuan(Request $request,$pengajuan_id)
    {

        $request->validate([
            "status" => "required",
        ]);
        $pengajuan = PengajuanPenarikan::find($pengajuan_id);

        $client = new Client([
            "timeout" => 5,
        ]);
        if($request->status !== "tolak") {
            $response = $client->request("POST", "http://145.79.10.111:8004/api/v1/nasabah/ubah-saldo", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => $request->get("token"),
                ],
                "json" => [
                    "saldo" => $pengajuan->total_penarikan,
                    "nik" => $pengajuan->nik,
                ]
            ]);
    
            $response = json_decode($response->getBody());
            $pengajuan->update([
                "status" => "berhasil",
                "keterangan" => $request->keterangan ?? null
            ]);

            return response()
            ->json([
                "status" => true,
                "message" => "pengajuan berhasil"
            ]);
        } else {
            $pengajuan->update([
                "status" => "gagal",
                "keterangan" => $request->keterangan ?? null
            ]);

            return response()
            ->json([
                "status" => true,
                "message" => "pengajuan berhasil"
            ]);
        }
    }
}
