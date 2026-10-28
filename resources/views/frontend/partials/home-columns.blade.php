{{--
    এক সারিতে পাশাপাশি কয়েকটি "কলাম" স্টাইলের ক্যাটাগরি (thumb_list, img_heads)।
    $entry = ['per_row' => 2|3|4, 'entries' => [ক্যাটাগরির entry, ...]]
    প্রতিটি কলামের ভেতরে আগের মতোই home-entry দিয়ে ব্লক আঁকা হয়।
    সারি অসম্পূর্ণ থাকলে কলামের প্রস্থ একই থাকে (ডানে ফাঁকা জায়গা)।
--}}
@php
    $cols = max(1, min(4, (int) ($entry['per_row'] ?? 1)));
    $span = intdiv(12, $cols);   // ২ → ৬, ৩ → ৪, ৪ → ৩
@endphp

<div class="row g-4 home-cols home-cols--{{ $entry['layout'] ?? 'x' }}" data-cols="{{ $cols }}">
    @foreach($entry['entries'] as $colEntry)
        <div class="col-12 col-md-6 col-lg-{{ $span }} home-col">
            @include('frontend.partials.home-entry', ['entry' => $colEntry])
        </div>
    @endforeach
</div>
