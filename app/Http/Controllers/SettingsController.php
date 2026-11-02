<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Setting;
use App\Support\SettingsDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $values = [];
        foreach (SettingsDefinition::GROUPS as $items) {
            foreach ($items as $key => $default) {
                $values[$key] = settings($key, $default);
            }
        }

        return view('settings.edit', [
            'tab' => $request->input('tab', 'general'),
            's' => $values,
            'timezones' => ['Asia/Karachi', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Kolkata', 'Europe/London', 'UTC'],
            'dateFormats' => ['d M Y' => today()->format('d M Y'), 'd/m/Y' => today()->format('d/m/Y'), 'd-m-Y' => today()->format('d-m-Y'), 'Y-m-d' => today()->format('Y-m-d'), 'M d, Y' => today()->format('M d, Y')],
            'defaultCustomer' => ($id = $values['default_customer_id']) ? Customer::find($id) : null,
            'backups' => (new BackupController)->files(),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        abort_unless(array_key_exists($group, SettingsDefinition::GROUPS), 404);

        $data = $request->validate($this->rules($group), [
            'prefix_*.regex' => 'Prefixes may use 1–5 capital letters only.',
        ]);

        // Checkboxes that are unticked are not sent at all.
        foreach (['receipt_show_logo', 'allow_price_edit', 'allow_negative_stock', 'beep_on_scan', 'tax_enabled', 'low_stock_alerts'] as $flag) {
            if (array_key_exists($flag, SettingsDefinition::GROUPS[$group])) {
                $data[$flag] = $request->boolean($flag) ? '1' : '0';
            }
        }

        foreach (['business_logo' => 'branding', 'business_favicon' => 'branding'] as $field => $folder) {
            unset($data[$field]);
            if ($request->hasFile($field)) {
                $this->deleteFile(settings($field));
                $data[$field] = $request->file($field)->store($folder, 'public');
            } elseif ($request->boolean("remove_{$field}")) {
                $this->deleteFile(settings($field));
                $data[$field] = null;
            }
        }
        unset($data['remove_business_logo'], $data['remove_business_favicon']);

        foreach ($data as $key => $value) {
            Setting::set($key, is_string($value) && str_starts_with($key, 'prefix_') ? strtoupper($value) : $value, $group);
        }

        activity('settings_updated', 'Updated '.$group.' settings', null, ['keys' => array_keys($data)]);

        return redirect()->route('settings.index', ['tab' => $group])->with('success', ucfirst($group).' settings saved.');
    }

    protected function rules(string $group): array
    {
        return match ($group) {
            'general' => [
                'business_name' => ['required', 'string', 'max:100'],
                'business_phone' => ['nullable', 'string', 'max:50'],
                'business_email' => ['nullable', 'email', 'max:150'],
                'business_address' => ['nullable', 'string', 'max:255'],
                'currency_symbol' => ['required', 'string', 'max:5'],
                'number_format' => ['required', Rule::in(['international', 'indian'])],
                'date_format' => ['required', Rule::in(['d M Y', 'd/m/Y', 'd-m-Y', 'Y-m-d', 'M d, Y'])],
                'timezone' => ['required', 'timezone'],
                'business_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
                'business_favicon' => ['nullable', 'image', 'mimes:png,ico,svg', 'max:256'],
                'remove_business_logo' => ['nullable', 'boolean'],
                'remove_business_favicon' => ['nullable', 'boolean'],
            ],
            'pos' => [
                'receipt_size' => ['required', Rule::in(['58', '80'])],
                'receipt_header' => ['nullable', 'string', 'max:150'],
                'receipt_footer' => ['nullable', 'string', 'max:200'],
                'cashier_max_discount' => ['required', 'numeric', 'min:0', 'max:100'],
                'default_customer_id' => ['nullable', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            ],
            'tax' => [
                'tax_default_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'tax_mode' => ['required', Rule::in(['exclusive', 'inclusive'])],
            ],
            'alerts' => [
                'expiry_warning_days' => ['required', 'integer', 'min:1', 'max:365'],
                'adjustment_approval_threshold' => ['required', 'numeric', 'min:0', 'max:99999999'],
            ],
            'prefixes' => collect(SettingsDefinition::GROUPS['prefixes'])->mapWithKeys(fn ($v, $k) => [$k => ['required', 'regex:/^[A-Za-z]{1,5}$/']])->all(),
        };
    }

    protected function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
