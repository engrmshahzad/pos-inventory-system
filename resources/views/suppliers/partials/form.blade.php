<x-drawer name="supplier-form" title-text="form.id ? 'Edit supplier' : 'New supplier'" subtitle="Distributor / company details">
    <form method="POST" class="flex flex-1 flex-col overflow-hidden"
        :action="form.id ? @js(route('suppliers.update', '__ID__')).replace('__ID__', form.id) : @js(route('suppliers.store'))">
        @csrf
        <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
        <input type="hidden" name="_form" value="supplier">
        <input type="hidden" name="_id" :value="form.id">
        <div class="scrollbar-thin flex-1 space-y-5 overflow-y-auto px-6 py-6">
            <x-input name="name" label="Contact name" icon="user" x-model="form.name" required />
            <x-input name="company" label="Company" icon="factory" placeholder="e.g. Tapal Tea (Pvt) Ltd" x-model="form.company" />
            <div class="grid grid-cols-2 gap-4">
                <x-input name="phone" label="Phone" icon="phone" x-model="form.phone" />
                <x-input name="email" type="email" label="Email" x-model="form.email" />
            </div>
            <x-input name="address" label="Address" icon="map-pin" x-model="form.address" />
            <div class="grid grid-cols-2 gap-4">
                <x-input name="city" label="City" x-model="form.city" />
                <x-input name="ntn" label="NTN" placeholder="Optional" x-model="form.ntn" />
            </div>
            <div x-show="!form.id">
                <x-input name="opening_balance" type="number" step="0.01" min="0" label="Opening payable" x-model="form.opening_balance" help="Amount you already owe this supplier." ::disabled="!!form.id" />
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
            <x-button variant="outline" @click="$dispatch('close-drawer', 'supplier-form')">Cancel</x-button>
            <x-button type="submit" icon="check" loading><span x-text="form.id ? 'Save changes' : 'Add supplier'"></span></x-button>
        </div>
    </form>
</x-drawer>
