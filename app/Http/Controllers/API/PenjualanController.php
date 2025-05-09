<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{
    Penjualan,
    DetailPenjualan,
    Sampah,
    Inventories
    
};
class PenjualanController extends Controller
{
// PenjualanController.php
public function store(Request $request)
{
    


    $id_perusahaan = $request->get("perusahaan_id");


    // Cek stok
    foreach ($request->items as $item) {
        $inventory = Inventories::where('bank_sampah_unit_id', $item['bank_sampah_unit_id'])
            ->where('sampah_id', $item['sampah_id'])
            ->firstOrFail();

        if ($inventory->berat_available < $item['berat']) {
            return response()->json([
                'error' => 'Stok tidak cukup untuk sampah ID: ' . $item['sampah_id']
            ], 400);
        }
    }

    // Buat penjualan
    $penjualan = Penjualan::create([
        'id_perusahaan' =>  $id_perusahaan,
        'bank_sampah_unit_id' => $item['bank_sampah_unit_id'],
        'total_harga' => 0,
        'status' => 'Belum Dikelola'
    ]);
    // Hitung total
    $total = 0;
    foreach ($request->items as $item) {
        $sampah = Sampah::find($item['sampah_id']);
        $subtotal = $item['berat'] * $sampah->harga_satuan;
        
        DetailPenjualan::create([
            'penjualan_id' => $penjualan->id,
            'sampah_id' => $item['sampah_id'],
            'berat' => $item['berat'],
            'harga_satuan' => $sampah->harga_satuan,
            'subtotal' => $subtotal
        ]);
        
        $total += $subtotal;
    }

    $penjualan->update(['total_harga' => $total]);

    return response()->json([
        'order_id' => $penjualan->id,
        'total' => $total
    ]);
}

public function getByPerusahaan()
{
    $idPerusahaan = request()->get('perusahaan_id');
    try {
        $penjualan = Penjualan::with(['detailPenjualan.sampah', 'bankSampahUnit'])
            ->where('id_perusahaan', $idPerusahaan)
            ->orderBy('waktu_penjualan', 'desc') // Diubah ke waktu_penjualan
            ->get();

        $transformedData = $penjualan->map(function ($item) {
            return [
                'id_penjualan' => $item->id,
                'tanggal_penjualan' => $item->waktu_penjualan, // Diubah ke waktu_penjualan
                'total_harga' => $item->total_harga,
                'status' => $item->status,
                'bank_sampah' => [
                    'id' => $item->bankSampahUnit->id,
                    'nama' => $item->bankSampahUnit->nama_bsu,
                    'alamat' => $item->bankSampahUnit->alamat
                ],
                'items' => $item->detailPenjualan->map(function ($detail) {
                    return [
                        'id_sampah' => $detail->sampah->id,
                        'jenis' => $detail->sampah->tipe,
                        'nama' => $detail->sampah->nama,
                        'berat' => $detail->berat,
                        'harga_satuan' => $detail->harga_satuan,
                        'subtotal' => $detail->subtotal
                    ];
                })
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedData
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data penjualan: ' . $e->getMessage()
        ], 500);
    }
}

public function getSampahDijual()
{
    try {
        $inventories = Inventories::with(['sampah', 'bsu'])
            ->where('berat_available', '>', 0)
            ->get();

        $transformedData = $inventories->map(function ($inventory) {
            return [
                'id' => $inventory->sampah->id,
                'tipe_sampah' => $inventory->sampah->tipe,
                'nama_sampah' => $inventory->sampah->nama,
                'berat_tersedia' => $inventory->berat_available,
                'harga_satuan' => $inventory->sampah->harga_satuan,
                'satuan' => $inventory->sampah->satuan,
                'bank_sampah' => [
                    'id' => $inventory->bsu->id,
                    'nama' => $inventory->bsu->nama_bsu,
                    "nomor_telepon" => $inventory->bsu->nomor_telepon,
                    "no_registrasi" => $inventory->bsu->nomor_registrasi,
                    'alamat' => $inventory->bsu->alamat
                ],
                'gambar' => $inventory->sampah->gambar_url,
                'deskripsi' => $inventory->sampah->deskripsi,
                'tanggal_update' => $inventory->updated_at->format('Y-m-d H:i:s')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedData
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data sampah: ' . $e->getMessage()
        ], 500);
    }
}
}
