<div>
    <flux:heading>Analytics</flux:heading>
    <flux:subheading>Error analytics and trends</flux:subheading>

    {{-- Period Selector --}}
    <div class="flex items-center justify-between mt-6">
        <div class="flex gap-2">
            <flux:button 
                wire:click="setPeriod(1)"
                :variant="$days === 1 ? 'solid' : 'ghost'"
                size="sm"
            >
                24 Hours
            </flux:button>
            
            <flux:button 
                wire:click="setPeriod(7)"
                :variant="$days === 7 ? 'solid' : 'ghost'"
                size="sm"
            >
                7 Days
            </flux:button>
            
            <flux:button 
                wire:click="setPeriod(30)"
                :variant="$days === 30 ? 'solid' : 'ghost'"
                size="sm"
            >
                30 Days
            </flux:button>
            
            <flux:button 
                wire:click="setPeriod(90)"
                :variant="$days === 90 ? 'solid' : 'ghost'"
                size="sm"
            >
                90 Days
            </flux:button>
            
            <flux:button 
                wire:click="setPeriod(365)"
                :variant="$days === 365 ? 'solid' : 'ghost'"
                size="sm"
            >
                1 Year
            </flux:button>
        </div>

        <flux:dropdown>
            <flux:button icon="arrow-down-tray" size="sm">
                Export
            </flux:button>
            
            <flux:menu>
                <flux:menu.item 
                    wire:click="export('json', 'errors')"
                    icon="document-text"
                >
                    Errors (JSON)
                </flux:menu.item>
                <flux:menu.item 
                    wire:click="export('csv', 'errors')"
                    icon="table-cells"
                >
                    Errors (CSV)
                </flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item 
                    wire:click="export('json', 'analytics')"
                    icon="document-text"
                >
                    Analytics (JSON)
                </flux:menu.item>
                <flux:menu.item 
                    wire:click="export('csv', 'analytics')"
                    icon="table-cells"
                >
                    Analytics (CSV)
                </flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item 
                    wire:click="export('json', 'trends')"
                    icon="document-text"
                >
                    Trends (JSON)
                </flux:menu.item>
                <flux:menu.item 
                    wire:click="export('csv', 'trends')"
                    icon="table-cells"
                >
                    Trends (CSV)
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    {{-- Summary Statistics --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
        <flux:card>
            <flux:stat
                label="Total Errors"
                :value="$this->analytics['total_errors'] ?? 0"
                icon="exclamation-triangle"
                color="red"
            />
        </flux:card>

        <flux:card>
            <flux:stat
                label="Unique Errors"
                :value="$this->analytics['unique_errors'] ?? 0"
                icon="finger-print"
                color="blue"
            />
        </flux:card>

        <flux:card>
            <flux:stat
                label="Affected Users"
                :value="$this->analytics['affected_users'] ?? 0"
                icon="users"
                color="orange"
            />
        </flux:card>

        <flux:card>
            <flux:stat
                label="Avg Response Time"
                :value="number_format($this->analytics['avg_response_time'] ?? 0, 2) . 'ms'"
                icon="clock"
                color="purple"
            />
        </flux:card>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Error Trends Chart --}}
        <flux:card>
            <flux:heading size="sm">Error Trends</flux:heading>
            <flux:subheading>
                Grouped by {{ $groupBy }}
            </flux:subheading>
            
            <div class="mt-4" x-data="errorTrendsChart(@js($this->trends))">
                <canvas id="trends-chart" height="250"></canvas>
            </div>
        </flux:card>

        {{-- Error Rate Chart --}}
        <flux:card>
            <flux:heading size="sm">Error Rate</flux:heading>
            <flux:subheading>
                Errors per {{ $days <= 1 ? 'hour' : 'day' }}
            </flux:subheading>
            
            <div class="mt-4" x-data="errorRateChart(@js($this->errorRate))">
                <canvas id="rate-chart" height="250"></canvas>
            </div>
        </flux:card>
    </div>

    {{-- Status Code Distribution & Top Errors --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Status Code Distribution --}}
        <flux:card>
            <flux:heading size="sm">Status Code Distribution</flux:heading>
            
            <div class="mt-4" x-data="statusCodeChart(@js($this->distribution))">
                <canvas id="distribution-chart" height="250"></canvas>
            </div>
        </flux:card>

        {{-- Top Errors List --}}
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="sm">Top Errors</flux:heading>
                
                <flux:select wire:model.live="topErrorsLimit" class="w-24">
                    <option value="5">Top 5</option>
                    <option value="10">Top 10</option>
                    <option value="20">Top 20</option>
                    <option value="50">Top 50</option>
                </flux:select>
            </div>

            <div class="space-y-3">
                @forelse($this->topErrors as $index => $error)
                    <div class="flex items-start gap-3 p-3 border rounded hover:bg-gray-50 transition">
                        <div class="flex-shrink-0">
                            <flux:badge color="gray" size="sm">
                                #{{ $index + 1 }}
                            </flux:badge>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">
                                {{ $error['message'] }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $error['endpoint'] ?? 'N/A' }}
                            </div>
                        </div>
                        
                        <div class="flex-shrink-0">
                            <flux:badge color="red">
                                {{ $error['occurrence_count'] ?? $error['count'] ?? 0 }}
                            </flux:badge>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <flux:icon name="inbox" class="text-4xl text-gray-400 mb-2" />
                        <p class="text-gray-500 text-sm">No errors in this period</p>
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Detailed Analytics Table --}}
    <flux:card class="mt-6">
        <flux:heading size="sm">Detailed Breakdown</flux:heading>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- By Status Code --}}
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">By Status Code</h4>
                <div class="space-y-2">
                    @foreach($this->distribution as $item)
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                            <div class="flex items-center gap-2">
                                <flux:badge size="xs">{{ $item['status_code'] }}</flux:badge>
                                <span class="text-sm text-gray-600">
                                    {{ $item['percentage'] }}%
                                </span>
                            </div>
                            <flux:badge color="blue" size="sm">
                                {{ $item['count'] }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- By Time Period --}}
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">By Time Period</h4>
                <div class="space-y-2">
                    @foreach(array_slice($this->trends, 0, 5) as $trend)
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                            <span class="text-sm text-gray-600">
                                {{ $trend['period'] }}
                            </span>
                            <flux:badge color="blue" size="sm">
                                {{ $trend['count'] }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Quick Stats --}}
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-3">Quick Stats</h4>
                <div class="space-y-2">
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                        <span class="text-sm text-gray-600">Peak Hour</span>
                        <span class="text-sm font-medium">
                            {{ $this->analytics['peak_hour'] ?? 'N/A' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                        <span class="text-sm text-gray-600">Error Rate</span>
                        <span class="text-sm font-medium">
                            {{ number_format($this->analytics['error_rate'] ?? 0, 2) }}%
                        </span>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                        <span class="text-sm text-gray-600">Resolved</span>
                        <span class="text-sm font-medium">
                            {{ $this->analytics['resolved_count'] ?? 0 }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                        <span class="text-sm text-gray-600">Unresolved</span>
                        <span class="text-sm font-medium">
                            {{ $this->analytics['unresolved_count'] ?? 0 }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </flux:card>
</div>

@script
<script>
// Error Trends Chart
Alpine.data('errorTrendsChart', (data) => ({
    init() {
        const ctx = this.$el.querySelector('#trends-chart').getContext('2d');
        
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
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}));

// Error Rate Chart
Alpine.data('errorRateChart', (data) => ({
    init() {
        const ctx = this.$el.querySelector('#rate-chart').getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.period),
                datasets: [{
                    label: 'Error Rate',
                    data: data.map(d => d.rate),
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}));

// Status Code Distribution Chart
Alpine.data('statusCodeChart', (data) => ({
    init() {
        const ctx = this.$el.querySelector('#distribution-chart').getContext('2d');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.status_code),
                datasets: [{
                    data: data.map(d => d.count),
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(251, 146, 60, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}));
</script>
@endscript
