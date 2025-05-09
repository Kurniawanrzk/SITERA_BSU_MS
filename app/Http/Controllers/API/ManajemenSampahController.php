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
        $data_sampah_bsu = Sampah::where("bank_sampah_unit_id",$request->get("bsu_id"))->get();
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

    public function AISampahDetection(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:4096',
        ]);

        $imageData = base64_encode(file_get_contents($request->file('image')->getPathname()));
        $apiKey = "AIzaSyAfSq_2UzoRuu-oSbJuTDrzvyTeVf36yc0";

        $client = new Client();
        $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey", [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'contents' => [[
                    'parts' => [
                        ['text' => 'Klasifikasikan gambar ini sebagai jenis sampah: organik, anorganik, PET, kertas, logam, atau lainnya.'],
                        ['inlineData' => [
                            'mimeType' => 'image/jpeg',
                            'data' => $imageData
                        ]]
                    ]
                ]]
            ]
        ]);

        $body = json_decode($response->getBody(), true);
        $hasil = $body['candidates'][0]['content']['parts'][0]['text'] ?? 'Tidak terdeteksi';

        return response()->json([
            'jenis_sampah' => $hasil
        ]);
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
        $sampah_bsu_baru->tipe = strtolower($request->tipe);
        $sampah_bsu_baru->nama = strtolower($request->nama);
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
        foreach ($data as $index => $row) {
            if($index == 0) continue; // Skip header row
            $sampah_bsu_baru = new Sampah;
            $sampah_bsu_baru->bank_sampah_unit_id = $request->get("bsu_id");
            $sampah_bsu_baru->tipe = strtolower($row[0]); // Assuming the first column is 'tipe'
            $sampah_bsu_baru->nama = strtolower($row[1]); // Assuming the second column is 'nama'
            $sampah_bsu_baru->harga_satuan = $row[2]; // Assuming the third column is 'harga_per_unit'
            $sampah_bsu_baru->save();
        }

        return response()->json([
            "status" => true,
            "message" => "Sampah berhasil ditambahkan dari CSV"
        ], 200);
    }

    public function cekSemuaTransaksiBSU(Request $request)
    {
        $token = $request->get("token");
        $transaksi = Transaksi::with('detailTransaksi.sampah')->get();
    
        return response()->json([
            "status" => true,
            "data" => $transaksi
        ]);
    }
    

    public function transaksiSampahBSUNasabah(Request $request)
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
            
            $response = $this->client->request("GET", "http://145.79.10.111:8004/api/v1/nasabah/cek-nasabah/".$request->nik_nasabah, [
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
            $poinTemp = 0;
            $inventoryUpdates = [];

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
                    $berat = $item['berat'];
                    $total_sampah += $berat;
                    $subtotal = $berat * $sampah->harga_satuan;
                    $totalHarga += $subtotal;
                
                    // Hitung poin: 1 kg = 10 poin
                    $poin = floor($berat * 10); // bulatkan ke bawah jika desimal
                    $poinTemp += $poin;
                
                    DetailTransaksi::create([
                        'transaksi_id' => $transaksi->id,
                        'sampah_id' => $sampah->id,
                        'berat' => $berat,
                        'harga_satuan' => $sampah->harga_satuan,
                        'subtotal' => $subtotal,
                        'poin' => $poin,
                        'is_cocacola' => $request->is_cocacola
                    ]);
                    $inventoryUpdates[] = [
                        'id' => Str::uuid()->toString(),
                        'bank_sampah_unit_id' => $bsu->id,
                        'sampah_id' => $item['id'],
                        'berat_available' => DB::raw("COALESCE(berat_available, 0) + {$item['berat']}")
                    ];
                }

                \App\Models\Inventories::upsert(
                    $inventoryUpdates,
                    ['bank_sampah_unit_id', 'sampah_id'],
                    ['berat_available' => DB::raw('inventories.berat_available + VALUES(berat_available)')]
                );
                
    
                // Update total harga transaksi
                $transaksi->update(['total_harga' => $totalHarga]);
                $this->isiSaldoNasabah($nasabah['nik'], $totalHarga, $total_sampah,$request->get("token"));
                $poinNasabah = $this->tambahPoin($poinTemp, $token, $nasabah['nik']);


                if($this->getTotalKGSampahTransaksi($bsu->id) > 5001){ // silver
                    BankSampahUnit::where("id", $bsu->id)->update([
                        "reward_level" => "silver"
                    ]);
                } else if($this->getTotalKGSampahTransaksi($bsu->id) > 10001){ // gold
                    BankSampahUnit::where("id", $bsu->id)->update([
                        "reward_level" => "gold"
                    ]);
                } 

                BankSampahUnit::where("id", $bsu->id)->update([
                    "total_sampah" => $this->getTotalKGSampahTransaksi($bsu->id)
                ]);

                if($poinNasabah->total_poin >= 500) {
                    $this->ubahReward($nasabah['nik'], "gold", $token);
                } else if($poinNasabah->total_poin >= 200) {
                    $this->ubahReward($nasabah['nik'], "silver", $token);
                } else {
                    $this->ubahReward($nasabah['nik'], "bronze", $token);
                }
            
                DB::commit();
                return response()->json(['message' => 'Transaksi berhasil disimpan', 'transaksi_id' => $transaksi->id], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Transaksi gagal disimpan: ' . $e->getMessage()], 500);
            }
    }

    private function getTotalKGSampahTransaksi($bsu_id)
    {
        $total_berat = 0;
        $transaksi = Transaksi::join("detail_transaksi", "transaksi.id", "=", "detail_transaksi.transaksi_id")
        ->where("transaksi.bank_sampah_unit_id", "e78c7237-2d9a-427b-bdc1-c11e34369936")->get();
        foreach ($transaksi as $item) {
            $total_berat = $total_berat+ $item->berat;
        }
        return $total_berat;
    }

    private function ubahReward($nik, $reward_level, $token)
    {
        $client = new Client([
            'timeout' => 5, // Timeout 5 detik untuk menghindari request yang terlalu lama
        ]);

        $response = $client->request("PUT", "http://145.79.10.111:8004/api/v1/nasabah/ubah-reward-level", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $token,
            ],
            'json' => [
                "nik" => $nik,
                "reward_level" => $reward_level
            ]
        ]);
        $response = json_decode($response->getBody());
        if($response->status == true){
           return true;
        } else {
            return false;
        }

    }

    private function tambahPoin($poin, $token, $nik)
    {
        $client = new Client([
            'timeout' => 5, // Timeout 5 detik untuk menghindari request yang terlalu lama
        ]);

        $response = $client->request("PUT", "http://145.79.10.111:8004/api/v1/nasabah/isi-poin", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $token,
            ],
            'json' => [
                "nik" => $nik,
                "poin" => $poin
            ]
        ]);
        $response = json_decode($response->getBody());
        if($response->status == true){
            return $response->data;
        } else {
          return false;
    }
    }


    public function isiSaldoNasabah($nik, $saldo, $total_sampah,$token)
    {
        $client = new Client([
            'timeout' => 5, // Timeout 5 detik untuk menghindari request yang terlalu lama
        ]);

        $response = $client->request("PUT", "http://145.79.10.111:8004/api/v1/nasabah/tambah-saldo-total-sampah-nasabah", [
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

        $response = $this->client->request("GET", "http://145.79.10.111:8004/api/v1/nasabah/cek-nasabah/".$nik_nasabah, [
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
            "transaksi" => [
                "frekuensi" => $transaksi->count() > 10 ? "tinggi" :( $transaksi->count() > 5 ? "sedang" : "rendah"),
                "total_transaksi" => $transaksi->count(),
                "kontribusi_terakhir" => $transaksi->last() ? $transaksi->last()->waktu_transaksi : null,
                "total_saldo" => $transaksi->sum('total_harga'),
                "jenis_sampah" => $transaksi->pluck('detailTransaksi.sampah.nama')->unique(),
            ]
        ], 200);
    }
    public function cekTransaksiNasabahBsuId(Request $request)
    {
        // Mendapatkan BSU berdasarkan user_id
        $bsu = BankSampahUnit::where("user_id", $request->get("user_id"))->first();
        
        if (!$bsu) {
            return response()->json([
                "status" => false,
                "message" => "Bank Sampah Unit tidak ditemukan",
            ], 404);
        }
        
        // Mendapatkan parameter pagination
        $perPage = $request->get('per_page', 10); // Default 10 item per halaman
        $page = $request->get('page', 1); // Default halaman 1
        
        // Mengambil transaksi berdasarkan ID BSU dengan pagination
        $transaksiQuery = Transaksi::with('detailTransaksi.sampah')
            ->where("bank_sampah_unit_id", $bsu->id)
            ->has('detailTransaksi')
            ->latest('waktu_transaksi'); // Mengurutkan dari yang terbaru
        
        // Eksekusi query dengan pagination
        $transaksiPaginated = $transaksiQuery->paginate($perPage);
        
        // Inisialisasi Guzzle HTTP Client
        $client = new \GuzzleHttp\Client([
            'timeout' => 5, // Timeout 5 detik untuk menghindari request yang terlalu lama
        ]);
        
        try {
            // Mengambil data nasabah dari API eksternal
            $response = $client->request('GET', 'http://145.79.10.111:8004/api/v1/nasabah/cek-nasabah-bsu', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => $request->header('Authorization'),
                ],
                'query' => [
                    'bsu_id' => $bsu->id
                ]
            ]);
            
            $nasabahData = json_decode($response->getBody(), true);
            
            // Membuat mapping NIK ke data nasabah untuk mempermudah penggabungan data
            $nasabahMap = [];
            if ($nasabahData['status'] && isset($nasabahData['data'])) {
                foreach ($nasabahData['data'] as $nasabahItem) {
                    if (isset($nasabahItem['nik'])) {
                        $nasabahMap[$nasabahItem['nik']] = $nasabahItem;
                    }
                }
            }
            
            // Mendapatkan data transaksi dari pagination
            $transaksiItems = $transaksiPaginated->items();
            
            // Menggabungkan data transaksi dengan data nasabah
            $mergedData = collect($transaksiItems)->map(function ($item) use ($nasabahMap) {
                $data = $item->toArray();
                
                // Menambahkan data nasabah jika NIK cocok
                if (isset($data['nik']) && isset($nasabahMap[$data['nik']])) {
                    $data['nasabah_info'] = $nasabahMap[$data['nik']];
                } else {
                    $data['nasabah_info'] = null;
                }
                
                return $data;
            });
            
            // Hitung statistik
            
            // 1. Jumlah transaksi pada minggu ini
            $startOfWeek = now()->startOfWeek();
            $endOfWeek = now()->endOfWeek();
            
            $transaksiMingguIni = Transaksi::where("bank_sampah_unit_id", $bsu->id)
                ->whereBetween('waktu_transaksi', [$startOfWeek, $endOfWeek])
                ->count();
            
            // 2. Sampah terlaris berdasarkan total berat terbanyak
            $tipeSampahTerlaris = DB::table('detail_transaksi')
                ->join('transaksi', 'detail_transaksi.transaksi_id', '=', 'transaksi.id')
                ->join('sampah', 'detail_transaksi.sampah_id', '=', 'sampah.id')
                ->where('transaksi.bank_sampah_unit_id', $bsu->id)
                ->select('sampah.tipe', 'sampah.nama', DB::raw('SUM(detail_transaksi.berat) as total_berat'))
                ->groupBy('sampah.tipe', 'sampah.nama')
                ->orderBy('total_berat', 'desc')
                ->first();
            
            // 3. Total berat keseluruhan
            $totalBerat = DB::table('detail_transaksi')
                ->join('transaksi', 'detail_transaksi.transaksi_id', '=', 'transaksi.id')
                ->where('transaksi.bank_sampah_unit_id', $bsu->id)
                ->sum('detail_transaksi.berat');
            
            // 4. Total penjualan keseluruhan
            $totalPenjualan = Transaksi::where("bank_sampah_unit_id", $bsu->id)
                ->sum('total_harga');
            
            // Menyiapkan data statistik
            $statistik = [
                'transaksi_minggu_ini' => $transaksiMingguIni,
                'tipe_sampah_terlaris' => $tipeSampahTerlaris ? [
                    'tipe' => $tipeSampahTerlaris->tipe,
                    'nama' => $tipeSampahTerlaris->nama,
                    'total_berat' => $tipeSampahTerlaris->total_berat
                ] : null,
                'total_berat_keseluruhan' => $totalBerat,
                'total_penjualan_keseluruhan' => $totalPenjualan
            ];
            
            // Menyiapkan informasi pagination untuk response
            $paginationInfo = [
                'current_page' => $transaksiPaginated->currentPage(),
                'per_page' => $transaksiPaginated->perPage(),
                'total' => $transaksiPaginated->total(),
                'last_page' => $transaksiPaginated->lastPage(),
                'next_page_url' => $transaksiPaginated->nextPageUrl(),
                'prev_page_url' => $transaksiPaginated->previousPageUrl(),
                'from' => $transaksiPaginated->firstItem(),
                'to' => $transaksiPaginated->lastItem(),
            ];
            
            // Mengembalikan response dengan status, data gabungan, statistik, dan info pagination
            return response()->json([
                "status" => true,
                "data" => $mergedData,
                "statistik" => $statistik,
                "pagination" => $paginationInfo
            ], 200);
            
        } catch (\Exception $e) {
            // Handle error jika terjadi masalah saat mengambil data nasabah
            return response()->json([
                "status" => false,
                "message" => "Gagal mendapatkan data: " . $e->getMessage(),
                "data" => $transaksiPaginated->items()
            ], 500);
        }
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
            $response = $this->client->request("GET", "http://145.79.10.111:8004/api/v1/nasabah/cek-nasabah-bsu/", [
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

    public function getTipeSampah(Request $request)
    {
        $tipe_sampah = Sampah::where("bank_sampah_unit_id", $request->get("bsu_id"))
            ->select("tipe")
            ->distinct()
            ->get();
        if ($tipe_sampah->isEmpty()) {
            return response()->json([
                "status" => false,
                "message" => "Tidak ada tipe sampah yang ditemukan"
            ], 404);
        }
        return response()->json([
            "status" => true,
            "data" => $tipe_sampah
        ], 200);
    }

    public function getSampahByTipe($tipe)
    {
        $sampah = Sampah::where('tipe', $tipe)->get();
        if ($sampah->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'Tidak ada sampah dengan tipe tersebut'], 404);
        }

        return response()->json(['status' => true, 'data' => $sampah], 200);
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
