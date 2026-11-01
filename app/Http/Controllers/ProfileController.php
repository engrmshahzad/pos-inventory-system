<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()->load('branch')]);
    }

    public function update(ProfileUpdateRequest $request, UserService $users): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email', 'phone']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->hasFile('avatar')) {
            $users->replaceAvatar($user, $request->file('avatar'));
        } elseif ($request->boolean('remove_avatar')) {
            $users->removeAvatar($user);
        }

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }
}
