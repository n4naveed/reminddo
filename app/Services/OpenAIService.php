<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Contracts\AIServiceContract;

class OpenAIService implements AIServiceContract
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.key', env('OPENAI_API_KEY'));
    }

    public function generateDailyPlan(string $todoList, array $userPreferences = [])
    {
        if (empty($this->apiKey)) {
            Log::error('OpenAI API Key is missing.');
            return ['error' => 'API Key is missing. Please configure OPENAI_API_KEY in .env'];
        }

        $systemPrompt = "You are an expert scheduler for the Reminddo app. 
         Your goal is to organize a chaotic to-do list into a structured, visual day plan.
         
         Rules:
         1. Return ONLY valid JSON.
         2. Format: Returns an object: { \"message\": \"Short friendly text explaining what you did\", \"tasks\": [ ...array of specific task objects... ] }
            - Task object format: { id, title, start_time (ISO8601), end_time (ISO8601), icon (emoji), color (hex), description }
         3. Assume today is " . date('Y-m-d') . ".
         4. Schedule tasks between 08:00 and 19:00 unless specified.
         5. Keep titles punchy and short.
         6. Add gaps for breaks if the schedule is tight.
         7. IMPORTANT: Preserve the 'id' from the input in the output object if updating, or generate new IDs if creating.
         8. SPLITTING TASKS: If the input implies two distinct moments (e.g. 'Go to gym at 8am and return at 6pm'), create TWO separate tasks.
         9. If the user mentions 'Morning' or 'Evening', place the tasks in those approximate time windows.
         ";

        $userPrompt = "Here is my request: \n$todoList\n\n Please plan my day. Return JSON with 'message' and 'tasks'.";

        try {
            $response = Http::withToken($this->apiKey)
                ->withoutVerifying()
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => 'gpt-3.5-turbo', // Or gpt-4o-mini for speed/cost
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.7,
                ]);

            if ($response->failed()) {
                if ($response->status() === 429) {
                    Log::error('OpenAI Quota Exceeded');
                    return ['error' => 'OpenAI Quota Exceeded. Please check your billing at platform.openai.com.'];
                }
                Log::error('OpenAI API Error', ['body' => $response->body()]);
                return ['error' => 'AI Service unavailable: ' . $response->status()];
            }

            $content = $response->json('choices.0.message.content');

            // Attempt to strip markdown code fences if present
            $cleanContent = preg_replace('/^```json\s*|\s*```$/', '', trim($content));

            $tasks = json_decode($cleanContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('OpenAI JSON Parse Error', ['content' => $content]);
                return ['error' => 'Failed to parse AI response.'];
            }

            return $tasks;

        } catch (\Exception $e) {
            Log::error('OpenAI Exception', ['message' => $e->getMessage()]);
            return ['error' => 'Internal server error during AI generation.'];
        }
    }
}
