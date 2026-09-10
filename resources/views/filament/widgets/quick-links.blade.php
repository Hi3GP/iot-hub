<x-filament-widgets::widget class="dash-plain">
    <div class="dash-panel-head">
        <span class="dash-panel-title">快捷入口</span>
    </div>
    <div class="ql-grid">
        @foreach ($links as $link)
            <a href="{{ url($link['url']) }}" class="ql-item">
                <x-filament::icon :icon="$link['icon']" />
                {{ $link['label'] }}
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
