<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use App\Models\DetailTransaksi;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaksi extends Model
{
    use HasFactory, HasUuid;
    protected $table = 'transaksi';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nik',
        'total_harga',
        'bank_sampah_unit_id',
        'waktu_transaksi',
        'poin'
    ];

    /**
     * Relasi ke detail transaksi
     */
    public function detailTransaksi()
    {
        return $this->hasMany(DetailTransaksi::class, 'transaksi_id');
    }

    public function bsu()
    {
        return $this->hasOne(BankSampahUnit::class, "bank_sampah_unit_id");
    }

}
