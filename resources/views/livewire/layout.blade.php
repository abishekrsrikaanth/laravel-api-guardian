<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'API Guardian' }}</title>
    
    @fluxStyles
</head>
<body>
    <flux:header>
        <flux:sidebar.toggle />
        
        <flux:brand href="{{ route('api-guardian.livewire.dashboard') }}">
            <flux:icon name="shield-check" class="text-2xl" />
            API Guardian
        </flux:brand>

        <flux:spacer />

        <flux:dropdown>
            <flux:button icon="user-circle" />
            
            <flux:menu>
                <flux:menu.item icon="user">Profile</flux:menu.item>
                <flux:menu.item icon="cog">Settings</flux:menu.item>
                <flux:menu.separator />
                <flux:menu.item icon="logout">Logout</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:sidebar>
        <flux:sidebar.toggle />

        <flux:navlist>
            <flux:navlist.item 
                icon="home" 
                href="{{ route('api-guardian.livewire.dashboard') }}"
                :current="request()->routeIs('api-guardian.livewire.dashboard')"
            >
                Dashboard
            </flux:navlist.item>

            <flux:navlist.item 
                icon="exclamation-triangle" 
                href="{{ route('api-guardian.livewire.errors') }}"
                :current="request()->routeIs('api-guardian.livewire.errors*')"
            >
                Errors
            </flux:navlist.item>

            <flux:navlist.item 
                icon="chart-bar" 
                href="{{ route('api-guardian.livewire.analytics') }}"
                :current="request()->routeIs('api-guardian.livewire.analytics')"
            >
                Analytics
            </flux:navlist.item>

            <flux:navlist.item 
                icon="shield-check" 
                href="{{ route('api-guardian.livewire.circuit-breakers') }}"
                :current="request()->routeIs('api-guardian.livewire.circuit-breakers')"
            >
                Circuit Breakers
            </flux:navlist.item>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist>
            <flux:navlist.item icon="information-circle">
                Help & Documentation
            </flux:navlist.item>
        </flux:navlist>
    </flux:sidebar>

    <flux:main>
        {{ $slot }}
    </flux:main>

    @fluxScripts
</body>
</html>
