@php
    $isEdit = $user->exists;
    $isSelf = $isEdit && $user->is(auth()->user());

    // The protected super administrator: only the password can be changed
    // (the controller, request and database triggers enforce the same rule).
    $isSuper = $isEdit && $user->isSuperAdmin();

    $val = fn (string $field, $default = '') =>
        old($field, $isEdit ? ($user->{$field} ?? $default) : $default);

    $selectedRole = $val('role', 'reporter');
    $selectedDistrict = (string) $val('district_id', '');
    $selectedUpazila = (string) $val('upazila_id', '');

    // If an existing reporter has an upazila but no district in old input,
    // derive the district from that upazila.
    if ($selectedDistrict === '' && $selectedUpazila !== '') {
        $selectedDistrict = (string) (
            $upazilas->firstWhere('id', (int) $selectedUpazila)?->parent_id ?? ''
        );
    }

    // Roles that work in the field and may have a district / upazila.
    $reporterRoles = \App\Models\User::REPORTER_ROLES;
    $reporterRoleNames = implode(', ', array_map(
        fn ($role) => \App\Models\User::ROLES[$role],
        $reporterRoles
    ));

    // Role dropdown groups. The super administrator role is never offered:
    // it cannot be assigned from a form. A role not listed here still shows,
    // under "অন্যান্য".
    $roleGroups = [
        'সম্পাদকীয়' => ['admin', 'deputy-editor', 'news-editor', 'joint-news-editor', 'editor', 'sub-editor'],
        'রিপোর্টিং' => ['chief-reporter', 'special-reporter', 'staff-reporter', 'reporter'],
        'প্রোডাকশন' => ['media-editor', 'designer', 'ad-manager'],
        'অন্যান্য' => ['user'],
    ];

    $placedRoles = array_merge(...array_values($roleGroups));
    $otherRoles = array_diff(
        array_keys(\App\Models\User::ROLES),
        $placedRoles,
        [\App\Models\User::ROLE_SUPER_ADMIN]
    );
    $roleGroups['অন্যান্য'] = array_merge($roleGroups['অন্যান্য'], array_values($otherRoles));
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

@if($isSuper)
    <div class="ad-panel ad-fgroup">
        <div class="ad-panel-body">
            <p class="ad-label">সুপার অ্যাডমিন অ্যাকাউন্ট</p>
            <p class="ad-hint">
                এই অ্যাকাউন্ট স্থায়ী। শুধু পাসওয়ার্ড বদলানো যায়; নাম, ইমেইল, রোল ও অন্য তথ্য পরিবর্তন বা অ্যাকাউন্ট মোছা যায় না।
            </p>
        </div>
    </div>
@endif

<div class="ad-two ad-fgroup">
    <div>
        <label class="ad-label" for="name">
            নাম <span class="ad-req">*</span>
        </label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ $val('name') }}"
            class="ad-field"
            maxlength="255"
            required
            @disabled($isSuper)
        >
    </div>

    <div>
        <label class="ad-label" for="email">
            ইমেইল <span class="ad-req">*</span>
        </label>
        <input
            id="email"
            type="email"
            name="email"
            value="{{ $val('email') }}"
            class="ad-field"
            maxlength="255"
            required
            @disabled($isSuper)
        >
    </div>
</div>

<div class="ad-two ad-fgroup">
    <div>
        <label class="ad-label" for="phone">ফোন</label>
        <input
            id="phone"
            type="text"
            name="phone"
            value="{{ $val('phone') }}"
            class="ad-field"
            maxlength="20"
            @disabled($isSuper)
        >
    </div>

    <div>
        <label class="ad-label" for="password">
            পাসওয়ার্ড
            @unless($isEdit)
                <span class="ad-req">*</span>
            @endunless
        </label>
        <input
            id="password"
            type="password"
            name="password"
            autocomplete="new-password"
            class="ad-field"
            @unless($isEdit) required @endunless
        >
        @if($isEdit)
            <p class="ad-hint">
                ফাঁকা রাখলে বর্তমান পাসওয়ার্ড অপরিবর্তিত থাকবে।
            </p>
        @endif
    </div>
</div>

<div class="ad-two ad-fgroup">
    <div>
        <label class="ad-label" for="role">রোল</label>
        <select
            id="role"
            name="role"
            class="ad-field"
            @if($isSelf || $isSuper) disabled @endif
        >
            @if($isSuper)
                <option value="{{ \App\Models\User::ROLE_SUPER_ADMIN }}" selected>
                    {{ \App\Models\User::ROLES[\App\Models\User::ROLE_SUPER_ADMIN] }}
                </option>
            @else
                @foreach($roleGroups as $groupLabel => $groupRoles)
                    <optgroup label="{{ $groupLabel }}">
                        @foreach($groupRoles as $key)
                            @if(isset(\App\Models\User::ROLES[$key]))
                                <option
                                    value="{{ $key }}"
                                    @selected($selectedRole === $key)
                                >
                                    {{ \App\Models\User::ROLES[$key] }}
                                </option>
                            @endif
                        @endforeach
                    </optgroup>
                @endforeach
            @endif
        </select>

        @if($isSelf && ! $isSuper)
            <input type="hidden" name="role" value="{{ $user->role }}">
            <p class="ad-hint">
                নিজের রোল পরিবর্তন করা যাবে না।
            </p>
        @endif
    </div>

    <div class="ad-check-field">
        <label class="ad-check" for="is_active">
            <input
                id="is_active"
                type="checkbox"
                name="is_active"
                value="1"
                @checked((bool) old('is_active', $isEdit ? $user->is_active : true))
                @if($isSelf) onclick="return false" @endif
                @disabled($isSuper)
            >
            সক্রিয় (নিষ্ক্রিয় ইউজার লগইন করতে পারে না)
        </label>
    </div>
