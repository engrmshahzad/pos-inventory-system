<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target ? $this->user()->can('update', $target) : $this->user()->can('create', User::class);
    }

    /**
     * A manager can only create/edit cashiers of their own branch, whatever the form sends.
     */
    protected function prepareForValidation(): void
    {
        $data = ['is_active' => $this->boolean('is_active')];

        if (! $this->user()->isOwner()) {
            $data['role'] = 'cashier';
            $data['branch_id'] = $this->user()->branch_id;
        }

        if ($this->input('role') === 'owner' && $this->user()->isOwner()) {
            $data['branch_id'] = null;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($target)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$target ? 'nullable' : 'required', 'confirmed',
                in_array($this->input('role'), ['owner', 'manager'], true) ? Password::defaults()->mixedCase() : Password::defaults()],
            'role' => ['required', Rule::in(User::ROLES)],
            'branch_id' => [
                Rule::requiredIf(fn () => in_array($this->input('role'), ['manager', 'cashier'], true)),
                'nullable',
                Rule::exists('branches', 'id')->whereNull('deleted_at'),
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $target = $this->route('user');

            // Owner must not lock themselves out.
            if ($target && $target->id === $this->user()->id) {
                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }
                if ($this->input('role') !== $target->role_name) {
                    $validator->errors()->add('role', 'You cannot change your own role.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return ['branch_id.required' => 'Managers and cashiers must belong to a branch.'];
    }
}
