<?php

namespace App\Services;

use App\Contracts\AIServiceContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService implements AIServiceContract
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
    }

    public function generateDailyPlan(string $todoList, array $userPreferences = [])
    {
        if (empty($this->apiKey)) {
            Log::error('Gemini API Key is missing.');
            return ['error' => 'API Key is missing. Please configure GEMINI_API_KEY in .env'];
        }

        $systemPrompt = "You are an expert scheduler for the Tiimo app. 
         Your goal is to organize a chaotic to-do list into a structured, visual day plan.
         
         Rules:
         1. Return ONLY valid JSON. Do not include markdown formatting like ```json ... ```.
         2. Format: Returns an object: { \"message\": \"Short friendly text explaining what you did\", \"tasks\": [ ...array of specific task objects... ] }
            - Task object format: { id, title, start_time (ISO8601), end_time (ISO8601), icon (emoji), color (hex), description }
         3. Assume today is " . date('Y-m-d') . ".
         4. Schedule tasks between 08:00 and 19:00 unless specified.
         5. Keep titles punchy and short.
         6. Add gaps for breaks if the schedule is tight.
         7. IMPORTANT: Preserve the 'id' from the input in the output object if updating, or generate new IDs if creating.
         8. SPLITTING TASKS: If the input implies two distinct moments, create TWO separate tasks.
         9. If the user mentions 'Morning' or 'Evening', place the tasks in those approximate time windows.
         ";

        $userPrompt = "Here is my request: \n$todoList\n\n Please plan my day. Return JSON with 'message' and 'tasks'.";

        $fullPrompt = $systemPrompt . "\n\n" . $userPrompt;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->withoutVerifying()
                ->post("{$this->baseUrl}?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $fullPrompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'responseMimeType' => 'application/json',
                    ]
                ]);

            if ($response->failed()) {
                Log::error('Gemini API Error', ['body' => $response->body()]);
                return ['error' => 'AI Service unavailable: ' . $response->status()];
            }

            $content = $response->json('candidates.0.content.parts.0.text');

            // Attempt to strip markdown code fences if present (just in case)
            $cleanContent = preg_replace('/^```json\s*|\s*```$/', '', trim($content));

            $tasks = json_decode($cleanContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Gemini JSON Parse Error', ['content' => $content]);
                return ['error' => 'Failed to parse AI response.'];
            }

            return $tasks;

        } catch (\Exception $e) {
            Log::error('Gemini Exception', ['message' => $e->getMessage()]);
            return ['error' => 'Internal server error during AI generation.'];
        }
    }
}
