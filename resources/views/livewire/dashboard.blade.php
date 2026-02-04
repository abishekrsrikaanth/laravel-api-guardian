<div wire:poll.{{ $pollInterval }}ms>
    <flux:heading>Dashboard</flux:heading>
    <flux:subheading>Real-time API error monitoring overview</flux:subheading>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
        {{-- Total Errors --}}
        <flux:card>
            <flux:stat
                label="Total Errors"
                :value="$this->stats['errors']['total']"
                icon="exclamation-triangle"
                color="red"
            />
        </flux:card>

        {{-- Unresolved Errors --}}
        <flux:card>
            <flux:stat
                label="Unresolved"
                :value="$this->stats['errors']['unresolved']"
                icon="exclamation-circle"
                color="orange"
            />
        </flux:card>

        {{-- Circuit Breakers Open --}}
        <flux:card>
            <flux:stat
                label="Open Breakers"
                :value="$this->stats['circuit_breakers']['open']"
                icon="shield-exclamation"
                color="red"
            />
        </flux:card>

        {{-- Today's Errors --}}
        <flux:card>
            <flux:stat
                label="Today"
                :value="$this->stats['errors']['today']"
                icon="calendar"
                color="blue"
            />
        </flux:card>
    </div>

    {{-- Charts and Widgets Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Error Trends Chart --}}
        <flux:card>
            <flux:heading size="sm">Error Trends (7 Days)</flux:heading>
            
            <div class="mt-4" x-data="errorTrendChart(@js($this->trends))">
                <canvas id="trend-chart" width="400" height="200"></canvas>
            </div>
        </flux:card>

        {{-- Top Errors Widget --}}
        <flux:card>
            <flux:heading size="sm">Top Errors</flux:heading>
            
            <div class="mt-4 space-y-3">
                @forelse($this->topErrors as $error)
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">
                                {{ $error['message'] }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $error['endpoint'] ?? 'N/A' }}
                            </div>
                        </div>
                        <flux:badge color="red" size="sm">
                            {{ $error['occurrence_count'] ?? $error['count'] ?? 0 }}
                        </flux:badge>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">No errors in the last 7 days</p>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Recent Errors Table --}}
    <flux:card class="mt-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="sm">Recent Errors</flux:heading>
            
            <flux:button 
                wire:click="refresh" 
                size="sm" 
                variant="ghost"
                icon="arrow-path"
            >
                Refresh
            </flux:button>
        </div>

        <flux:table>
            <flux:columns>
                <flux:column>Status</flux:column>
                <flux:column>Message</flux:column>
                <flux:column>Endpoint</flux:column>
                <flux:column>Occurrences</flux:column>
                <flux:column>Last Seen</flux:column>
                <flux:column>Actions</flux:column>
            </flux:columns>

            <flux:rows>
                @forelse($this->recentErrors as $error)
                    <flux:row>
                        <flux:cell>
                            @if($error->resolved_at)
                                <flux:badge color="green" size="sm">Resolved</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">Open</flux:badge>
                            @endif
                        </flux:cell>

                        <flux:cell>
                            <div class="max-w-md truncate">{{ $error->message }}</div>
                        </flux:cell>

                        <flux:cell>
                            <div class="flex items-center gap-2">
                                <flux:badge color="gray" size="sm">
                                    {{ $error->request_method }}
                                </flux:badge>
                                <span class="text-sm truncate max-w-xs">
                                    {{ $error->request_url }}
                                </span>
                            </div>
                        </flux:cell>

                        <flux:cell>
                            <flux:badge color="blue" size="sm">
                                {{ $error->occurrence_count }}
                            </flux:badge>
                        </flux:cell>

                        <flux:cell>
                            <span class="text-sm text-gray-600">
                                {{ $error->updated_at->diffForHumans() }}
                            </span>
                        </flux:cell>

                        <flux:cell>
                            <flux:button 
                                href="{{ route('api-guardian.livewire.error.show', $error->id) }}"
                                size="sm"
                                variant="ghost"
                            >
                                View
                            </flux:button>
                        </flux:cell>
                    </flux:row>
                @empty
                    <flux:row>
                        <flux:cell colspan="6">
                            <div class="text-center py-8 text-gray-500">
                                No recent errors
                            </div>
                        </flux:cell>
                    </flux:row>
                @endforelse
            </flux:rows>
        </flux:table>
    </flux:card>

    {{-- Last Updated Indicator --}}
    <div class="mt-4 text-center text-sm text-gray-500">
        Last updated: {{ now()->format('H:i:s') }}
    </div>
</div>

@script
<script>
Alpine.data('errorTrendChart', (data) => ({
    init() {
        const ctx = this.$el.querySelector('#trend-chart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => d.period),
                datasets: [{
                    label: 'Errors',
                    data: data.map(d => d.count),
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
}));
</script>
@endscript