</div>

@unless($isSuper)
<div id="user-location-fields" class="ad-panel ad-fgroup">
    <div class="ad-panel-body">
        <p class="ad-label">রিপোর্টারের এলাকা</p>
        <p class="ad-hint">
            শুধু এই রোলের জন্য জেলা ও উপজেলা নির্ধারণ করুন: {{ $reporterRoleNames }}।
            অন্য রোল নির্বাচন করলে এই তথ্য সংরক্ষণ করা হবে না।
        </p>

        <div class="ad-two ad-fgroup">
            <div>
                <label class="ad-label" for="district_id">
                    জেলা
                </label>

                <select
                    id="district_id"
                    name="district_id"
                    class="ad-field"
                    data-selected="{{ $selectedDistrict }}"
                >
                    <option value="">জেলা নির্বাচন করুন</option>

                    @foreach($districts as $district)
                        <option
                            value="{{ $district->id }}"
                            @selected($selectedDistrict === (string) $district->id)
                        >
                            {{ $district->name_bn }}
                        </option>
                    @endforeach
                </select>

                @error('district_id')
                    <p class="ad-hint" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="ad-label" for="upazila_id">
                    উপজেলা
                </label>

                <select
                    id="upazila_id"
                    name="upazila_id"
                    class="ad-field"
                    data-selected="{{ $selectedUpazila }}"
                >
                    <option value="">উপজেলা নির্বাচন করুন</option>

                    @foreach($upazilas as $upazila)
                        <option
                            value="{{ $upazila->id }}"
                            data-district="{{ $upazila->parent_id }}"
                            @selected($selectedUpazila === (string) $upazila->id)
                        >
                            {{ $upazila->name_bn }}
                        </option>
                    @endforeach
                </select>

                @error('upazila_id')
                    <p class="ad-hint" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
</div>
@endunless

<div class="ad-fgroup">
    <label class="ad-label" for="bio">পরিচিতি (Bio)</label>
    <textarea
        id="bio"
        name="bio"
        rows="3"
        class="ad-field"
        maxlength="1000"
        @disabled($isSuper)
    >{{ $val('bio') }}</textarea>
    @error('bio')
        <p class="ad-hint" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="ad-form-actions">
    <button type="submit" class="ad-btn-solid">
        {{ $isEdit ? 'আপডেট করুন' : 'ইউজার তৈরি করুন' }}
    </button>

    <a href="{{ route('users.index') }}" class="ad-cancel">
        বাতিল
    </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role');
    const districtSelect = document.getElementById('district_id');
    const upazilaSelect = document.getElementById('upazila_id');
    const locationFields = document.getElementById('user-location-fields');

    if (!roleSelect || !districtSelect || !upazilaSelect || !locationFields) {
        return;
    }

    // Roles that may have a district / upazila (User::REPORTER_ROLES).
    const reporterRoles = @json($reporterRoles);

    const upazilaOptions = Array.from(upazilaSelect.options)
        .filter(option => option.value !== '')
        .map(option => ({
            value: option.value,
            text: option.textContent.trim(),
            district: option.dataset.district
        }));

    function filterUpazilas(resetSelection = false) {
        const districtId = districtSelect.value;
        const previousUpazila = upazilaSelect.value;

        upazilaSelect.replaceChildren();

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = districtId
            ? 'উপজেলা নির্বাচন করুন'
            : 'আগে জেলা নির্বাচন করুন';

        upazilaSelect.appendChild(placeholder);

        upazilaOptions.forEach(item => {
            if (item.district !== districtId) {
                return;
            }

            const option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.text;
            option.dataset.district = item.district;

            if (!resetSelection && item.value === previousUpazila) {
                option.selected = true;
            }

            upazilaSelect.appendChild(option);
        });

        if (
            resetSelection ||
            !Array.from(upazilaSelect.options).some(
                option => option.value === previousUpazila
            )
        ) {
            upazilaSelect.value = '';
        }

        upazilaSelect.disabled = !districtId;
    }

    function toggleLocationFields() {
        const isReporter = reporterRoles.includes(roleSelect.value);

        locationFields.hidden = !isReporter;
        districtSelect.disabled = !isReporter;
        upazilaSelect.disabled = !isReporter || !districtSelect.value;
    }

    districtSelect.addEventListener('change', function () {
        filterUpazilas(true);
    });

    roleSelect.addEventListener('change', toggleLocationFields);

    filterUpazilas(false);
    toggleLocationFields();
});
</script>