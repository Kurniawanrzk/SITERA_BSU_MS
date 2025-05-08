<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{BankSampahUnit, DetailTransaksi, Transaksi, Sampah};
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class BSUController extends Controller
{
    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 5, // Timeout 5 detik untuk menghindari request yang terlalu lama
        ]);
    }
    public function register(Request $request)
    {
    }

    public function cekSemuaBSU(Request $request)
    {
        $limit = $request->get('limit', 10); // Ambil parameter limit dari request, default 10
        return response()->json([
            "status" => true,
            "data" => BankSampahUnit::limit($limit)->get() // Batasi jumlah data yang diambil
        ], 200);    
    }

    public function cekDataUtamaBSU(Request $request)
    {
        $client = new Client([
            'timeout' => 5, // Timeout 5 detik untuk menghindari request yang terlalu lama
        ]);

        $response = $client->request("GET", "http://145.79.10.111:8004/api/v1/nasabah/cek-nasabah-bsu", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $request->header('Authorization'),
            ],
        ]);
        $response = json_decode($response->getBody(), true)['data'];
        $total_nasabah = count($response);
        $total_berat_sampah = Transaksi::where("bank_sampah_unit_id", $request->get("bsu_id"))
            ->join("detail_transaksi", "transaksi.id", "=", "detail_transaksi.transaksi_id")
            ->where("bank_sampah_unit_id", $request->get("bsu_id"))
            ->sum("detail_transaksi.berat"); 
        $total_transaksi = Transaksi::where("bank_sampah_unit_id", $request->get("bsu_id"))->count();
        $total_pendapatan = Transaksi::where("bank_sampah_unit_id", $request->get("bsu_id"))->sum("total_harga");

        return response()->json([
            "status" => true,
            "data" => [
                "total_nasabah" => $total_nasabah,
                "total_sampah" => $total_berat_sampah,
                "total_transaksi" => $total_transaksi,
                "total_pendapatan" => $total_pendapatan
            ]
        ], 200);
    }

    public function distribusiJenisSampah(Request $request)
    {
        $bsuId = $request->get("bsu_id");
     
        $result = DB::select(
            "SELECT 
                s.tipe AS tipe_sampah,
                SUM(dt.berat) AS total_berat,
                ROUND((SUM(dt.berat) / (
                    SELECT SUM(dt2.berat) 
                    FROM detail_transaksi dt2
                    JOIN transaksi t2 ON dt2.transaksi_id = t2.id
                    JOIN sampah s2 ON dt2.sampah_id = s2.id
                    WHERE t2.bank_sampah_unit_id = :bsu_id_sub
                )) * 100, 2) AS persentase
            FROM 
                detail_transaksi dt
            JOIN 
                transaksi t ON dt.transaksi_id = t.id
            JOIN 
                sampah s ON dt.sampah_id = s.id
            WHERE 
                t.bank_sampah_unit_id = :bsu_id_main
            GROUP BY 
                s.tipe
            ORDER BY 
                total_berat DESC",
            [
                'bsu_id_main' => $bsuId,
                'bsu_id_sub' => $bsuId
            ]
        );
        

        return response()->json([
            "1" => $result,
        ]);

    }

    public function getTopContributors(Request $request, $bsuId)
    {
        // Mendapatkan awal dan akhir bulan sekarang
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        
        // Mengambil data transaksi untuk BSU tertentu pada bulan ini
        $topContributors = Transaksi::where('bank_sampah_unit_id', $bsuId)
            ->whereBetween('waktu_transaksi', [$startOfMonth, $endOfMonth])
            ->select('nik')
            ->selectRaw('SUM(total_harga) as total_kontribusi')
            ->selectRaw('COUNT(*) as jumlah_transaksi')
            ->selectRaw('SUM((SELECT SUM(berat) FROM detail_transaksi WHERE transaksi_id = transaksi.id)) as total_berat')
            ->groupBy('nik')
            ->orderBy('total_kontribusi', 'desc')
            ->take(10) // Ambil 10 kontributor terbaik
            ->get();
        
        if ($topContributors->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak ada data kontributor untuk bulan ini',
                'data' => []
            ]);
        }
        
        // Kumpulkan semua NIK untuk request ke service nasabah
        $nikList = $topContributors->pluck('nik')->toArray();
        
        // Panggil nasabah_ms API untuk mendapatkan detail nasabah
        try {
            $response = Http::get(env('NASABAH_SERVICE_URL') . '/api/nasabah/batch', [
                'nik_list' => implode(',', $nikList)
            ]);
            
            $nasabahData = $response->json()['data'] ?? [];
            
            // Gabungkan data transaksi dengan data nasabah
            $result = $topContributors->map(function ($contributor) use ($nasabahData) {
                $nasabah = collect($nasabahData)->firstWhere('nik', $contributor->nik);
                
                return [
                    'nik' => $contributor->nik,
                    'nama' => $nasabah['nama'] ?? 'Nama tidak tersedia',
                    'total_kontribusi' => $contributor->total_kontribusi,
                    'jumlah_transaksi' => $contributor->jumlah_transaksi,
                    'total_berat' => $contributor->total_berat,
                    'nomor_wa' => $nasabah['nomor_wa'] ?? 'Tidak tersedia',
                    'reward_level' => $nasabah['reward_level'] ?? 'Tidak tersedia'
                ];
            });
            
            return response()->json([
                'status' => 'success',
                'message' => 'Data kontributor terbaik bulan ini berhasil diambil',
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            // Jika gagal mengambil data nasabah, kembalikan hanya data transaksi
            return response()->json([
                'status' => 'partial_success',
                'message' => 'Data kontributor berhasil diambil tanpa detail nasabah',
                'error_detail' => $e->getMessage(),
                'data' => $topContributors
            ], 206);
        }
    }
    public function cekTrenPengumpulanSampah(Request $request)
    {
        $jenjang = $request->get("jenjang");
        $bsu_id = $request->get("bsu_id");
    
        $currentYear = Carbon::now()->year;
        
        if ($jenjang == "harian") {
            $startDate = Carbon::now()->startOfDay();
            $endDate = Carbon::now()->endOfDay();
            $group = "DATE(waktu_transaksi)";
            $format = "d M"; // Format: 01 Jan
        } else    if ($jenjang == "mingguan") {
            $startDate = Carbon::now()->startOfMonth()->startOfWeek(); // Awal minggu dari bulan ini
            $endDate = Carbon::now()->endOfWeek();
            $group = "YEARWEEK(waktu_transaksi, 1)";
            $format = "W"; // Format: Week number
        } elseif ($jenjang == "bulanan") {
            $startDate = Carbon::createFromDate($currentYear, 1, 1)->startOfDay(); // Awal tahun ini
            $endDate = Carbon::now()->endOfMonth(); // Akhir bulan saat ini
            $group = "MONTH(waktu_transaksi)";
            $format = "n"; // Just month number (1-12)
        }
        
        // Pendapatan
        $pendapatan = Transaksi::where("bank_sampah_unit_id", $bsu_id)
            ->whereBetween("waktu_transaksi", [$startDate, $endDate])
            ->select(
                DB::raw("$group as periode"),
                DB::raw("SUM(total_harga) as total_pendapatan")
            )
            ->groupBy("periode")
            ->orderBy("periode", "asc")
            ->get();
        
        // Berat
        $berat = Transaksi::where("bank_sampah_unit_id", $bsu_id)
            ->whereBetween("waktu_transaksi", [$startDate, $endDate])
            ->join("detail_transaksi", "transaksi.id", "=", "detail_transaksi.transaksi_id")
            ->select(
                DB::raw("$group as periode"),
                DB::raw("SUM(detail_transaksi.berat) as total_berat")
            )
            ->groupBy("periode")
            ->orderBy("periode", "asc")
            ->get();
        
        // Format data untuk response
        $trendData = [];
        
        if ($jenjang == "bulanan") {
            // Inisialisasi array dengan bulan dari awal tahun sampai bulan sekarang
            $namaBulan = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
            $bulanSekarang = (int)Carbon::now()->format('n'); // Mendapatkan bulan saat ini (1-12)
            
            // Inisialisasi data untuk bulan dari Januari sampai bulan saat ini
            for ($i = 1; $i <= $bulanSekarang; $i++) {
                $trendData[$i] = [
                    "bulan" => $namaBulan[$i-1],
                    "sampah" => 0,
                    "pendapatan" => 0
                ];
            }
            
            // Isi data pendapatan
            foreach ($pendapatan as $item) {
                $bulanIndex = (int)$item->periode;
                if (isset($trendData[$bulanIndex])) {
                    $trendData[$bulanIndex]["pendapatan"] = (int)$item->total_pendapatan;
                }
            }
            
            // Isi data berat sampah
            foreach ($berat as $item) {
                $bulanIndex = (int)$item->periode;
                if (isset($trendData[$bulanIndex])) {
                    $trendData[$bulanIndex]["sampah"] = (int)$item->total_berat;
                }
            }
            
            // Ubah dari associative array menjadi sequential array
            $trendData = array_values($trendData);
        } else if ($jenjang == "harian") {
            // Untuk jenjang harian, kita cukup tampilkan data hari ini saja
            $periodeData = [];
            
            foreach ($pendapatan as $item) {
                $periodeData[$item->periode]['pendapatan'] = (int)$item->total_pendapatan;
                if (!isset($periodeData[$item->periode]['sampah'])) {
                    $periodeData[$item->periode]['sampah'] = 0;
                }
            }
            
            foreach ($berat as $item) {
                $periodeData[$item->periode]['sampah'] = (int)$item->total_berat;
                if (!isset($periodeData[$item->periode]['pendapatan'])) {
                    $periodeData[$item->periode]['pendapatan'] = 0;
                }
            }
            
            // Format label periode sesuai jenjang
            foreach ($periodeData as $periode => $data) {
                $label = Carbon::createFromFormat('Y-m-d', $periode)->format('d M');
                
                $trendData[] = [
                    "bulan" => $label, // Tetap menggunakan key "bulan" untuk konsistensi 
                    "sampah" => $data['sampah'],
                    "pendapatan" => $data['pendapatan']
                ];
            }
        } else if ($jenjang == "mingguan") {
            // Untuk jenjang mingguan, tampilkan minggu-minggu dalam bulan ini
            $periodeData = [];
            
            foreach ($pendapatan as $item) {
                $periodeData[$item->periode]['pendapatan'] = (int)$item->total_pendapatan;
                if (!isset($periodeData[$item->periode]['sampah'])) {
                    $periodeData[$item->periode]['sampah'] = 0;
                }
            }
            
            foreach ($berat as $item) {
                $periodeData[$item->periode]['sampah'] = (int)$item->total_berat;
                if (!isset($periodeData[$item->periode]['pendapatan'])) {
                    $periodeData[$item->periode]['pendapatan'] = 0;
                }
            }
            
            // Format label periode sesuai jenjang
            foreach ($periodeData as $periode => $data) {
                $tahun = substr($periode, 0, 4);
                $minggu = substr($periode, 4);
                $label = "Minggu " . $minggu;
                
                $trendData[] = [
                    "bulan" => $label, // Tetap menggunakan key "bulan" untuk konsistensi
                    "sampah" => $data['sampah'],
                    "pendapatan" => $data['pendapatan']
                ];
            }
        }
    
        return response()->json([
            "status" => true,
            "data" => [
                "trendData" => $trendData
            ]
        ]);
    }
    

    public function cekBSUBerdasarkanNoRegis(Request $request, $id)
    {
        $bsu = BankSampahUnit::find($id);
        unset($bsu->jumlah_nasabah);
        if ($bsu) {
            return response()->json([
                "status" => true,
                "data" => $bsu 
            ], 200);
        } else {
            return response()->json([
                "status" => false,
                "message" => "BSU not found."
            ], 404);
        }
    }

    public function checkIfBsu(Request $request)
    {
        if($bsu = BankSampahUnit::where("user_id", $request->id)->exists())
        {
            return response()
            ->json([
                "status" => true,
                "bsu_profile" => $bsu
            ], 200);
        } else {
            return response()
            ->json([
                "status" => false,
                "bsu_profile" => false
            ], 400);            
        }
    }

    public function cekProfileBsu(Request $request)
    {
        return response()->json([
            "status" => true,
            "data" => [
                    "bsu" => BankSampahUnit::find($request->get("bsu_id")),
            ]
        ], 200);
    }

    public function editProfileBsu(Request $request)
    {
        $edit_bsu = BankSampahUnit::find($request->get("bsu_id"));
        $token = $request->get("token");
        $apiResponse = "";
        // Handle email and password updates if provided
        if($request->has('email') || $request->has('password')) {
            try {
                $userData = [];
                
                // Only include fields that are actually provided
                if($request->has('email')) {
                    $userData['email'] = $request->email;
                }
                
                if($request->has('password')) {
                    $userData['password'] = $request->password;
                }
                
                // Kirim permintaan ke API eksternal untuk update email dan password
                $response = $this->client->request("PUT", "http://145.79.10.111:8002/api/v1/auth/edit/profile", [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => $token,
                    ],
                    'json' => $userData
                ]);
        
                // Konversi response ke array
                $apiResponse = json_decode($response->getBody()->getContents(), true);
            } catch (\Exception $e) {
                return response()->json([
                    "status" => false,
                    "message" => "Gagal untuk mengupdate profil dibagian email dan password",
                    "error" => $e->getMessage()
                ], 500);
            }
        }
    
        // Pastikan $edit_bsu bukan null
        if (!isset($edit_bsu)) {
            return response()->json([
                "status" => false,
                "message" => "BSU user not found."
            ], 404);
        }
    
        // Handle gambar_bsu jika ada dalam request
        if ($request->hasFile('gambar_bsu')) {
            $file = $request->file('gambar_bsu');
            
            // Validasi jenis file
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                return response()->json([
                    "status" => false,
                    "message" => "Format gambar tidak didukung. Gunakan format JPG, JPEG, atau PNG."
                ], 400);
            }
            
            // Validasi ukuran file (misalnya maksimal 2MB)
            $maxSize = 2 * 1024 * 1024; // 2MB dalam bytes
            if ($file->getSize() > $maxSize) {
                return response()->json([
                    "status" => false,
                    "message" => "Ukuran gambar terlalu besar. Maksimal 2MB."
                ], 400);
            }
            
            // Generate nama file unik
            $fileName = time() . '_' . $edit_bsu->id . '.' . $file->getClientOriginalExtension();
            
            // Hapus gambar lama jika ada
            if ($edit_bsu->gambar_bsu && file_exists(public_path('uploads/bsu/' . $edit_bsu->gambar_bsu))) {
                unlink(public_path('uploads/bsu/' . $edit_bsu->gambar_bsu));
            }
            
            // Simpan gambar baru
            $file->move(public_path('uploads/bsu'), $fileName);
            
            // Update field gambar_bsu dengan nama file baru
            $edit_bsu->gambar_bsu = $fileName;
        }
    
        // Update field lain yang dikirim dalam request
        $updateableFields = [
            'kategori', 'alamat', 'jalan_dusun', 'rt', 'rw', 'desa', 
            'kecamatan', 'longitude', 'latitude', 'tanggal_berdiri', 
            'nama_pengurus', 'jumlah_nasabah', 'nomor_telepon', 
            'reward_level', 'total_sampah', "nama_bsu"
        ];
    
        foreach ($updateableFields as $field) {
            if ($request->has($field)) {
                $edit_bsu->$field = $request->$field;
            }
        }
    
        $edit_bsu->save(); // Simpan perubahan
    
        return response()->json([
            "status" => true,
            "message" => "profil berhasil di edit",
            "data" => [
                "user_bsu" => $edit_bsu,
            ]
        ], 200); 
    }


    public function cekUser($user_id)
    {
        $bsu = BankSampahUnit::where('user_id', $user_id);
        
        if ($bsu->exists()) {
            return response()->json([
                'status' => true,
                'data'  => [
                    'id' => $bsu->first()->id,
                    'nama' => $bsu->first()->nama,
                    // Add any other relevant BSU fields you want to return
                ]
            ]);
        }
        
        return response()->json([
            'status' => false,
            'message' => 'User tidak terdaftar sebagai BSU'
        ], 404);
    }

    public function cekRekapitulasi()
    {
        // Default to current week if no dates specified
        $startDate = request('start_date', Carbon::now()->startOfWeek());
        $endDate = request('end_date', Carbon::now()->endOfWeek());

        // Aggregate waste data by type
        $wasteRecapitulation = DetailTransaksi::join('sampah', 'detail_transaksi.sampah_id', '=', 'sampah.id')
            ->join('transaksi', 'detail_transaksi.transaksi_id', '=', 'transaksi.id')
            ->whereBetween('transaksi.waktu_transaksi', [$startDate, $endDate])
            ->select(
                'sampah.tipe', 
                DB::raw('SUM(detail_transaksi.berat) as total_berat')
            )
            ->groupBy('sampah.tipe')
            ->get();

        // Prepare data for pie chart
        $chartData = [
            'labels' => [],
            'datasets' => [
                'data' => [],
                'backgroundColor' => [
                    '#1F77B4', // Blue for Organic
                    '#FF7F0E', // Orange for Plastic
                    '#808080'  // Gray for Other
                ]
            ]
        ];

        // Map waste types to the chart data
        foreach ($wasteRecapitulation as $waste) {
            $chartData['labels'][] = $this->mapWasteType($waste->tipe);
            $chartData['datasets']['data'][] = $waste->total_berat;
        }

        return response()->json([
            'rekapitulasi' => $wasteRecapitulation,
            'chart_data' => $chartData,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }
    private function mapWasteType($type)
    {
        $typeMap = [
            'organik' => 'Sampah Organik',
            'plastik' => 'Sampah Plastik',
            'lainnya' => 'Sampah Lainnya'
        ];

        return $typeMap[strtolower($type)] ?? $type;
    }
    
}
