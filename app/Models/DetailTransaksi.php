<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\{
    Transaksi,
    Sampah
};
class DetailTransaksi extends Model
{
    use HasFactory, HasUuid;
    protected $table = 'detail_transaksi';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'transaksi_id',
        'sampah_id',
        'berat',
        'harga_satuan',
        'subtotal',
    ];

    /**
     * Relasi ke transaksi
     */
    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    /**
     * Relasi ke sampah
     * Asumsikan model Sampah sudah ada
     */
    public function sampah()
    {
        return $this->belongsTo(Sampah::class, 'sampah_id');
    }
}