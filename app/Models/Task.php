<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'title',
        'start_time',
        'end_time',
        'color',
        'icon',
        'is_completed',
        'user_id',
        'recurrence_pattern',
        'recurrence_id',
        'priority',
        'notes',
        'all_day',
        'time_of_day',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_completed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checklistItems()
    {
        return $this->hasMany(ChecklistItem::class);
    }
}
