<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categorie extends Model
{
    use HasFactory;

    protected $fillable = ['nom_categorie'];

    public function campagne()
    {
        return $this->hasMany(Campagne::class, 'id_categorie');
    }
}
