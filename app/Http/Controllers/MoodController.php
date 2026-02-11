<?php

namespace App\Http\Controllers;

use App\Models\Mood;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MoodController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'note' => 'nullable|string|max:255',
        ]);

        $request->user()->moods()->create($validated);

        return back();
    }
}
