<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campagne extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'spots' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'id_client');
    }
    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'id_categorie');
    }
    public function plannings()
    {
        return $this->hasMany(Planning::class, 'id_campagne');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
