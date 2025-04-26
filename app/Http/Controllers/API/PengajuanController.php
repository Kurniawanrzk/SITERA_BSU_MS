<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
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
        $bsu = BankSampahUnit::where("user_id", $request->get("user_id"))->first();
        return response()
        ->json([
            "status" => true,
            "data" => PengajuanPenarikan::where("bank_sampah_unit_id", $bsu->id)->get()
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
        if($request->status !== "gagal") {
            $response = $client->request("POST", "http://127.0.0.1:7000/api/v1/nasabah/ubah-saldo", [
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

        }
    }
}
