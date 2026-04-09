<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Planning extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function campagne()
    {
        return $this->belongsTo(Campagne::class, 'id_campagne');
    }
    public function client()
    {
        return $this->belongsTo(Client::class, 'id_client'); // Check if this is correct, usually planning belongs to a campagne which belongs to a client
    }
}
