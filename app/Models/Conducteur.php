<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conducteur extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'date', 'status'];

    public function slots()
    {
        return $this->hasMany(ConducteurSlot::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'id_client');
    }

    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'id_categorie');
    }
}
