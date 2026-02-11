<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Mood extends Model
{
    protected $fillable = ['user_id', 'rating', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
