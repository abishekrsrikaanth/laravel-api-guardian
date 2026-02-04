<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Livewire;

use Livewire\Component;

final class Layout extends Component
{
    public string $title = 'API Guardian';

    public function mount(): void
    {
        //
    }

    /**
     * Get navigation items.
     */
    public function getNavigationProperty(): array
    {
        return [
            [
                'name' => 'Dashboard',
                'route' => 'api-guardian.livewire.dashboard',
                'icon' => 'home',
            ],
            [
                'name' => 'Errors',
                'route' => 'api-guardian.livewire.errors',
                'icon' => 'exclamation-triangle',
            ],
            [
                'name' => 'Analytics',
                'route' => 'api-guardian.livewire.analytics',
                'icon' => 'chart-bar',
            ],
            [
                'name' => 'Circuit Breakers',
                'route' => 'api-guardian.livewire.circuit-breakers',
                'icon' => 'shield-check',
            ],
        ];
    }

    public function render()
    {
        return view('api-guardian::livewire.layout');
    }
}
