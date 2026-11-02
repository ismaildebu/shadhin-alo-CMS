
@php
    $isEdit = isset($news) && $news->exists;
    $user = auth()->user();

    // Where the article is now + where this user may move it.
    $options = $isEdit
        ? array_values(array_unique(array_merge([$news->status], $statuses)))
        : $statuses;

    $publishedAt = old('published_at', ($isEdit && $news->published_at)
        ? $news->published_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i')
        : '');

    $val = fn (string $field, $default = '') => old(
        $field,
        $isEdit ? ($news->{$field} ?? $default) : $default
    );

    /*
     * Multi-category selection.
     * On validation failure, retain the user's checkbox selections.
     * On edit, load saved categories; fall back to legacy category_id.
     */
    $oldCategoryIds = old('category_ids');

    if (is_array($oldCategoryIds)) {
        $selectedCategoryIds = array_map('intval', $oldCategoryIds);
    } elseif ($isEdit) {
        $selectedCategoryIds = $news->categories
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($selectedCategoryIds === []) {
            $selectedCategoryIds = $news->category_id
                ? [(int) $news->category_id]
                : [];
        }
    } else {
        $selectedCategoryIds = $val('category_id') !== ''
            ? [(int) $val('category_id')]
            : [];
    }

    $hasImage = $isEdit && $news->image;

    // Location: division > district > upazila.
    $selected = [
        'division' => null,
        'district' => null,
        'upazila' => null,
    ];

    $current = $isEdit ? $news->location : null;

    if ($current) {
        foreach ($current->path() as $place) {
            $selected[$place->type] = $place->id;
        }
    }

    $selDivision = old('division_id', $selected['division']);
    $selDistrict = old('district_id', $selected['district']);
    $selUpazila = old('upazila_id', $selected['upazila']);

    $districtOnly = $user->isDistrictCorrespondent()
        ? $user->district
        : null;

    // Keep typed tags after validation failure.
    $initialTags = old(
        'tags',
        $isEdit ? $news->tags->pluck('name')->all() : []
    );

    $initialTags = is_array($initialTags) ? $initialTags : [];
@endphp

