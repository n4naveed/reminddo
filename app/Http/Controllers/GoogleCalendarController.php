<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Google\Client;
use Google\Service\Calendar;

class GoogleCalendarController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/calendar.events.readonly'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent']) // Offline access for refresh token
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $user = Auth::user();

            $user->update([
                'google_id' => $googleUser->id,
                'google_access_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'google_token_expires_at' => now()->addSeconds($googleUser->expiresIn),
            ]);

            return redirect()->route('dashboard')->with('flash.banner', 'Google Calendar connected successfully!');
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('flash.banner', 'Failed to connect Google Calendar: ' . $e->getMessage())->with('flash.bannerStyle', 'danger');
        }
    }

    public function getEvents()
    {
        $user = Auth::user();

        if (!$user->google_access_token) {
            return response()->json(['events' => []]);
        }

        // Check if token expired and refresh if needed
        if ($user->google_token_expires_at && $user->google_token_expires_at->isPast()) {
            if ($user->google_refresh_token) {
                $this->refreshToken($user);
            } else {
                // Token expired and no refresh token? Revoke access logic or ask to reconnect.
                // For now, return empty or error.
            }
        }

        $client = new Client();
        $client->setAccessToken($user->google_access_token);

        $service = new Calendar($client);
        $calendarId = 'primary';

        // Fetch today's events
        $optParams = [
            'maxResults' => 20,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => now()->startOfDay()->toRfc3339String(),
            'timeMax' => now()->endOfDay()->toRfc3339String(),
        ];

        try {
            $results = $service->events->listEvents($calendarId, $optParams);
            $events = [];

            foreach ($results->getItems() as $event) {
                $start = $event->start->dateTime ?? $event->start->date;
                $end = $event->end->dateTime ?? $event->end->date;

                $events[] = [
                    'id' => $event->id,
                    'title' => $event->summary,
                    'start' => $start,
                    'end' => $end,
                    'videoLink' => $event->hangoutLink,
                ];
            }

            return response()->json(['events' => $events]);

        } catch (\Exception $e) {
            // If error is 401 (invalid credentials), maybe token refresh failed or access revoked.
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function refreshToken(User $user)
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->refreshToken($user->google_refresh_token);

        $newAccessToken = $client->getAccessToken();

        $user->update([
            'google_access_token' => $newAccessToken['access_token'],
            'google_token_expires_at' => now()->addSeconds($newAccessToken['expires_in']),
        ]);

        // Note: Refresh token might not be returned again, keep old one unless new one provided (rare but possible).
        if (isset($newAccessToken['refresh_token'])) {
            $user->update(['google_refresh_token' => $newAccessToken['refresh_token']]);
        }
    }
}
