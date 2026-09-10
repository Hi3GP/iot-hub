<x-filament-widgets::widget class="dash-plain">
    <div class="qs-card" wire:poll.30s>
        @foreach ($stats as $stat)
            <a href="{{ url($stat['url']) }}" class="qs-item">
                <span class="qs-icon qs-icon-{{ $stat['color'] }}">
                    <x-filament::icon :icon="$stat['icon']" />
                </span>
                <span class="qs-text">
                    <span class="qs-value">{{ $stat['value'] }}</span>
                    <span class="qs-label">{{ $stat['label'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
