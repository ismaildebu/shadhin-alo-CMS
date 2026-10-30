@php
    $current = \App\Support\Avatars::url($avatarUser);
@endphp

<form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" class="ad-form">
    @csrf

    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:22px;">

        <div class="pf-avatar" id="pf-preview">
            @if($current)
                <img src="{{ $current }}" alt="{{ $avatarUser->name }}">
            @else
                {{ \Illuminate\Support\Str::substr($avatarUser->name, 0, 1) }}
            @endif
        </div>

        <div style="flex:1; min-width:240px;">
            <input type="file" name="avatar" id="pf-file" accept="image/png,image/jpeg,image/webp" class="ad-file" required>
            <p class="ad-hint">jpg, png বা webp। ছবি নিজে থেকে মাঝখান থেকে চৌকো করে ছেঁটে নেওয়া হবে, মুখ মাঝখানে থাকে এমন ছবি দিলে ভালো।</p>

            @error('avatar')
                <p class="ad-field-error">{{ $message }}</p>
            @enderror

            <div class="ad-form-actions">
                <button type="submit" class="ad-btn-solid">ছবি আপলোড করুন</button>

                @if($current)
                    <button type="submit" form="avatar-delete" class="ad-act act-del"
                            onclick="return confirm('ছবিটি মুছে ফেলতে চান?')">ছবি মুছুন</button>
                @endif
            </div>
        </div>

    </div>
</form>

@if($current)
    <form id="avatar-delete" method="POST" action="{{ $destroyUrl }}" hidden>
        @csrf
        @method('DELETE')
    </form>
@endif

<script>
    (function () {
        const input = document.getElementById('pf-file');
        const box = document.getElementById('pf-preview');

        if (! input || ! box) { return; }

        // বেছে নেওয়া ছবি মাঝখান থেকে ৪৮০×৪৮০ এ ছেঁটে ইনপুটে বসানো ও গোল প্রিভিউ দেখানো
        input.addEventListener('change', function () {
            const file = input.files && input.files[0];

            if (! file) { return; }

            const url = URL.createObjectURL(file);
            const img = new Image();

            img.onload = function () {
                const size = 480;
                const canvas = document.createElement('canvas');
                const side = Math.min(img.width, img.height);

                canvas.width = size;
                canvas.height = size;

                const ctx = canvas.getContext('2d');

                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, size, size);
                ctx.drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, size, size);

                canvas.toBlob(function (blob) {
                    if (blob) {
                        try {
                            const dt = new DataTransfer();

                            dt.items.add(new File([blob], 'avatar.jpg', { type: 'image/jpeg' }));
                            input.files = dt.files;
                        } catch (e) { /* পুরোনো ব্রাউজারে মূল ছবিই যাবে */ }
                    }

                    const preview = document.createElement('img');

                    preview.src = canvas.toDataURL('image/jpeg', 0.9);
                    box.textContent = '';
                    box.appendChild(preview);
                    URL.revokeObjectURL(url);
                }, 'image/jpeg', 0.9);
            };

            img.onerror = function () { URL.revokeObjectURL(url); };
            img.src = url;
        });
    })();
</script>