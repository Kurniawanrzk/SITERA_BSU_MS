<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use App\Models\BankSampahUnit;
use App\Models\Sampah;
use App\Models\DetailPenjualan;


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
        "subtotal"
    ];

    public $timestamps = false;

    public function sampah()
{
    return $this->belongsTo(Sampah::class, 'sampah_id');
}

}