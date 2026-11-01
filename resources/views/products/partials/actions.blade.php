<x-dropdown width="w-48">
    <x-slot name="trigger">
        <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" aria-label="Actions for {{ $product->name }}"><x-icon name="more-horizontal" size="w-5 h-5" /></button>
    </x-slot>
    <x-slot name="content">
        <a href="{{ route('products.show', $product) }}" class="dropdown-item"><x-icon name="eye" size="w-4 h-4" /> View</a>
        @can('products.edit')
            <a href="{{ route('products.edit', $product) }}" class="dropdown-item"><x-icon name="pencil" size="w-4 h-4" /> Edit</a>
        @endcan
        <a href="{{ route('barcodes.index', ['ids' => [$product->id]]) }}" class="dropdown-item"><x-icon name="scan" size="w-4 h-4" /> Print barcode</a>
        @can('products.delete')
            <form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="Delete {{ $product->name }}?" data-confirm-text="Stock history is kept; the product is hidden from lists.">
                @csrf @method('DELETE')
                <button type="submit" class="dropdown-item text-danger hover:!text-danger"><x-icon name="trash" size="w-4 h-4" /> Delete</button>
            </form>
        @endcan
    </x-slot>
</x-dropdown>
