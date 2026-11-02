<x-app-layout title="My Profile">
    <x-page-header title="My Profile" subtitle="Your personal details, photo and password." :breadcrumbs="['Profile' => null]" />

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Summary card --}}
        <div class="card overflow-hidden">
            <div class="hero-pattern h-24 bg-gradient-to-r from-primary-600 to-violet-600"></div>
            <div class="-mt-10 px-6 pb-6 text-center">
                <x-avatar :user="$user" size="xl" class="mx-auto !ring-4" />
                <h2 class="mt-3 text-lg font-semibold text-heading">{{ $user->name }}</h2>
                <p class="text-sm text-muted">{{ $user->email }}</p>
                <div class="mt-3 flex justify-center gap-2">
                    <x-badge color="primary" class="capitalize">{{ $user->role_name }}</x-badge>
                    <x-badge color="slate">{{ $user->branch?->name ?? 'All branches' }}</x-badge>
                </div>
                <dl class="mt-6 grid grid-cols-2 gap-3 text-left">
                    <div class="rounded-xl bg-subtle p-3">
                        <dt class="text-xs text-muted">Last login</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-heading">{{ fmtDate($user->last_login_at, true) ?? '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-subtle p-3">
                        <dt class="text-xs text-muted">Member since</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-heading">{{ $user->created_at->format('M Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="space-y-6 xl:col-span-2">
            <x-card title="Profile information" subtitle="Update your name, contact details and photo.">
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-5"
                    x-data="{ preview: @js($user->avatar_url), remove: false }">
                    @csrf @method('PATCH')
                    <input type="hidden" name="remove_avatar" :value="remove ? 1 : 0">

                    <div class="flex items-center gap-4">
                        <template x-if="preview"><img :src="preview" alt="" class="h-16 w-16 rounded-full object-cover ring-2 ring-line"></template>
                        <template x-if="!preview"><x-avatar :user="$user->replicate()->forceFill(['avatar' => null])" size="lg" /></template>
                        <div>
                            <div class="flex gap-2">
                                <x-button size="sm" variant="outline" icon="upload" @click="$refs.avatar.click()">Change photo</x-button>
                                <x-button size="sm" variant="ghost-danger" x-show="preview" @click="preview = null; remove = true; $refs.avatar.value = ''">Remove</x-button>
                            </div>
                            <p class="mt-1.5 text-xs text-muted">JPG, PNG or WEBP, max 2 MB.</p>
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden" x-ref="avatar"
                                @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); remove = false }">
                            @error('avatar')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input name="name" label="Full name" icon="user" :value="$user->name" required />
                        <x-input name="phone" label="Phone" icon="phone" :value="$user->phone" />
                    </div>
                    <x-input name="email" type="email" label="Email" icon="mail" :value="$user->email" required />

                    <div class="flex justify-end">
                        <x-button type="submit" icon="check" loading>Save profile</x-button>
                    </div>
                </form>
            </x-card>

            @if ($user->isOwner() || $user->isManager())
                <x-card title="Approval PIN" :subtitle="$user->pin ? 'A PIN is set. Enter a new one to change it.' : 'Cashiers need this PIN from you to cancel a sale.'">
                    <form method="POST" action="{{ route('profile.pin') }}" class="grid gap-5 sm:grid-cols-3">
                        @csrf @method('PUT')
                        <x-input name="current_password" type="password" label="Current password" icon="lock" error-bag="updatePin" required autocomplete="current-password" />
                        <x-input name="pin" type="password" inputmode="numeric" maxlength="6" label="New PIN (4–6 digits)" icon="key" error-bag="updatePin" required autocomplete="off" />
                        <x-input name="pin_confirmation" type="password" inputmode="numeric" maxlength="6" label="Confirm PIN" icon="key" error-bag="updatePin" required autocomplete="off" />
                        <div class="flex justify-end sm:col-span-3"><x-button type="submit" icon="key" loading>Save PIN</x-button></div>
                    </form>
                </x-card>
            @endif

            <x-card title="Change password" subtitle="At least 8 characters with letters and numbers{{ $user->isCashier() ? '' : ', upper and lower case' }}.">
                <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                    @csrf @method('PUT')
                    <x-input name="current_password" type="password" label="Current password" icon="lock" error-bag="updatePassword" required autocomplete="current-password" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input name="password" type="password" label="New password" icon="lock" error-bag="updatePassword" required autocomplete="new-password" />
                        <x-input name="password_confirmation" type="password" label="Confirm new password" icon="lock" error-bag="updatePassword" required autocomplete="new-password" />
                    </div>
                    <div class="flex justify-end">
                        <x-button type="submit" icon="key" loading>Update password</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
