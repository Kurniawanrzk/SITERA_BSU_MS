<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class DetailPenjualan extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'detail_penjualan';
    public $incrementing = false;
    protected $keyType = 'string'; 
    protected $fillable = [
        'penjualan_id',
        "sampah_id",
        "berat",
        "harga_satuan",
        "sub_total"
    ];

    public $timestamps = false;

}