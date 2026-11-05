<?php

namespace App\Http\Requests\Role;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isOwner() && $this->user()->can('roles.manage');
    }

    protected function prepareForValidation(): void
    {
        $role = $this->route('role');

        // Built-in roles keep their name and type; only their permissions can change.
        if ($role?->isBuiltIn()) {
            $this->merge(['name' => $role->name, 'base_type' => $role->base_type]);
        } else {
            $this->merge(['name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('name')))]);
        }
        $this->merge(['permissions' => array_values((array) $this->input('permissions', []))]);
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9 \-]*$/',
                Rule::unique('roles', 'name')->ignore($role), Rule::notIn($role?->isBuiltIn() ? [] : Role::BUILT_IN)],
            'base_type' => ['required', Rule::in($role?->isBuiltIn() ? [$role->base_type] : array_keys(Role::BASE_TYPES))],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::assignable())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Use letters, numbers, spaces or dashes, starting with a letter.',
            'name.not_in' => 'This name is reserved for a built-in role.',
            'permissions.*.in' => 'One of the selected permissions cannot be given to this role.',
        ];
    }
}
