{{-- Customer create/edit drawer. Parent x-data must provide `form` with id, name, phone, address, city, cnic, credit_limit, opening_balance, is_active. --}}
<x-drawer name="customer-form" title-text="form.id ? 'Edit customer' : 'New customer'" subtitle="Contact details and credit (udhaar) settings">
    <form method="POST" class="flex flex-1 flex-col overflow-hidden"
        :action="form.id ? @js(route('customers.update', '__ID__')).replace('__ID__', form.id) : @js(route('customers.store'))">
        @csrf
        <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
        <input type="hidden" name="_form" value="customer">
        <input type="hidden" name="_id" :value="form.id">
        <div class="scrollbar-thin flex-1 space-y-5 overflow-y-auto px-6 py-6">
            <x-input name="name" label="Full name / shop name" icon="user" x-model="form.name" required />
            <div class="grid grid-cols-2 gap-4">
                <x-input name="phone" label="Phone" icon="phone" placeholder="0300-1234567" x-model="form.phone" required />
                <x-input name="cnic" label="CNIC" placeholder="41303-1234567-1" x-model="form.cnic" />
            </div>
            <x-input name="address" label="Address" icon="map-pin" x-model="form.address" />
            <x-input name="city" label="City" x-model="form.city" />
            <div class="grid grid-cols-2 gap-4">
                <x-input name="credit_limit" type="number" step="0.01" min="0" label="Credit limit" x-model="form.credit_limit" help="0 = no limit. POS warns above it." />
                <div x-show="!form.id">
                    <x-input name="opening_balance" type="number" step="0.01" min="0" label="Opening balance" x-model="form.opening_balance" help="Old udhaar before this system." ::disabled="!!form.id" />
                </div>
            </div>
            <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-line p-4">
                <span class="text-sm font-medium text-heading">Active</span>
                <span class="relative inline-flex">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="peer sr-only" x-model="form.is_active">
                    <span class="h-6 w-11 rounded-full bg-line transition peer-checked:bg-primary"></span>
                    <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                </span>
            </label>
        </div>
        <div class="flex justify-end gap-2 border-t border-line bg-subtle/60 px-6 py-4">
            <x-button variant="outline" @click="$dispatch('close-drawer', 'customer-form')">Cancel</x-button>
            <x-button type="submit" icon="check" loading><span x-text="form.id ? 'Save changes' : 'Add customer'"></span></x-button>
        </div>
    </form>
</x-drawer>
