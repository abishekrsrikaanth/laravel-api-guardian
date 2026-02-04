<div wire:poll.{{ $pollInterval }}ms>
    <flux:heading>Circuit Breakers</flux:heading>
    <flux:subheading>Monitor and manage circuit breaker states</flux:subheading>

    {{-- Statistics Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <flux:card>
            <flux:stat
                label="Total Breakers"
                :value="$this->stats['total'] ?? 0"
                icon="shield-check"
                color="blue"
            />
        </flux:card>

        <flux:card>
            <flux:stat
                label="Closed"
                :value="$this->stats['closed'] ?? 0"
                icon="check-circle"
                color="green"
            />
        </flux:card>

        <flux:card>
            <flux:stat
                label="Half Open"
                :value="$this->stats['half_open'] ?? 0"
                icon="exclamation-circle"
                color="yellow"
            />
        </flux:card>

        <flux:card>
            <flux:stat
                label="Open"
                :value="$this->stats['open'] ?? 0"
                icon="x-circle"
                color="red"
            />
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="mt-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- State Filter --}}
            <div>
                <flux:select wire:model.live="stateFilter">
                    <option value="all">All States</option>
                    <option value="closed">Closed</option>
                    <option value="half_open">Half Open</option>
                    <option value="open">Open</option>
                </flux:select>
            </div>

            {{-- Service Search --}}
            <div>
                <flux:input 
                    wire:model.live.debounce.500ms="searchService" 
                    placeholder="Search by service name..."
                    icon="magnifying-glass"
                />
            </div>
        </div>

        <div class="mt-4">
            <flux:button 
                wire:click="clearFilters" 
                variant="ghost" 
                size="sm"
                icon="x-mark"
            >
                Clear Filters
            </flux:button>
        </div>
    </flux:card>

    {{-- Circuit Breakers Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        @forelse($this->circuitBreakers as $breaker)
            <flux:card>
                {{-- Header --}}
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <flux:heading size="sm">{{ $breaker->service }}</flux:heading>
                            <flux:badge :color="getStateBadgeColor($breaker->state)">
                                {{ ucfirst(str_replace('_', ' ', $breaker->state)) }}
                            </flux:badge>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $breaker->identifier }}
                        </p>
                    </div>

                    {{-- State Icon --}}
                    <div>
                        @if($breaker->state === 'closed')
                            <flux:icon name="check-circle" class="text-2xl text-green-500" />
                        @elseif($breaker->state === 'half_open')
                            <flux:icon name="exclamation-circle" class="text-2xl text-yellow-500" />
                        @else
                            <flux:icon name="x-circle" class="text-2xl text-red-500" />
                        @endif
                    </div>
                </div>

                {{-- Statistics --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="p-3 bg-gray-50 rounded">
                        <div class="text-xs text-gray-500 mb-1">Failure Count</div>
                        <div class="text-lg font-semibold text-gray-900">
                            {{ $breaker->failure_count }}
                        </div>
                    </div>

                    <div class="p-3 bg-gray-50 rounded">
                        <div class="text-xs text-gray-500 mb-1">Success Count</div>
                        <div class="text-lg font-semibold text-gray-900">
                            {{ $breaker->success_count }}
                        </div>
                    </div>

                    <div class="p-3 bg-gray-50 rounded">
                        <div class="text-xs text-gray-500 mb-1">Failure Threshold</div>
                        <div class="text-lg font-semibold text-gray-900">
                            {{ $breaker->failure_threshold }}
                        </div>
                    </div>

                    <div class="p-3 bg-gray-50 rounded">
                        <div class="text-xs text-gray-500 mb-1">Success Threshold</div>
                        <div class="text-lg font-semibold text-gray-900">
                            {{ $breaker->success_threshold }}
                        </div>
                    </div>
                </div>

                {{-- Progress Bars --}}
                <div class="space-y-3 mb-4">
                    {{-- Failure Progress --}}
                    <div>
                        <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                            <span>Failures</span>
                            <span>{{ $breaker->failure_count }}/{{ $breaker->failure_threshold }}</span>
                        </div>
                        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div 
                                class="h-full bg-red-500 transition-all"
                                style="width: {{ min(($breaker->failure_count / $breaker->failure_threshold) * 100, 100) }}%"
                            ></div>
                        </div>
                    </div>

                    {{-- Success Progress (for half_open state) --}}
                    @if($breaker->state === 'half_open')
                        <div>
                            <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                <span>Successes</span>
                                <span>{{ $breaker->success_count }}/{{ $breaker->success_threshold }}</span>
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div 
                                    class="h-full bg-green-500 transition-all"
                                    style="width: {{ min(($breaker->success_count / $breaker->success_threshold) * 100, 100) }}%"
                                ></div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Timestamps --}}
                <div class="space-y-2 mb-4 text-sm">
                    @if($breaker->last_failure_at)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Last Failure:</span>
                            <span class="text-gray-900" title="{{ $breaker->last_failure_at }}">
                                {{ $breaker->last_failure_at->diffForHumans() }}
                            </span>
                        </div>
                    @endif

                    @if($breaker->next_attempt_at)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Next Attempt:</span>
                            <span class="text-gray-900" title="{{ $breaker->next_attempt_at }}">
                                {{ $breaker->next_attempt_at->diffForHumans() }}
                            </span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Last Updated:</span>
                        <span class="text-gray-900" title="{{ $breaker->updated_at }}">
                            {{ $breaker->updated_at->diffForHumans() }}
                        </span>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex gap-2 pt-4 border-t">
                    @if($breaker->state !== 'closed')
                        <flux:button 
                            wire:click="reset('{{ $breaker->id }}')"
                            wire:confirm="Are you sure you want to reset this circuit breaker?"
                            size="sm"
                            color="green"
                            icon="arrow-path"
                            class="flex-1"
                        >
                            Reset
                        </flux:button>
                    @endif

                    <flux:button 
                        wire:click="testSuccess('{{ $breaker->id }}')"
                        size="sm"
                        variant="ghost"
                        color="green"
                        icon="check"
                        class="flex-1"
                    >
                        Test Success
                    </flux:button>

                    <flux:button 
                        wire:click="testFailure('{{ $breaker->id }}')"
                        size="sm"
                        variant="ghost"
                        color="red"
                        icon="x-mark"
                        class="flex-1"
                    >
                        Test Failure
                    </flux:button>
                </div>
            </flux:card>
        @empty
            <flux:card class="lg:col-span-2">
                <div class="text-center py-12">
                    <flux:icon name="shield-check" class="text-4xl text-gray-400 mb-2" />
                    <p class="text-gray-500">No circuit breakers found</p>
                    @if($stateFilter !== 'all' || $searchService)
                        <flux:button 
                            wire:click="clearFilters" 
                            size="sm" 
                            class="mt-4"
                        >
                            Clear Filters
                        </flux:button>
                    @endif
                </div>
            </flux:card>
        @endforelse
    </div>

    {{-- Last Updated Indicator --}}
    <div class="mt-6 text-center text-sm text-gray-500">
        Auto-refreshing every {{ $pollInterval / 1000 }} seconds • Last updated: {{ now()->format('H:i:s') }}
    </div>

    {{-- State Legend --}}
    <flux:card class="mt-6">
        <flux:heading size="sm">State Descriptions</flux:heading>
        
        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex items-start gap-3">
                <flux:icon name="check-circle" class="text-2xl text-green-500 flex-shrink-0" />
                <div>
                    <div class="font-medium text-sm">Closed</div>
                    <p class="text-xs text-gray-600 mt-1">
                        Normal operation. Requests are passing through successfully.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <flux:icon name="exclamation-circle" class="text-2xl text-yellow-500 flex-shrink-0" />
                <div>
                    <div class="font-medium text-sm">Half Open</div>
                    <p class="text-xs text-gray-600 mt-1">
                        Testing recovery. Allowing limited requests to check if service is healthy.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <flux:icon name="x-circle" class="text-2xl text-red-500 flex-shrink-0" />
                <div>
                    <div class="font-medium text-sm">Open</div>
                    <p class="text-xs text-gray-600 mt-1">
                        Failure detected. Blocking requests to prevent cascading failures.
                    </p>
                </div>
            </div>
        </div>
    </flux:card>
</div>
