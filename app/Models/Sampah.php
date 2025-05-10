<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Sampah extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'sampah'; // Menentukan nama tabel
    public $incrementing = false;
    protected $keyType = 'string'; 
    protected $fillable = [
        'bank_sampah_unit_id', // ID unit bank sampah
        'tipe',                // Tipe sampah
        'nama',                // Nama sampah
        'harga_satuan'
    ];

    // Relasi dengan model BankSampahUnit (jika ada)
    public function bankSampahUnit()
    {
        return $this->belongsTo(App\Models\BankSampahUnit::class, 'bank_sampah_unit_id');
    }

 
}
