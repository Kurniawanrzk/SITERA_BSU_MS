<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Penjualan;
use Midtrans\Config;
use Midtrans\Snap;

class PaymentController extends Controller
{
public function getSnapToken($id)
{
    $penjualan = Penjualan::findOrFail($id);

    $params = [
        'transaction_details' => [
            'order_id' => $penjualan->id,
            'gross_amount' => $penjualan->total_harga
        ],
        'customer_details' => [
            'first_name' => 'Perusahaan',
            'email' => 'corporate@tosam.id',
            'phone' => '081234567890'
        ]
    ];

    try {
        $snapToken = Snap::getSnapToken($params);
        return response()->json(['snap_token' => $snapToken]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
}
