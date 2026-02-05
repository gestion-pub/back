<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'campagne_nom', 'adresse', 'telephone'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'create_by');
    }

    public function campagne()
    {
        return $this->hasMany(Campagne::class, 'id_client');
    }

    public function planning()
    {
        return $this->belongsTo(Planning::class, 'id_client');
    }
}
