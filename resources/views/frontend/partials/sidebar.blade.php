@php
    $widgets = ($sidebarWidgets ?? collect())->filter(
        fn ($widget) => array_key_exists($widget->name, \App\Models\SidebarWidget::META)
    );

    // হোম ও সংবাদ পেজ তথ্য নিজে পাঠায়; বাকি পেজে (বিভাগ, ট্যাগ, স্থান, ভিডিও) এখান থেকেই জোগাড় হয়।
    $sidebarData = \App\Support\SidebarData::for($widgets, [
        'latestNews' => $latestNews ?? null,
        'popularNews' => $popularNews ?? null,
        'videos' => $videos ?? null,
    ]);
@endphp

@foreach($widgets as $widget)
    @include('frontend.partials.widgets.'.$widget->name, $sidebarData + [
        'limit' => $widget->item_limit,
        'layout' => $widget->layout,
        'accent' => $widget->accent,
        'boxHeight' => $widget->box_height,
    ])
@endforeach
