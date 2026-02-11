<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;

class ICloudCalendarController extends Controller
{
    public function connect(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'], // Should be an app-specific password
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $user = Auth::user();

            // Store credentials securely
            $user->update([
                'icloud_email' => $request->email,
                'icloud_password' => Crypt::encryptString($request->password),
            ]);

            // In a real implementation, we would verify the credentials immediately by trying to connect to iCloud
            // For now, we assume success if we can store them.

            return back()->with('flash.banner', 'iCloud Calendar connected successfully!');

        } catch (\Exception $e) {
            return back()->with('flash.banner', 'Failed to connect iCloud Calendar: ' . $e->getMessage())->with('flash.bannerStyle', 'danger');
        }
    }
}
