<div>
    <x-admin.page-header title="Products" subtitle="Each product has its own licenses, releases and Product ID used by the SDK.">
        <button type="button" wire:click="create" class="btn-primary">New product</button>
    </x-admin.page-header>

    <div class="card overflow-hidden">
        <div class="p-4 border-b">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search by name or Product ID…" class="input sm:max-w-xs">
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Product ID</th><th>Status</th><th>Licenses</th><th>Releases</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($products as $product)
                        <tr wire:key="product-{{ $product->id }}">
                            <td>
                                <div class="font-medium">{{ $product->name }}</div>
                                <div class="text-xs text-gray-500 truncate max-w-xs">{{ $product->description }}</div>
                            </td>
                            <td class="font-mono text-xs">{{ $product->product_uid }}</td>
                            <td><x-admin.badge :value="$product->status" /></td>
                            <td><a href="{{ route('admin.licenses', ['product' => $product->id]) }}" wire:navigate class="link">{{ $product->licenses_count }}</a></td>
                            <td><a href="{{ route('admin.products.versions', $product) }}" wire:navigate class="link">{{ $product->versions_count }}</a></td>
                            <td class="text-right whitespace-nowrap space-x-1">
                                <a href="{{ route('admin.products.versions', $product) }}" wire:navigate class="btn-secondary btn-sm">Releases</a>
                                <button type="button" wire:click="edit({{ $product->id }})" class="btn-secondary btn-sm">Edit</button>
                                <button type="button" wire:click="delete({{ $product->id }})"
                                        wire:confirm="Delete {{ $product->name }}? All of its licenses, activations and releases will be deleted."
                                        class="btn-danger btn-sm">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty colspan="6">No products yet. Create one to start issuing licenses.</x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="p-4 border-t">{{ $products->links() }}</div>
        @endif
    </div>

    @if ($showForm)
        <x-admin.dialog :title="$editingId ? 'Edit product' : 'New product'">
            <form wire:submit="save" class="space-y-4">
                <x-admin.field label="Name" name="name">
                    <input id="name" type="text" wire:model="name" class="input" required autofocus>
                </x-admin.field>
                <x-admin.field label="Product ID" name="product_uid" hint="Public identifier your app sends to the API. Letters and numbers only.">
                    <input id="product_uid" type="text" wire:model="product_uid" class="input font-mono uppercase" required>
                </x-admin.field>
                <x-admin.field label="Description" name="description">
                    <textarea id="description" wire:model="description" rows="3" class="input"></textarea>
                </x-admin.field>
                <x-admin.field label="Status" name="status" hint="Inactive products reject all API calls.">
                    <select id="status" wire:model="status" class="input">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </x-admin.field>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeForm" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-admin.dialog>
    @endif
</div>
