@php
    $menuFooterDesign = \App\Support\MenuFooterDesign::all();
@endphp
<style>{!! $menuFooterDesign['css'] !!}</style>
