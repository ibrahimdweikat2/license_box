<div>
    <a href="{{ route('admin.products') }}" wire:navigate class="text-sm link">&larr; Products</a>
    <x-admin.page-header :title="$product->name.' releases'" subtitle="Published releases are offered to licensed installations through check_update and download_update.">
        <button type="button" wire:click="create" class="btn-primary">New release</button>
    </x-admin.page-header>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>Version</th><th>Status</th><th>Released</th><th>Files</th><th>Changelog</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($releases as $i => $release)
                        <tr wire:key="release-{{ $release->id }}">
                            <td class="font-mono font-medium whitespace-nowrap">
                                {{ $release->version }}
                                @if ($i === 0 && $release->is_published)
                                    <x-admin.badge value="active">latest</x-admin.badge>
                                @endif
                            </td>
                            <td>
                                <button type="button" wire:click="togglePublished({{ $release->id }})" title="Toggle published">
                                    <x-admin.badge :value="$release->is_published ? 'published' : 'draft'" />
                                </button>
                            </td>
                            <td class="text-gray-500 whitespace-nowrap">{{ $release->release_date?->format('M j, Y') ?? '—' }}</td>
                            <td class="text-xs space-y-1">
                                <div>{{ $release->main_file_path ? '✓ main.zip' : '✗ no ZIP' }}</div>
                                @if ($release->sql_file_path)
                                    <div>✓ update.sql
                                        <button type="button" wire:click="removeSql({{ $release->id }})" wire:confirm="Remove the SQL file from {{ $release->version }}?" class="text-red-600 hover:underline ml-1">remove</button>
                                    </div>
                                @endif
                            </td>
                            <td class="text-gray-600 max-w-sm"><div class="line-clamp-2 whitespace-pre-line">{{ $release->changelog }}</div></td>
                            <td class="text-right whitespace-nowrap space-x-1">
                                <button type="button" wire:click="edit({{ $release->id }})" class="btn-secondary btn-sm">Edit</button>
                                <button type="button" wire:click="delete({{ $release->id }})" wire:confirm="Delete release {{ $release->version }} and its files?" class="btn-danger btn-sm">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty colspan="6">No releases yet. Upload a ZIP to publish your first version.</x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showForm)
        <x-admin.dialog :title="$editingId ? 'Edit release' : 'New release'">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field label="Version" name="version" hint="Semantic version, e.g. 1.4.0">
                        <input id="version" type="text" wire:model="version" class="input font-mono" placeholder="1.0.0" required>
                    </x-admin.field>
                    <x-admin.field label="Release date" name="release_date">
                        <input id="release_date" type="date" wire:model="release_date" class="input">
                    </x-admin.field>
                </div>
                <x-admin.field label="Changelog" name="changelog">
                    <textarea id="changelog" wire:model="changelog" rows="5" class="input" placeholder="- New feature&#10;- Bug fix"></textarea>
                </x-admin.field>
                <x-admin.field label="Update package (.zip)" name="main_file" :hint="$editingId ? 'Leave empty to keep the current file.' : 'Extracted over the installation root by the SDK.'">
                    <input id="main_file" type="file" wire:model="main_file" accept=".zip" class="block w-full text-sm">
                    <div wire:loading wire:target="main_file" class="mt-1 text-xs text-indigo-600">Uploading…</div>
                </x-admin.field>
                <x-admin.field label="Database migration (.sql, optional)" name="sql_file" hint="Executed by the SDK after the files are extracted.">
                    <input id="sql_file" type="file" wire:model="sql_file" accept=".sql" class="block w-full text-sm">
                    <div wire:loading wire:target="sql_file" class="mt-1 text-xs text-indigo-600">Uploading…</div>
                </x-admin.field>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="is_published" class="rounded border-gray-300 text-indigo-600">
                    Published (visible to customers)
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeForm" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,main_file,sql_file">Save release</button>
                </div>
            </form>
        </x-admin.dialog>
    @endif
</div>
