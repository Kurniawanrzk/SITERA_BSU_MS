<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class Inventories extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'inventories';
    public $incrementing = false;
    protected $keyType = 'string'; 
    protected $fillable = [
        'bank_sampah_unit_id',
        "sampah_id",
        "berat_available",
        "updated_at",
        "created_at"
        
    ];

    public function sampah()
{
    return $this->belongsTo(App\Models\Sampah::class, 'sampah_id');
}

public function bsu()
{
    return $this->belongsTo(App\Models\BankSampahUnit::class, 'bank_sampah_unit_id');
}
}
