<div>
    <flux:heading>Errors</flux:heading>
    <flux:subheading>Monitor and manage API errors</flux:subheading>

    {{-- Filters Section --}}
    <flux:card class="mt-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <div class="md:col-span-2">
                <flux:input 
                    wire:model.live.debounce.500ms="search" 
                    placeholder="Search errors..."
                    icon="magnifying-glass"
                />
            </div>

            {{-- Status Filter --}}
            <div>
                <flux:select wire:model.live="status">
                    <option value="all">All Status</option>
                    <option value="unresolved">Unresolved</option>
                    <option value="resolved">Resolved</option>
                </flux:select>
            </div>

            {{-- Status Code Filter --}}
            <div>
                <flux:input 
                    wire:model.live="statusCode" 
                    type="number"
                    placeholder="Status code..."
                    min="100"
                    max="599"
                />
            </div>
        </div>

        <div class="flex items-center justify-between mt-4">
            <flux:button 
                wire:click="clearFilters" 
                variant="ghost" 
                size="sm"
                icon="x-mark"
            >
                Clear Filters
            </flux:button>

            {{-- Bulk Actions --}}
            @if(count($selected) > 0)
                <div class="flex gap-2">
                    <flux:badge color="blue">
                        {{ count($selected) }} selected
                    </flux:badge>
                    
                    <flux:button 
                        wire:click="bulkResolve"
                        wire:confirm="Are you sure you want to resolve {{ count($selected) }} errors?"
                        size="sm"
                        color="green"
                        icon="check"
                    >
                        Resolve Selected
                    </flux:button>
                    
                    <flux:button 
                        wire:click="bulkDelete"
                        wire:confirm="Are you sure you want to delete {{ count($selected) }} errors? This cannot be undone."
                        size="sm"
                        color="red"
                        icon="trash"
                    >
                        Delete Selected
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:card>

    {{-- Errors Table --}}
    <flux:card class="mt-6" wire:poll.{{ $pollInterval }}ms>
        <flux:table>
            <flux:columns>
                <flux:column>
                    <flux:checkbox 
                        wire:model.live="selectAll"
                        @change="$wire.selectAll = $event.target.checked"
                    />
                </flux:column>
                <flux:column>Status</flux:column>
                <flux:column>Error</flux:column>
                <flux:column>Endpoint</flux:column>
                <flux:column sortable>Occurrences</flux:column>
                <flux:column sortable>Last Seen</flux:column>
                <flux:column>Actions</flux:column>
            </flux:columns>

            <flux:rows>
                @forelse($this->errors as $error)
                    <flux:row :key="$error->id">
                        <flux:cell>
                            <flux:checkbox 
                                wire:model.live="selected"
                                :value="$error->id"
                            />
                        </flux:cell>

                        <flux:cell>
                            @if($error->resolved_at)
                                <flux:badge color="green">Resolved</flux:badge>
                            @else
                                <flux:badge color="red">Open</flux:badge>
                            @endif
                        </flux:cell>

                        <flux:cell>
                            <div>
                                <div class="font-medium text-sm truncate max-w-md">
                                    {{ $error->message }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <flux:badge color="gray" size="xs">
                                        {{ $error->status_code }}
                                    </flux:badge>
                                    <span class="ml-1">
                                        {{ $error->exception_class }}
                                    </span>
                                </div>
                            </div>
                        </flux:cell>

                        <flux:cell>
                            <div class="flex items-center gap-1">
                                <flux:badge size="xs">{{ $error->request_method }}</flux:badge>
                                <span class="text-sm truncate max-w-xs">
                                    {{ $error->request_url }}
                                </span>
                            </div>
                        </flux:cell>

                        <flux:cell>
                            <flux:badge color="blue">
                                {{ $error->occurrence_count }}
                            </flux:badge>
                        </flux:cell>

                        <flux:cell>
                            <span class="text-sm text-gray-600" title="{{ $error->updated_at }}">
                                {{ $error->updated_at->diffForHumans() }}
                            </span>
                        </flux:cell>

                        <flux:cell>
                            <div class="flex gap-1">
                                <flux:button 
                                    href="{{ route('api-guardian.livewire.error.show', $error->id) }}"
                                    size="sm"
                                    variant="ghost"
                                    icon="eye"
                                >
                                    View
                                </flux:button>

                                @unless($error->resolved_at)
                                    <flux:button 
                                        wire:click="resolveError('{{ $error->id }}')"
                                        size="sm"
                                        variant="ghost"
                                        color="green"
                                        icon="check"
                                    >
                                        Resolve
                                    </flux:button>
                                @endunless

                                <flux:button 
                                    wire:click="deleteError('{{ $error->id }}')"
                                    wire:confirm="Are you sure you want to delete this error?"
                                    size="sm"
                                    variant="ghost"
                                    color="red"
                                    icon="trash"
                                >
                                    Delete
                                </flux:button>
                            </div>
                        </flux:cell>
                    </flux:row>
                @empty
                    <flux:row>
                        <flux:cell colspan="7">
                            <div class="text-center py-12">
                                <flux:icon name="inbox" class="text-4xl text-gray-400 mb-2" />
                                <p class="text-gray-500">No errors found</p>
                                @if($search || $status !== 'all' || $statusCode)
                                    <flux:button 
                                        wire:click="clearFilters" 
                                        size="sm" 
                                        class="mt-4"
                                    >
                                        Clear Filters
                                    </flux:button>
                                @endif
                            </div>
                        </flux:cell>
                    </flux:row>
                @endforelse
            </flux:rows>
        </flux:table>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $this->errors->links() }}
        </div>
    </flux:card>
</div>
