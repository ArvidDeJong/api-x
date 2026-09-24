<div class="mx-auto max-w-5xl space-y-6 p-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">Post to X</flux:heading>
            <flux:subheading>Every post that goes to X is listed below, also the ones from x:post and the MCP tool.</flux:subheading>
        </div>
        @if ($this->remainingToday !== null)
            <flux:badge color="zinc">{{ $this->remainingToday }} posts left today</flux:badge>
        @endif
    </div>

    <flux:card>
        <form wire:submit="post" class="space-y-4">
            <flux:field>
                <flux:label>Text</flux:label>
                <flux:textarea wire:model.live.debounce.300ms="text" rows="6" placeholder="What are you working on?" />
                <div class="flex justify-between text-sm">
                    <flux:text size="sm">{{ $allowLinks ? 'Links are allowed, and X bills them at a much higher rate.' : 'Links are refused; X bills them at a much higher rate.' }}</flux:text>
                    <span @class(['tabular-nums', 'text-red-600 dark:text-red-400 font-medium' => $this->length > $maxLength, 'text-zinc-500' => $this->length <= $maxLength])>
                        {{ $this->length }} / {{ $maxLength }}
                    </span>
                </div>
                <flux:error name="text" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Image upload</flux:label>
                    <flux:input type="file" wire:model="upload" accept="image/jpeg,image/png,image/gif,image/webp" />
                    <flux:description>JPEG, PNG, GIF or WebP, at most 5 MB.</flux:description>
                    <flux:error name="upload" />
                </flux:field>

                <flux:field>
                    <flux:label>Or image URL</flux:label>
                    <flux:input wire:model="imageUrl" placeholder="https://opengraph.githubassets.com/1/owner/repo/releases/tag/v1.0.0" />
                    <flux:description>Used when there is no upload.</flux:description>
                    <flux:error name="imageUrl" />
                </flux:field>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:checkbox wire:model.live="dryRun" label="Dry run: check everything, send nothing" />

                <flux:button type="submit" variant="primary" icon="paper-airplane">
                    <span wire:loading.remove wire:target="post">{{ $dryRun ? 'Check' : 'Post to X' }}</span>
                    <span wire:loading wire:target="post">Sending…</span>
                </flux:button>
            </div>

            @if ($result)
                <flux:callout :variant="$result['ok'] ? 'success' : 'danger'" :icon="$result['ok'] ? 'check-circle' : 'exclamation-triangle'">
                    <flux:callout.text>
                        {{ $result['message'] }}
                        @if ($result['url'])
                            <a href="{{ $result['url'] }}" target="_blank" rel="noopener" class="underline">Open the post</a>
                        @endif
                    </flux:callout.text>
                </flux:callout>
            @endif
        </form>
    </flux:card>

    <div class="space-y-3">
        <flux:heading size="lg">History</flux:heading>

        @if ($this->posts === null)
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>The x_posts table does not exist yet. Run php artisan migrate to keep a history.</flux:callout.text>
            </flux:callout>
        @else
            <flux:table :paginate="$this->posts">
                <flux:table.columns>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Text</flux:table.column>
                    <flux:table.column>Time</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->posts as $post)
                        <flux:table.row :key="'post-'.$post->id">
                            <flux:table.cell class="py-2">
                                <flux:badge size="sm" :color="$post->status === 'sent' ? 'green' : 'red'">{{ $post->status === 'sent' ? 'Sent' : 'Failed' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="max-w-md">
                                <div class="truncate">{{ $post->text }}</div>
                                @if ($post->error)
                                    <div class="truncate text-sm text-red-600 dark:text-red-400">{{ $post->error }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap text-sm text-zinc-500">{{ $post->created_at?->format('d-m-Y H:i') }}</flux:table.cell>
                            <flux:table.cell class="text-right">
                                @if ($post->url())
                                    <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$post->url()" target="_blank">View</flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-zinc-500">No posts yet.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
