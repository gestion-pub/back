<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConducteurSlot extends Model
{
    use HasFactory;

    protected $fillable = ['conducteur_id', 'time_slot', 'campagne_id'];

    public function conducteur()
    {
        return $this->belongsTo(Conducteur::class);
    }

    public function campagne()
    {
        return $this->belongsTo(Campagne::class);
    }
}
