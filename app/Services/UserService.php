<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function create(array $data, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($data, $avatar) {
            $user = User::create($this->attributes($data));

            if ($avatar) {
                $this->replaceAvatar($user, $avatar);
            }

            $user->syncRoles($data['role']);

            return $user;
        });
    }

    public function update(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($user, $data, $avatar) {
            $attributes = $this->attributes($data);

            $passwordChanged = ! empty($attributes['password']);
            if (! $passwordChanged) {
                unset($attributes['password']);
            }

            $user->update($attributes);

            if ($passwordChanged) {
                $this->endSessions($user);
            }

            if ($avatar) {
                $this->replaceAvatar($user, $avatar);
            } elseif (! empty($data['remove_avatar'])) {
                $this->removeAvatar($user);
            }

            if (isset($data['role']) && $user->role_name !== $data['role']) {
                $user->syncRoles($data['role']);
                activity('role_changed', "Role of \"{$user->name}\" changed to {$data['role']}", $user);
            }

            return $user;
        });
    }

    /** Sign the user out everywhere (database sessions), optionally keeping one session. */
    public function endSessions(User $user, ?string $exceptSessionId = null): void
    {
        DB::table('sessions')->where('user_id', $user->id)
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();
    }

    public function replaceAvatar(User $user, UploadedFile $file): void
    {
        $this->removeAvatar($user);
        $user->update(['avatar' => $file->store('avatars', 'public')]);
    }

    public function removeAvatar(User $user): void
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }
    }

    protected function attributes(array $data): array
    {
        $attributes = Arr::only($data, ['name', 'email', 'phone', 'password', 'is_active', 'branch_id']);

        // Owners are never tied to a branch.
        if (($data['role'] ?? null) === 'owner') {
            $attributes['branch_id'] = null;
        }

        return $attributes;
    }
}
