<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class BankSampahUnit extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'bank_sampah_units';
    public $incrementing = false;
    protected $keyType = 'string'; 
    protected $fillable = [
        'id',
        'user_id',
        'nomor_registrasi',
        'nama_bsu',
        'kategori',
        'alamat',
        'jalan_dusun',
        'rt',
        'rw',
        'desa',
        'kecamatan',
        'longitude',
        'latitude',
        'tanggal_berdiri',
        'nama_pengurus',
        'jumlah_nasabah',
        'nomor_telepon',
        'gambar_bsu',
        'reward_level',
        'total_sampah',
    ];

    protected $casts = [
        'tanggal_berdiri' => 'date',
        'id' => 'string'
    ];

    protected $hidden = [
        "nomor_registrasi",
        "user_id",
        "nama_pengurus",
        "created_at",
        "updated_at"
    ];

    public function transaksiNasabah()
    {
        return $this->belongsTo(App\Models\Transaksi::class, 'bank_sampah_unit_id');
    }

    // Menambahkan relasi dengan PengajuanPenarikan
    public function pengajuanPenarikan()
    {
        return $this->hasMany(PengajuanPenarikan::class, 'bank_sampah_unit_id');
    }

    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    // public function nasabah()
    // {
    //     return $this->hasMany(Nasabah::class, 'bsu_id');
    // }

    // public function sampah()
    // {
    //     return $this->hasMany(Sampah::class, 'bsu_id');
    // }

    // public function transaksiNasabah()
    // {
    //     return $this->hasMany(TransaksiNasabah::class, 'bsu_id');
    // }

    // public function transaksiPihakKetiga()
    // {
    //     return $this->hasMany(TransaksiPihakKetiga::class, 'bsu_id');
    // }
}
