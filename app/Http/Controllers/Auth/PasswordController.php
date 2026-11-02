<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', $request->user()->isCashier() ? Password::defaults() : Password::defaults()->mixedCase()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Keep this browser signed in, sign out every other device.
        app(\App\Services\UserService::class)->endSessions($request->user(), $request->session()->getId());
        activity('password_changed', "{$request->user()->name} changed their password", $request->user());

        return back()->with('success', 'Password updated successfully.');
    }
}
