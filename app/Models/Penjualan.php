<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

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
}