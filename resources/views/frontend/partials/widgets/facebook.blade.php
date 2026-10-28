@php
    $facebookUrl = \App\Support\Settings::get('social_facebook');
@endphp

@if($facebookUrl !== '')
<div class="sa-widget">
    <div class="sa-widget-head">ফেসবুকে আমরা</div>
    <div style="padding:8px; text-align:center; overflow:hidden;">
        <iframe src="https://www.facebook.com/plugins/page.php?href={{ urlencode($facebookUrl) }}&amp;tabs=timeline&amp;width=320&amp;height=420&amp;small_header=true&amp;adapt_container_width=true&amp;hide_cover=false&amp;show_facepile=true"
                width="320" height="420"
                style="border:none; overflow:hidden; max-width:100%;"
                scrolling="no" frameborder="0" loading="lazy"
                title="ফেসবুক পেজ"
                allow="clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
    </div>
</div>
@endif