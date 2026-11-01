@php
    // Focus points chosen in the photo gallery: keeps the important part of a photo visible
    // when a card crops it (object-fit: cover). Empty when no image has a focus point.
    $focusCss = \App\Models\Media::focusCss();
@endphp
@if($focusCss !== '')
<style>{!! $focusCss !!}</style>
@endif