<x-app-layout>
    <x-slot name="header">
        <h1 style="font-size:1.25rem;font-weight:700;margin:0">রোল ও অনুমতি</h1>
    </x-slot>

    <style>
        .rp-wrap { max-width: 1000px; margin: 0 auto; padding: 24px 16px; }
        .rp-note { margin: 0 0 16px; color: #4b5563; font-size: .95rem; line-height: 1.6; }
        .rp-flash { margin: 0 0 16px; padding: 10px 14px; border-radius: 8px; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .rp-error { margin: 0 0 16px; padding: 10px 14px; border-radius: 8px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .rp-scroll { overflow-x: auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; }
        .rp-table { width: 100%; border-collapse: collapse; min-width: 640px; }
        .rp-table th, .rp-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: top; text-align: left; }
        .rp-table thead th { background: #f8fafc; font-weight: 700; font-size: .9rem; }
        .rp-table tbody tr:last-child td { border-bottom: 0; }
        .rp-c { text-align: center !important; width: 110px; }
        .rp-help { margin-top: 4px; font-size: .82rem; color: #6b7280; line-height: 1.5; }
        .rp-help.rp-danger { color: #b45309; }
        .rp-lock { display: block; margin-top: 2px; font-size: .72rem; color: #6b7280; }
        .rp-box { width: 18px; height: 18px; cursor: pointer; }
        .rp-box[disabled] { cursor: not-allowed; opacity: .55; }
        .rp-actions { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-top: 18px; }
        .rp-btn { padding: 10px 20px; border-radius: 8px; border: 0; font-weight: 600; cursor: pointer; font-size: .95rem; }
        .rp-btn-primary { background: #1d4ed8; color: #fff; }
        .rp-btn-ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
    </style>

    <div class="rp-wrap">

        @if (session('success'))
            <div class="rp-flash">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="rp-error">{{ $errors->first() }}</div>
        @endif

        <p class="rp-note">
            প্রতিটি রোলের জন্য টিক দিয়ে অনুমতি দিন বা তুলে নিন। পরিবর্তন সংরক্ষণের পর সাথে সাথে কার্যকর হয়।
            সুপার অ্যাডমিন সবসময় সব অনুমতি পায়, তাই এখানে পরিবর্তনযোগ্য নয়।
            অ্যাডমিন রোল থেকে "প্যানেলে প্রবেশ" ও "ইউজার ম্যানেজমেন্ট" তোলা যায় না, যাতে কেউ নিজেকে লক আউট না করে।
        </p>

        <form method="POST" action="{{ route('roles.update') }}">
            @csrf
            @method('PUT')

            <div class="rp-scroll">
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th>অনুমতি</th>
                            <th class="rp-c">সুপার অ্যাডমিন</th>
                            @foreach ($roles as $role => $label)
                                <th class="rp-c">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissions as $key => $meta)
                            <tr>
                                <td>
                                    <strong>{{ $meta['label'] }}</strong>
                                    <div class="rp-help {{ $meta['danger'] ? 'rp-danger' : '' }}">{{ $meta['help'] }}</div>
                                </td>
                                <td class="rp-c">
                                    <input type="checkbox" class="rp-box" checked disabled aria-label="সুপার অ্যাডমিন">
                                </td>
                                @foreach ($roles as $role => $label)
                                    @php($locked = in_array($role, $meta['locked'], true))
                                    <td class="rp-c">
                                        <input
                                            type="checkbox"
                                            class="rp-box"
                                            name="perm[{{ $role }}][{{ $key }}]"
                                            value="1"
                                            aria-label="{{ $label }}: {{ $meta['label'] }}"
                                            @checked($matrix[$role][$key] ?? false)
                                            @disabled($locked)
                                        >
                                        @if ($locked)
                                            <span class="rp-lock">সবসময় চালু</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="rp-actions">
                <button type="submit" class="rp-btn rp-btn-primary">সংরক্ষণ করুন</button>
            </div>
        </form>

        <form method="POST" action="{{ route('roles.reset') }}"
              onsubmit="return confirm('সব রোল ডিফল্ট অনুমতিতে ফিরবে। নিশ্চিত?')"
              style="margin-top:12px">
            @csrf
            <button type="submit" class="rp-btn rp-btn-ghost">ডিফল্টে ফিরিয়ে নিন</button>
        </form>

    </div>
</x-app-layout>