@if($errors->any())
    <div class="ad-alert ad-alert-err">
        <ul class="ad-errlist">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="ad-form-grid">

    {{-- Main column --}}
    <div class="ad-form-main">

        <div class="ad-panel">
            <div class="ad-panel-body">

                <div class="ad-fgroup">
                    <label class="ad-label">শোল্ডার (ঐচ্ছিক)</label>
                    <input
                        type="text"
                        name="shoulder"
                        value="{{ $val('shoulder') }}"
                        class="ad-field"
                    >
                </div>

                <div class="ad-fgroup">
                    <label class="ad-label">
                        প্রধান শিরোনাম <span class="ad-req">*</span>
                    </label>
                    <input
                        type="text"
                        name="title"
                        value="{{ $val('title') }}"
                        class="ad-field ad-field-lg"
                        required
                    >
                </div>

                <div class="ad-fgroup">
                    <label class="ad-label">উপ-শিরোনাম</label>
                    <input
                        type="text"
                        name="sub_title"
                        value="{{ $val('sub_title') }}"
                        class="ad-field"
                    >
                </div>

                <div class="ad-fgroup">
                    <label class="ad-label">সারসংক্ষেপ</label>
                    <textarea
                        name="summary"
                        rows="2"
                        class="ad-field"
                    >{{ $val('summary') }}</textarea>
                </div>

            </div>
        </div>

        {{-- Article body --}}
        <div class="ad-panel">
            <div class="ad-panel-head">
                <h2>বিস্তারিত <span class="ad-req">*</span></h2>
                <button
                    type="button"
                    id="editor-gallery-btn"
                    class="ad-act act-edit"
                >গ্যালারি থেকে ছবি যোগ করুন</button>
            </div>

            <div class="ad-panel-body">
                <textarea
                    id="editor"
                    name="description"
                    rows="10"
                    class="ad-field"
                >{{ $val('description') }}</textarea>
            </div>
        </div>

    </div>

    {{-- Side column --}}
    <div class="ad-form-side">

        {{-- Publish --}}
        <div class="ad-panel">
            <div class="ad-panel-head">
                <h2>প্রকাশ</h2>
            </div>

            <div class="ad-panel-body">

                <div class="ad-fgroup">
                    <label class="ad-label">অবস্থা</label>

                    <select name="status" class="ad-field">
                        @foreach($options as $opt)
                            <option
                                value="{{ $opt }}"
                                @selected(old('status', $isEdit ? $news->status : 'draft') === $opt)
                            >
                                {{ \App\Models\News::STATUS_LABELS[$opt] }}
                            </option>
                        @endforeach
                    </select>

                    @unless($user->isEditorOrAdmin())
                        <p class="ad-hint">
                            প্রকাশের জন্য "পর্যালোচনায়" বেছে নিন;
                            সম্পাদক যাচাই করে প্রকাশ করবেন।
                        </p>
                    @endunless
                </div>

                @if($user->isEditorOrAdmin())
                    <div class="ad-fgroup">
                        <label class="ad-label">
                            প্রকাশের সময় ({{ config('app.timezone') }})
                        </label>

                        <input
                            type="datetime-local"
                            name="published_at"
                            value="{{ $publishedAt }}"
                            class="ad-field"
                        >

                        <p class="ad-hint">
                            "নির্ধারিত" হলে ভবিষ্যতের সময় দিন।
                            "প্রকাশিত" হলে ফাঁকা রাখলে এখনই।
                        </p>
                    </div>

                    <div class="ad-fgroup">
                        <div class="ad-checks">
                            <label class="ad-check">
                                <input
                                    type="checkbox"
                                    name="breaking"
                                    value="1"
                                    @checked(old('breaking', $isEdit && $news->breaking))
                                >
                                ব্রেকিং নিউজ
                            </label>

                            <label class="ad-check">
                                <input
                                    type="checkbox"
                                    name="featured"
                                    value="1"
                                    @checked(old('featured', $isEdit && $news->featured))
                                >
                                ফিচার্ড
                            </label>

                            <label class="ad-check">
                                <input
                                    type="checkbox"
                                    name="is_lead"
                                    value="1"
                                    @checked(old('is_lead', $isEdit && $news->is_lead))
                                >
                                প্রধান সংবাদ (Lead)
                            </label>
                        </div>
                    </div>
                @endif

                <div class="ad-form-actions">
                    <button type="submit" class="ad-btn-solid">
                        {{ $isEdit ? 'আপডেট করুন' : 'সংরক্ষণ করুন' }}
                    </button>

                    <a href="{{ route('news.index') }}" class="ad-cancel">
                        বাতিল
                    </a>
                </div>

            </div>
        </div>

        {{-- Category / type / byline --}}
        <div class="ad-panel">
            <div class="ad-panel-head">
                <h2>বিভাগ ও বাইলাইন</h2>
            </div>

            <div class="ad-panel-body">

                {{-- Multiple category checkboxes --}}
                <div class="ad-fgroup">
                    <label class="ad-label">
                        ক্যাটাগরি <span class="ad-req">*</span>
                    </label>

                    <div class="ad-category-checkboxes">
                        @foreach($categories as $category)
                            <label class="ad-category-checkbox">
                                <input
                                    type="checkbox"
                                    name="category_ids[]"
                                    value="{{ $category->id }}"
                                    @checked(
                                        in_array(
                                            (int) $category->id,
                                            $selectedCategoryIds,
                                            true
                                        )
                                    )
                                >
                                <span>{{ $category->name }}</span>
                            </label>
                        @endforeach
                    </div>

                    @error('category_ids')
                        <p class="ad-hint" style="color:#b91c1c;">
                            {{ $message }}
                        </p>
                    @enderror

                    @error('category_ids.*')
                        <p class="ad-hint" style="color:#b91c1c;">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="ad-fgroup">
                    <label class="ad-label">ধরন</label>

                    <select name="type" class="ad-field">
                        @foreach(\App\Models\News::TYPES as $key => $label)
                            <option
                                value="{{ $key }}"
                                @selected($val('type', 'news') === $key)
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="ad-fgroup">
                    <label class="ad-label">রিপোর্টার / বাইলাইন</label>

                    <input
                        type="text"
                        name="reporter"
                        value="{{ $val('reporter') }}"
                        placeholder="ফাঁকা রাখলে আপনার নাম বসবে"
                        class="ad-field"
                    >
                </div>

            </div>
        </div>

        {{-- Image --}}
        <div class="ad-panel">
            <div class="ad-panel-head">
                <h2>মূল ছবি</h2>
            </div>

            <div class="ad-panel-body">

                <div class="ad-fgroup">
                    @php
                        $pickedOld = basename(
                            (string) old('gallery_image', '')
                        );

                        $currentUrl = $hasImage
                            ? asset('uploads/news/'.$news->image)
                            : '';

                        $previewUrl = $pickedOld !== ''
                            ? asset('uploads/news/'.$pickedOld)
                            : $currentUrl;
                    @endphp

                    <img
                        id="image-preview"
                        class="ad-preview"
                        alt=""
                        data-original="{{ $currentUrl }}"
                        @if($previewUrl)
                            src="{{ $previewUrl }}"
                        @else
                            hidden
                        @endif
                    >

                    <input
                        type="hidden"
                        name="gallery_image"
                        id="gallery-image"
                        value="{{ $pickedOld }}"
                    >

                    <div class="ad-picker-actions">
                        <button
                            type="button"
                            id="gallery-open"
                            class="ad-act act-edit"
                        >গ্যালারি থেকে বাছুন</button>

                        <span class="ad-hint" style="margin:0;">
                            অথবা নতুন ছবি আপলোড করুন:
                        </span>
                    </div>

                    <input
                        id="image-input"
                        type="file"
                        name="image"
                        accept="image/jpeg,image/png,image/webp"
                        class="ad-file"
                    >

                    <p
                        id="gallery-picked-note"
                        class="ad-hint"
                        @if($pickedOld === '') hidden @endif
                    >
                        গ্যালারির ছবি বাছা হয়েছে।
                        <button
                            type="button"
                            id="gallery-clear"
                            class="ad-linkbtn"
                        >বাতিল করুন</button>
                    </p>

                    <p class="ad-hint">jpg, png, webp; সর্বোচ্চ ২ MB</p>
                </div>

                <div class="ad-fgroup">
                    <label class="ad-label">ছবির ক্যাপশন</label>
                    <input
                        type="text"
                        name="image_caption"
                        value="{{ $val('image_caption') }}"
                        class="ad-field"
                    >
                </div>

                <div class="ad-fgroup">
                    <label class="ad-label">ছবির ক্রেডিট</label>
                    <input
                        type="text"
                        name="image_credit"
                        value="{{ $val('image_credit') }}"
                        class="ad-field"
                    >
                </div>

                <div class="ad-fgroup">
                    <label class="ad-label" for="chart-shortcode">গ্রাফ শর্টকোড</label>

                    <textarea
                        id="chart-shortcode"
                        name="chart_shortcode"
                        rows="3"
                        dir="ltr"
                        spellcheck="false"
                        placeholder='[chart id="5"]'
                        class="ad-field"
                        style="font-family:ui-monospace, SFMono-Regular, Consolas, monospace;"
                    >{{ $val('chart_shortcode') }}</textarea>

                    <p class="ad-hint">
                        ছবি না থাকলে এই গ্রাফটি মূল ছবির জায়গায় দেখাবে। অ্যাডমিন → গ্রাফ ও চার্ট থেকে শর্টকোড কপি করে বসান।
                    </p>
                </div>

            </div>
        </div>

        {{-- Location --}}
        <div class="ad-panel" id="location-field">
            <div class="ad-panel-head">
                <h2>স্থান</h2>
            </div>

            <div class="ad-panel-body">

                <input type="hidden" name="location_present" value="1">

                <p class="ad-hint" style="margin:0 0 12px;">
                    সংবাদটি কোন এলাকার; জাতীয় বা আন্তর্জাতিক হলে ফাঁকা রাখুন।
                </p>

                @if($districtOnly)
                    <p class="ad-hint" style="margin:0 0 12px;">
                        আপনি <b>{{ $districtOnly->name_bn }}</b> জেলার প্রতিনিধি;
                        শুধু এই জেলা ও এর উপজেলা নির্বাচন করা যাবে।
                    </p>
                @endif

                @if($locationTree['divisions']->isEmpty())
                    <p class="ad-hint" style="color:#b91c1c;">
                        লোকেশন ডেটা লোড করা নেই।
                        টার্মিনালে চালান:
                        <code>php artisan locations:import</code>
                    </p>
                
                 
                    @else
                        <div class="ad-loc-grid">
                            <select
                                name="division_id"
                                data-level="division"
                                class="ad-field"
                            >
                                <option value="">— বিভাগ —</option>
                                @foreach($locationTree['divisions'] as $place)
                                    <option value="{{ $place->id }}" @selected((string) $selDivision === (string) $place->id)>{{ $place->name_bn }}</option>
                                @endforeach
                            </select>

                            <select
                                name="district_id"
                                data-level="district"
                                class="ad-field"
                            >
                                <option value="">— জেলা —</option>
                                @foreach($locationTree['districts'] as $place)
                                    <option value="{{ $place->id }}" data-parent="{{ $place->parent_id }}" @selected((string) $selDistrict === (string) $place->id)>{{ $place->name_bn }}</option>
                                @endforeach
                            </select>

                            <select
                                name="upazila_id"
                                data-level="upazila"
                                class="ad-field"
                            >
                                <option value="">— উপজেলা —</option>
                                @foreach($locationTree['upazilas'] as $place)
                                    <option value="{{ $place->id }}" data-parent="{{ $place->parent_id }}" @selected((string) $selUpazila === (string) $place->id)>{{ $place->name_bn }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
   

            </div>
        </div>

        {{-- Tags --}}
        <div
            class="ad-panel"
            id="tag-field"
            data-suggest-url="{{ route('tags.suggest') }}"
            data-max="{{ \App\Models\Tag::MAX_PER_ARTICLE }}"
            data-max-length="{{ \App\Models\Tag::MAX_NAME }}"
        >
            <div class="ad-panel-head">
                <h2>ট্যাগ</h2>
            </div>

            <div class="ad-panel-body">

                <input type="hidden" name="tags_present" value="1">

                <div class="ad-tagbox">
                    <div data-role="chips"></div>

                    <input
                        type="text"
                        data-role="input"
                        name="tag_input"
                        list="tag-suggestions"
                        value="{{ old('tag_input') }}"
                        maxlength="200"
                        autocomplete="off"
                        placeholder="ট্যাগ লিখে Enter বা কমা দিন"
                        class="ad-tag-input"
                    >
                </div>

                <datalist id="tag-suggestions"></datalist>

                <p class="ad-hint">
                    সর্বোচ্চ {{ \App\Models\Tag::MAX_PER_ARTICLE }}টি;
                    Enter বা কমা দিয়ে যোগ করুন।
                </p>

                @foreach($initialTags as $tagName)
                    <input type="hidden" name="tags[]" value="{{ $tagName }}">
                @endforeach

            </div>
        </div>

    </div>
</div>

<div
    id="gallery-modal"
    class="ad-modal"
    data-list-url="{{ route('media.list') }}"
    hidden
    role="dialog"
    aria-modal="true"
    aria-label="গ্যালারি থেকে ছবি বাছুন"
>
    <div class="ad-modal-box">
        <div class="ad-modal-head">
            <h2>গ্যালারি থেকে ছবি বাছুন</h2>

            <button
                type="button"
                id="gallery-close"
                class="ad-modal-close"
                aria-label="বন্ধ করুন"
            >&times;</button>
        </div>

        <div class="ad-modal-tools">
            <input
                type="text"
                id="gallery-search"
                class="ad-field"
                placeholder="ফাইলের নামে খুঁজুন…"
                autocomplete="off"
            >
        </div>

        <div class="ad-modal-body">
            <div id="gallery-grid" class="ad-pick-grid"></div>
            <div id="gallery-status" class="ad-modal-status"></div>

            <div style="text-align:center;">
                <button
                    type="button"
                    id="gallery-more"
                    class="ad-act"
                    hidden
                >আরও দেখুন</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/admin-tag-input.js') }}"></script>
<script src="{{ asset('js/admin-location-select.js') }}"></script>
<script src="{{ asset('js/admin-media-picker.js') }}"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const textarea = document.querySelector('#editor');

        if (textarea && window.ClassicEditor) {
            ClassicEditor
                .create(textarea, {
                    ckfinder: {
                        uploadUrl: @json(route('media.editor-upload', ['_token' => csrf_token()]))
                    }
                })
                .then(function (editor) {
                    window.newsEditor = editor;
                })
                .catch(function (error) {
                    console.error(error);
                });
        }

        // Insert a gallery photo into the article body.
        const galleryBtn = document.getElementById('editor-gallery-btn');

        function insertIntoEditor(item) {
            const editor = window.newsEditor;

            if (!editor) {
                return;
            }

            editor.editing.view.focus();

            try {
                editor.execute('insertImage', { source: item.path });
            } catch (e) {
                editor.model.change(function (writer) {
                    const image = writer.createElement('imageBlock', {
                        src: item.path
                    });

                    editor.model.insertContent(
                        image,
                        editor.model.document.selection
                    );
                });
            }
        }

        if (galleryBtn && window.AdminMediaPicker) {
            galleryBtn.addEventListener('click', function () {
                if (!window.newsEditor) {
                    alert('এডিটর এখনো লোড হয়নি, একটু অপেক্ষা করুন।');
                    return;
                }

                window.AdminMediaPicker.open(insertIntoEditor, galleryBtn);
            });
        }

        // Live preview of the chosen image.
        const input = document.getElementById('image-input');
        const preview = document.getElementById('image-preview');

        if (input && preview) {
            input.addEventListener('change', function () {
                const file = input.files && input.files[0];

                if (!file) {
                    return;
                }

                preview.src = URL.createObjectURL(file);
                preview.hidden = false;
            });
        }
    });
</script>
