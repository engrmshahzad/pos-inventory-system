<x-app-layout title="Brands">
    @php
        $blank = ['id' => null, 'name' => '', 'logo_url' => null];
        $reopen = $errors->any() && old('_form') === 'brand';
        $initial = $reopen ? array_merge($blank, ['name' => old('name'), 'id' => old('_id') ?: null]) : $blank;
    @endphp

    <div x-data="{
            form: @js($initial), blank: @js($blank), preview: null, removeLogo: false,
            create() { this.form = { ...this.blank }; this.preview = null; this.removeLogo = false; document.querySelector('input[type=file][name=logo]').value = ''; $dispatch('open-modal', 'brand-form') },
            edit(b) { this.form = { ...b }; this.preview = b.logo_url; this.removeLogo = false; document.querySelector('input[type=file][name=logo]').value = ''; $dispatch('open-modal', 'brand-form') },
        }" x-init="@if ($reopen) $nextTick(() => $dispatch('open-modal', 'brand-form')) @endif">

        <x-page-header title="Brands" subtitle="Manufacturers and labels of your products." :breadcrumbs="['Inventory' => null, 'Brands' => null]">
            <x-button icon="plus" @click="create()">Add Brand</x-button>
        </x-page-header>

        <form method="GET" class="card mb-6 p-4">
            <div class="relative max-w-sm">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search brands…" class="form-control pl-10" aria-label="Search brands">
            </div>
        </form>

        @if ($brands->isEmpty())
            <x-card><x-empty-state icon="award" title="No brands found" description="Add brands like Tapal, Nestlé or Shan."><x-button icon="plus" @click="create()">Add Brand</x-button></x-empty-state></x-card>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($brands as $brand)
                    @php $payload = ['id' => $brand->id, 'name' => $brand->name, 'logo_url' => $brand->logo_url]; @endphp
                    <div class="card card-hover flex items-center gap-4 p-4">
                        @if ($brand->logo_url)
                            <img src="{{ $brand->logo_url }}" alt="" class="h-14 w-14 shrink-0 rounded-xl border border-line bg-white object-contain p-1.5">
                        @else
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary/15 to-violet-500/15 text-lg font-bold text-primary dark:text-primary-300">{{ mb_strtoupper(mb_substr($brand->name, 0, 2)) }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-heading">{{ $brand->name }}</p>
                            <a href="{{ route('products.index', ['brand' => $brand->id]) }}" class="text-xs text-muted hover:text-primary">{{ $brand->products_count }} {{ \Illuminate\Support\Str::plural('product', $brand->products_count) }}</a>
                        </div>
                        <x-dropdown width="w-40">
                            <x-slot name="trigger"><button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" aria-label="Actions for {{ $brand->name }}"><x-icon name="more-horizontal" size="w-5 h-5" /></button></x-slot>
                            <x-slot name="content">
                                <button type="button" class="dropdown-item" @click="edit(@js($payload))"><x-icon name="pencil" size="w-4 h-4" /> Edit</button>
                                <form method="POST" action="{{ route('brands.destroy', $brand) }}" data-confirm="Delete {{ $brand->name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger hover:!text-danger"><x-icon name="trash" size="w-4 h-4" /> Delete</button>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                @endforeach
            </div>
        @endif

        <x-modal name="brand-form" title="Brand" maxWidth="md">
            <form id="brand-form" method="POST" enctype="multipart/form-data" class="space-y-5"
                :action="form.id ? @js(route('brands.update', '__ID__')).replace('__ID__', form.id) : @js(route('brands.store'))">
                @csrf
                <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
                <input type="hidden" name="_form" value="brand">
                <input type="hidden" name="_id" :value="form.id">
                <input type="hidden" name="remove_logo" :value="removeLogo ? 1 : 0">

                <x-input name="name" label="Brand name" placeholder="e.g. Tapal" x-model="form.name" required />
                <div class="flex items-center gap-4">
                    <template x-if="preview"><img :src="preview" alt="" class="h-16 w-16 rounded-xl border border-line bg-white object-contain p-1.5"></template>
                    <template x-if="!preview"><span class="flex h-16 w-16 items-center justify-center rounded-xl bg-primary/10 text-primary"><x-icon name="camera" size="w-6 h-6" /></span></template>
                    <div>
                        <div class="flex gap-2">
                            <x-button size="sm" variant="outline" icon="upload" @click="$refs.logo.click()">Upload logo</x-button>
                            <x-button size="sm" variant="ghost-danger" x-show="preview" @click="preview = null; removeLogo = true; $refs.logo.value = ''">Remove</x-button>
                        </div>
                        <p class="mt-1.5 text-xs text-muted">Optional. PNG, JPG or WEBP, max 1 MB.</p>
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="hidden" x-ref="logo" @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); removeLogo = false }">
                        @error('logo')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </form>
            <x-slot name="footer">
                <x-button variant="outline" @click="$dispatch('close-modal', 'brand-form')">Cancel</x-button>
                <x-button type="submit" form="brand-form" icon="check" loading><span x-text="form.id ? 'Save changes' : 'Create brand'"></span></x-button>
            </x-slot>
        </x-modal>
    </div>
</x-app-layout>
