<x-app-layout title="Categories">
    @php
        $blank = ['id' => null, 'name' => '', 'parent_id' => '', 'icon' => 'package', 'color' => '#4F46E5', 'is_active' => true];
        $reopen = $errors->any() && old('_form') === 'category';
        $initial = $reopen ? array_merge($blank, \Illuminate\Support\Arr::only(old(), ['name', 'parent_id', 'icon', 'color']), ['id' => old('_id') ?: null, 'is_active' => (bool) old('is_active')]) : $blank;
        $swatches = ['#4F46E5', '#8B5CF6', '#EC4899', '#EF4444', '#F59E0B', '#10B981', '#14B8A6', '#0EA5E9', '#64748B'];
    @endphp

    <div x-data="{
            form: @js($initial), blank: @js($blank),
            create(parentId = '') { this.form = { ...this.blank, parent_id: String(parentId) }; $dispatch('open-modal', 'category-form') },
            edit(c) { this.form = { ...c, parent_id: c.parent_id ? String(c.parent_id) : '' }; $dispatch('open-modal', 'category-form') },
        }" x-init="@if ($reopen) $nextTick(() => $dispatch('open-modal', 'category-form')) @endif">

        <x-page-header title="Categories" subtitle="Group products for browsing, reports and the POS category pills." :breadcrumbs="['Inventory' => null, 'Categories' => null]">
            <x-button icon="plus" @click="create()">Add Category</x-button>
        </x-page-header>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Total categories" :value="$stats['total']" icon="folder" />
            <x-stat-card label="Top-level" :value="$stats['parents']" icon="layout-grid" color="info" />
            <x-stat-card label="Active" :value="$stats['active']" icon="check-circle" color="success" />
        </div>

        <x-table>
            <x-slot name="toolbar">
                <form method="GET" class="relative w-full lg:max-w-sm">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                    <input type="search" name="search" value="{{ request('search') }}" placeholder="Search categories…" class="form-control pl-10" aria-label="Search categories">
                </form>
            </x-slot>
            <x-slot name="head">
                <tr><th>Category</th><th class="text-right">Products</th><th>Status</th><th class="text-right">Actions</th></tr>
            </x-slot>

            @foreach ($roots as $root)
                @foreach (collect([$root])->concat($root->children) as $category)
                    @php
                        $isChild = $category->parent_id !== null;
                        $payload = $category->only(['id', 'name', 'parent_id', 'icon', 'color', 'is_active']);
                    @endphp
                    <tr>
                        <td>
                            <div @class(['flex items-center gap-3', 'pl-10' => $isChild])>
                                @if ($isChild)<span class="-ml-6 h-px w-4 bg-line"></span>@endif
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background: {{ $category->color }}1a; color: {{ $category->color }}">
                                    <x-icon :name="$category->icon ?: 'folder'" size="w-[18px] h-[18px]" />
                                </span>
                                <div>
                                    <p class="font-semibold text-heading">{{ $category->name }}</p>
                                    @unless ($isChild)<p class="text-xs text-muted">{{ $root->children->count() }} sub-categories</p>@endunless
                                </div>
                            </div>
                        </td>
                        <td class="text-right tabular font-semibold text-heading">{{ $category->products_count }}</td>
                        <td><x-badge :color="$category->is_active ? 'success' : 'slate'" dot>{{ $category->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        <td class="text-right">
                            <div class="flex justify-end">
                                <x-dropdown width="w-52">
                                    <x-slot name="trigger"><button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" aria-label="Actions for {{ $category->name }}"><x-icon name="more-horizontal" size="w-5 h-5" /></button></x-slot>
                                    <x-slot name="content">
                                        <button type="button" class="dropdown-item" @click="edit(@js($payload))"><x-icon name="pencil" size="w-4 h-4" /> Edit</button>
                                        @unless ($isChild)
                                            <button type="button" class="dropdown-item" @click="create({{ $category->id }})"><x-icon name="plus" size="w-4 h-4" /> Add sub-category</button>
                                        @endunless
                                        <a href="{{ route('products.index', ['category' => $category->id]) }}" class="dropdown-item"><x-icon name="package" size="w-4 h-4" /> View products</a>
                                        <form method="POST" action="{{ route('categories.destroy', $category) }}" data-confirm="Delete {{ $category->name }}?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger hover:!text-danger"><x-icon name="trash" size="w-4 h-4" /> Delete</button>
                                        </form>
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        </td>
                    </tr>
                @endforeach
            @endforeach

            @if ($roots->isEmpty())
                <x-slot name="empty">
                    <x-empty-state icon="folder" title="No categories found" description="Create categories like Grocery or Beverages.">
                        <x-button icon="plus" @click="create()">Add Category</x-button>
                    </x-empty-state>
                </x-slot>
            @endif
        </x-table>

        <x-modal name="category-form" title="Category" subtitle="Name, parent, icon and colour">
            <form id="category-form" method="POST" :action="form.id ? @js(route('categories.update', '__ID__')).replace('__ID__', form.id) : @js(route('categories.store'))" class="space-y-5">
                @csrf
                <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
                <input type="hidden" name="_form" value="category">
                <input type="hidden" name="_id" :value="form.id">

                <x-input name="name" label="Name" placeholder="e.g. Beverages" x-model="form.name" required />
                <x-select name="parent_id" label="Parent category" placeholder="None (top-level)" :options="$parents->all()" x-model="form.parent_id" help="Only one level of sub-categories is allowed." />

                <div>
                    <span class="form-label">Icon</span>
                    <div class="grid grid-cols-6 gap-2">
                        @foreach ($icons as $icon)
                            <label class="cursor-pointer">
                                <input type="radio" name="icon" value="{{ $icon }}" class="peer sr-only" x-model="form.icon">
                                <span class="flex h-10 items-center justify-center rounded-lg border border-line text-muted transition peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary peer-focus-visible:ring-[3px] peer-focus-visible:ring-primary/30 hover:text-heading">
                                    <x-icon :name="$icon" size="w-[18px] h-[18px]" />
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <span class="form-label">Colour</span>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($swatches as $swatch)
                            <button type="button" class="h-8 w-8 rounded-full ring-offset-2 ring-offset-card transition" style="background: {{ $swatch }}"
                                :class="form.color.toLowerCase() === '{{ strtolower($swatch) }}' && 'ring-2 ring-heading'" @click="form.color = '{{ $swatch }}'" aria-label="Colour {{ $swatch }}"></button>
                        @endforeach
                        <input type="color" x-model="form.color" class="h-8 w-10 cursor-pointer rounded border border-line bg-card" aria-label="Custom colour">
                        <input type="hidden" name="color" :value="form.color">
                    </div>
                    @error('color')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-center justify-between gap-4">
                    <span class="text-sm font-medium text-heading">Active</span>
                    <span class="relative inline-flex">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="peer sr-only" x-model="form.is_active">
                        <span class="h-6 w-11 rounded-full bg-line transition peer-checked:bg-primary"></span>
                        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                    </span>
                </label>
            </form>
            <x-slot name="footer">
                <x-button variant="outline" @click="$dispatch('close-modal', 'category-form')">Cancel</x-button>
                <x-button type="submit" form="category-form" icon="check" loading><span x-text="form.id ? 'Save changes' : 'Create category'"></span></x-button>
            </x-slot>
        </x-modal>
    </div>
</x-app-layout>
