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

    /** Approval PIN (owner/manager) used to authorise a cashier's sensitive actions, e.g. cancelling a sale. */
    public function updatePin(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner() || $request->user()->isManager(), 403);

        $data = $request->validateWithBag('updatePin', [
            'current_password' => ['required', 'current_password'],
            'pin' => ['required', 'digits_between:4,6', 'confirmed', 'not_in:0000,1234,12345,123456,1111,000000'],
        ], ['pin.not_in' => 'Choose a less obvious PIN.']);

        $request->user()->forceFill(['pin' => $data['pin']])->save();
        activity('pin_changed', "{$request->user()->name} set an approval PIN", $request->user());

        return back()->with('success', 'Approval PIN saved.');
    }
}
