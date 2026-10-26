@if (auth()->user()->hasGlobalRole() || auth()->user()->isNutritionUser())
    @php
        $supportActive = request()->routeIs('admin.tickets.*') || (request()->routeIs('dashboard') && request('workspace') === 'support' && ($activeTab ?? 'overview') === 'overview');
        $nutritionActive = request()->routeIs('nutrition-tasks.*') && request('workspace') === 'micronutrient' && ($activeTab ?? 'overview') === 'overview';
    @endphp
    <nav class="d-flex align-items-center flex-wrap gap-2" aria-label="Workspaces">
        @if (auth()->user()->hasGlobalRole())
            <a href="{{ route('dashboard', ['workspace' => 'support']) }}" class="btn rounded-pill px-4 {{ $supportActive ? 'btn-success' : 'btn-outline-dark' }}" @if($supportActive) aria-current="page" @endif>Support Tickets</a>
        @endif
        @if (auth()->user()->isNutritionUser())
            <a href="{{ route('nutrition-tasks.dashboard', ['workspace' => 'micronutrient']) }}" class="btn rounded-pill px-4 {{ $nutritionActive ? 'btn-success' : 'btn-outline-dark' }}" @if($nutritionActive) aria-current="page" @endif>Micronutrient</a>
        @endif
    </nav>
@endif
