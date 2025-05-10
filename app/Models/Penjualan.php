<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use App\Models\BankSampahUnit;
use App\Models\Sampah;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Models\Inventories;

class Penjualan extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'penjualan';
    public $incrementing = false;
    protected $keyType = 'string'; 
    protected $fillable = [
        'id_perusahaan',
        "bank_sampah_unit_id",
        "total_harga",
        "waktu_pejualan",
        "status"
    ];

    public $timestamps = false;

    public function detailPenjualan()
    {
        return $this->hasMany(DetailPenjualan::class, 'penjualan_id');
    }

    public function bankSampahUnit()
    {
        return $this->belongsTo(BankSampahUnit::class, 'bank_sampah_unit_id');
    }

}