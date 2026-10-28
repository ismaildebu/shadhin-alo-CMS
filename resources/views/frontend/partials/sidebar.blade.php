@foreach(($sidebarWidgets ?? collect()) as $widget)
    @if(array_key_exists($widget->name, \App\Models\SidebarWidget::META))
        @include('frontend.partials.widgets.'.$widget->name, [
            'limit' => $widget->item_limit,
            'layout' => $widget->layout,
            'accent' => $widget->accent,
            'boxHeight' => $widget->box_height,
        ])
    @endif
@endforeach