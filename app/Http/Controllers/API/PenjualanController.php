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
    $request->validate([
        'bank_sampah_unit_id' => 'required',
        'items' => 'required|array',
        'items.*.sampah_id' => 'required',
        'items.*.berat' => 'required|numeric'
    ]);

    $id_perusahaan = $request->get("perusahaan_id");

    // Cek stok
    foreach ($request->items as $item) {
        $inventory = Inventories::where('bank_sampah_unit_id', $request->bank_sampah_unit_id)
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
        'bank_sampah_unit_id' => $request->bank_sampah_unit_id,
        'total_harga' => 0,
        'status' => 'pending'
    ]);

    // Hitung total
    $total = 0;
    foreach ($request->items as $item) {

        $sampah = Sampah::find($item['sampah_id']);
        return response()->json($sampah);
        $subtotal = $item['berat'] * $sampah->harga;
        
        DetailPenjualan::create([
            'penjualan_id' => $penjualan->id,
            'sampah_id' => $item['sampah_id'],
            'berat' => $item['berat'],
            'harga_satuan' => $sampah->harga,
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
}
