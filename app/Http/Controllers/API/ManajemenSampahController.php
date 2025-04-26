<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{
    Sampah,
    Transaksi,
    DetailTransaksi,
    BankSampahUnit
};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use GuzzleHttp\Client;

class ManajemenSampahController extends Controller
{
    protected $client;
    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 5, // Timeout 5 detik untuk menghindari request yang terlalu lama
        ]);
    }

    public function cekSampahBerdasarkanBSU(Request $request)
    {
        $data_sampah_bsu = Sampah::where("bank_sampah_unit_id",$request->get("bsu_user")->id)->first();
        if(isset($data_sampah_bsu)){
            return response()
            ->json([
                "status" => true,
                "data" => $data_sampah_bsu
            ], 200);
        } else {
            return response()
            ->json([
                "status" => true,
                "message" => "BSU ini belum memasukkan data sampah"
            ], 200);
        }
    }

    public function tambahSampahBerdasarkanBSU(Request $request)
    {
        $request->validate([
            'tipe' => 'required|string',
            'nama' => 'required|string',
            'harga_satuan' => 'required|numeric',
        ]);
        
        $sampah_bsu_baru = new Sampah;
        
        $sampah_bsu_baru->bank_sampah_unit_id = $request->get("bsu_id");
        $sampah_bsu_baru->tipe =  $request->tipe;
        $sampah_bsu_baru->nama = $request->nama;
        $sampah_bsu_baru->harga_satuan = $request->harga_satuan;
        $sampah_bsu_baru->save();

        if($sampah_bsu_baru)
        {
            return response()
            ->json([
                "status" => true,
                "message" => "sampah berhasil ditambahkan"
            ], 200);
        }
    }

    public function tambahSampahDariCSV(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt'
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        $data = array_map('str_getcsv', file($path));
        foreach ($data as $row) {
            $sampah_bsu_baru = new Sampah;
            $sampah_bsu_baru->bank_sampah_unit_id = $request->get("bsu_id");
            $sampah_bsu_baru->tipe = $row[0]; // Assuming the first column is 'tipe'
            $sampah_bsu_baru->nama = $row[1]; // Assuming the second column is 'nama'
            $sampah_bsu_baru->harga_satuan = $row[2]; // Assuming the third column is 'harga_per_unit'
            $sampah_bsu_baru->save();
        }

        return response()->json([
            "status" => true,
            "message" => "Sampah berhasil ditambahkan dari CSV"
        ], 200);
    }

    public function  transaksiSampahBSUNasabah(Request $request)
    {
            $token = $request->get("token");
            $bsu = BankSampahUnit::find($request->get("bsu_id"));
            // Validasi input
            $request->validate([
                'nik_nasabah' => 'required|string',
                'sampah' => 'required|array',
                'sampah.*.id' => 'required|uuid',
                'sampah.*.berat' => 'required|numeric',
            ]);
            
            $response = $this->client->request("GET", "http://127.0.0.1:7000/api/v1/nasabah/cek-nasabah/".$request->nik_nasabah, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => $token,
                ]
            ]);

    
            $nasabah = json_decode($response->getBody()->getContents(), true);
            if(!$nasabah['status']){
                return response()
                ->json([
                    "status" => false,
                    "message" => "Nasabah tidak ada atau ada kesalahan!"
                ], 400);
            }
            $totalHarga = 0;

            $nasabah = $nasabah['data']['user_nasabah'];
            $total_sampah = 0;

            DB::beginTransaction();
            try {
                // Simpan transaksi
                $transaksi = Transaksi::create([
                    'nik' => $nasabah['nik'],
                    'total_harga' => 0,
                    'bank_sampah_unit_id' => $bsu->id,
                    'waktu_transaksi' => now(),
                ]);
                // Simpan detail transaksi
                foreach ($request->sampah as $item) {
                    $sampah = Sampah::find($item['id']);
                    if (!$sampah) {
                        throw new \Exception("Sampah dengan ID {$item['id']} tidak ditemukan");
                    }
                    $total_sampah += $item['berat'];
                    $subtotal = $item['berat'] * $sampah->harga_satuan;
                    $totalHarga += $subtotal;
    
                    DetailTransaksi::create([
                        'transaksi_id' => $transaksi->id,
                        'sampah_id' => $sampah->id,
                        'berat' => $item['berat'],
                        'harga_satuan' => $sampah->harga_satuan,
                        'subtotal' => $subtotal,
                    ]);
                }
    
                // Update total harga transaksi
                $transaksi->update(['total_harga' => $totalHarga]);
                $this->isiSaldoNasabah($nasabah['nik'], $totalHarga, $total_sampah,$request->get("token"));
                DB::commit();
                return response()->json(['message' => 'Transaksi berhasil disimpan', 'transaksi_id' => $transaksi->id], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Transaksi gagal disimpan: ' . $e->getMessage()], 500);
            }
    }

    public function isiSaldoNasabah($nik, $saldo, $total_sampah,$token)
    {
        $client = new Client([
            'timeout' => 5, // Timeout 5 detik untuk menghindari request yang terlalu lama
        ]);

        $response = $client->request("PUT", "http://127.0.0.1:7000/api/v1/nasabah/tambah-saldo-total-sampah-nasabah", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $token,
            ],
            'json' => [
                "nik" => $nik,
                "saldo" => $saldo,
                "total_sampah" => $total_sampah
            ]
        ]);

        $response = json_decode($response->getBody());

        return $response;

    }

    

    // Untuk BSU
    public function cekSemuaTransaksiNasabahBSUnik(Request $request, $nik_nasabah)
    {
        $token = $request->get("token");
        $bsu = $request->get("bsu_user");

        $response = $this->client->request("GET", "http://127.0.0.1:7000/api/v1/nasabah/cek-nasabah/".$nik_nasabah, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $token,
            ]
        ]);
        $nasabah = json_decode($response->getBody()->getContents(), true);
        if(!$nasabah['status']){
            return response()
            ->json([
                "status" => false,
                "message" => "Nasabah tidak ada atau ada kesalahan!"
            ], 400);
        }
        $nasabah =  $nasabah['data']['user_nasabah'];

        // Fetch transactions for the specific nasabah with details
        $transaksi = Transaksi::with('detailTransaksi.sampah') // Load detailTransaksi and related sampah
            ->where('nik', $nasabah['nik'])
            ->has('detailTransaksi')
            ->get();

        // Create a structured response including nasabah and their transactions
        return response()->json([
            "status" => true,
            "nasabah" => $nasabah, // Include nasabah data
            "transaksi" => $transaksi // Include transactions
        ], 200);
    }
    public function cekTransaksiNasabahBsuId(Request $request)
    {
        $bsu = BankSampahUnit::where("user_id", $request->get("user_id"))->first();
        // Mengambil transaksi berdasarkan ID nasabah
        $transaksi = Transaksi::with('detailTransaksi.sampah') // Load detailTransaksi dan related sampah
            ->where("bank_sampah_unit_id", $bsu->id) // Menggunakan ID nasabah untuk pencarian
            ->has('detailTransaksi') // Memastikan transaksi memiliki detail
            ->get();
    
        // Mengembalikan response dengan status dan data transaksi
        return response()->json([
            "status" => true,
            "data" =>$transaksi // Menyertakan data transaksi
        ], 200);
    }
    public function cekTransaksiNasabah($nik_nasabah)
    {
        // Mengambil transaksi berdasarkan ID nasabah
        $transaksi = Transaksi::with('detailTransaksi.sampah') // Load detailTransaksi dan related sampah
            ->where('nik', $nik_nasabah) // Menggunakan ID nasabah untuk pencarian
            ->has('detailTransaksi') // Memastikan transaksi memiliki detail
            ->get();
    
        // Mengembalikan response dengan status dan data transaksi
        return response()->json([
            "status" => true,
            "data" =>$transaksi // Menyertakan data transaksi
        ], 200);
    }

    public function cekNasabahBSU(Request $request)
    {
        $token = $request->get("token");

        try {
            $response = $this->client->request("GET", "http://127.0.0.1:7000/api/v1/nasabah/cek-nasabah-bsu/", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => $token,
                ]
            ]);
            $nasabah = json_decode($response->getBody()->getContents(), true);

            return $nasabah;
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    public function editSampah(Request $request, $id)
    {
        $request->validate([
            'tipe' => 'string',
            'nama' => 'string',
            'harga_satuan' => 'numeric',
        ]);

        $sampah = Sampah::find($id);
        if (!$sampah) {
            return response()->json(['status' => false, 'message' => 'Sampah tidak ditemukan'], 404);
        }

        // Update only the fields that are provided
        if ($request->has('tipe')) {
            $sampah->tipe = $request->tipe;
        }
        if ($request->has('nama')) {
            $sampah->nama = $request->nama;
        }
        if ($request->has('harga_satuan')) {
            $sampah->harga_satuan = $request->harga_satuan;
        }
        $sampah->save();

        return response()->json(['status' => true, 'message' => 'Sampah berhasil diperbarui'], 200);
    }

    public function deleteSampah($id)
    {
        $sampah = Sampah::find($id);
        if (!$sampah) {
            return response()->json(['status' => false, 'message' => 'Sampah tidak ditemukan'], 404);
        }

        $sampah->delete();
        return response()->json(['status' => true, 'message' => 'Sampah berhasil dihapus'], 200);
    }
}
