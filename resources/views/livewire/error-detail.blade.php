<div>
    @if($error)
        {{-- Breadcrumbs --}}
        <flux:breadcrumbs class="mb-4">
            <flux:breadcrumbs.item href="{{ route('api-guardian.livewire.dashboard') }}">
                Dashboard
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item href="{{ route('api-guardian.livewire.errors') }}">
                Errors
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ substr($error->id, 0, 8) }}...
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        {{-- Header --}}
        <div class="flex items-start justify-between mb-6">
            <div>
                <flux:heading>Error Details</flux:heading>
                <flux:subheading>{{ $error->exception_class }}</flux:subheading>
            </div>

            <div class="flex gap-2">
                @unless($error->resolved_at)
                    <flux:button 
                        wire:click="resolve"
                        color="green"
                        icon="check"
                    >
                        Mark Resolved
                    </flux:button>
                @endunless

                <flux:button 
                    wire:click="delete"
                    wire:confirm="Are you sure you want to delete this error? This cannot be undone."
                    color="red"
                    icon="trash"
                >
                    Delete
                </flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Content --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Error Summary --}}
                <flux:card>
                    <flux:heading size="sm">Summary</flux:heading>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-xs font-medium text-gray-500">Status</label>
                            <div class="mt-1">
                                @if($error->resolved_at)
                                    <flux:badge color="green">Resolved</flux:badge>
                                    <span class="text-sm text-gray-600 ml-2">
                                        {{ $error->resolved_at->diffForHumans() }}
                                    </span>
                                @else
                                    <flux:badge color="red">Open</flux:badge>
                                @endif
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-gray-500">Error ID</label>
                            <div class="mt-1 flex items-center gap-2">
                                <code class="text-sm bg-gray-100 px-2 py-1 rounded">
                                    {{ $error->error_id }}
                                </code>
                                <flux:button 
                                    wire:click="copyId"
                                    size="xs"
                                    variant="ghost"
                                    icon="clipboard"
                                >
                                    Copy
                                </flux:button>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-gray-500">Message</label>
                            <div class="mt-1 text-sm">{{ $error->message }}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium text-gray-500">Status Code</label>
                                <div class="mt-1">
                                    <flux:badge>{{ $error->status_code }}</flux:badge>
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-medium text-gray-500">Occurrences</label>
                                <div class="mt-1">
                                    <flux:badge color="blue">{{ $error->occurrence_count }}</flux:badge>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium text-gray-500">First Seen</label>
                                <div class="mt-1 text-sm" title="{{ $error->created_at }}">
                                    {{ $error->created_at->diffForHumans() }}
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-medium text-gray-500">Last Seen</label>
                                <div class="mt-1 text-sm" title="{{ $error->updated_at }}">
                                    {{ $error->updated_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </flux:card>

                {{-- Request Details --}}
                <flux:card>
                    <flux:heading size="sm">Request Details</flux:heading>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-xs font-medium text-gray-500">Endpoint</label>
                            <div class="mt-1 flex items-center gap-2">
                                <flux:badge size="sm">{{ $error->request_method }}</flux:badge>
                                <code class="text-sm">{{ $error->request_url }}</code>
                            </div>
                        </div>

                        @if($error->ip_address)
                            <div>
                                <label class="text-xs font-medium text-gray-500">IP Address</label>
                                <div class="mt-1 text-sm">{{ $error->ip_address }}</div>
                            </div>
                        @endif

                        @if($error->user_agent)
                            <div>
                                <label class="text-xs font-medium text-gray-500">User Agent</label>
                                <div class="mt-1 text-sm text-gray-600 break-all">
                                    {{ $error->user_agent }}
                                </div>
                            </div>
                        @endif

                        @if($error->user_id)
                            <div>
                                <label class="text-xs font-medium text-gray-500">User ID</label>
                                <div class="mt-1 text-sm">{{ $error->user_id }}</div>
                            </div>
                        @endif
                    </div>
                </flux:card>

                {{-- Context & Data --}}
                <flux:card>
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="sm">Context & Data</flux:heading>
                        <flux:button 
                            wire:click="toggleRaw"
                            size="sm"
                            variant="ghost"
                            icon="code-bracket"
                        >
                            {{ $showRaw ? 'Hide' : 'Show' }} Raw
                        </flux:button>
                    </div>

                    @if($showRaw)
                        <div class="space-y-4">
                            @if($error->context)
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Context</label>
                                    <pre class="mt-1 text-xs bg-gray-900 text-gray-100 p-4 rounded overflow-x-auto">{{ json_encode(json_decode($error->context), JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif

                            @if($error->request_data)
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Request Data</label>
                                    <pre class="mt-1 text-xs bg-gray-900 text-gray-100 p-4 rounded overflow-x-auto">{{ json_encode(json_decode($error->request_data), JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif

                            @if($error->meta)
                                <div>
                                    <label class="text-xs font-medium text-gray-500">Meta</label>
                                    <pre class="mt-1 text-xs bg-gray-900 text-gray-100 p-4 rounded overflow-x-auto">{{ json_encode(json_decode($error->meta), JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-gray-500">
                            Click "Show Raw" to view full JSON data
                        </p>
                    @endif
                </flux:card>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Related Errors --}}
                <flux:card>
                    <flux:heading size="sm">Related Errors</flux:heading>

                    <div class="mt-4 space-y-3">
                        @forelse($relatedErrors as $related)
                            <a 
                                href="{{ route('api-guardian.livewire.error.show', $related->id) }}"
                                class="block p-3 border rounded hover:bg-gray-50 transition"
                            >
                                <div class="text-sm font-medium truncate">
                                    {{ $related->message }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ $related->updated_at->diffForHumans() }}
                                </div>
                            </a>
                        @empty
                            <p class="text-sm text-gray-500">No related errors</p>
                        @endforelse
                    </div>
                </flux:card>

                {{-- Actions --}}
                <flux:card>
                    <flux:heading size="sm">Quick Actions</flux:heading>

                    <div class="mt-4 space-y-2">
                        <flux:button 
                            href="{{ route('api-guardian.livewire.errors', ['status_code' => $error->status_code]) }}"
                            variant="ghost"
                            size="sm"
                            class="w-full justify-start"
                            icon="magnifying-glass"
                        >
                            More {{ $error->status_code }} errors
                        </flux:button>

                        <flux:button 
                            href="{{ route('api-guardian.livewire.errors', ['search' => $error->exception_class]) }}"
                            variant="ghost"
                            size="sm"
                            class="w-full justify-start"
                            icon="magnifying-glass"
                        >
                            Same exception type
                        </flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    @else
        <flux:card>
            <div class="text-center py-12">
                <flux:icon name="exclamation-circle" class="text-4xl text-gray-400 mb-2" />
                <p class="text-gray-500">Error not found</p>
            </div>
        </flux:card>
    @endif
</div>
