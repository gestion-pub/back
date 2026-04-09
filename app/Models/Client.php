<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'contact_name', 'email', 'campagne_nom', 'adresse', 'telephone', 'created_by'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
