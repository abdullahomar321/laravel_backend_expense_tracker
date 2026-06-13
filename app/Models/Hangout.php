<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hangout extends Model
{
    protected $fillable = [
        'name',
        'creator_id',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'hangout_members');
    }
}
