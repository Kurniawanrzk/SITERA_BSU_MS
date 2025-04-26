<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class PengajuanPenarikan extends Model
{
    use HasFactory,HasUuid;

    
    protected $table = 'pengajuan_penarikan';
    public $incrementing = false;
    protected $keyType = 'string'; 

    // Menambahkan properti fillable
    protected $fillable = [
        'bank_sampah_unit_id',
        'nik',
        'total_penarikan',
        'status',
        'keterangan',
    ];

    // Menambahkan relasi jika diperlukan
    public function bankSampahUnit()
    {
        return $this->belongsTo(BankSampahUnit::class, 'bank_sampah_unit_id');
    }
}
