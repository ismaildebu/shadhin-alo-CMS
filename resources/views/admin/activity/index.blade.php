<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">কার্যকলাপ লগ</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="overflow-x-auto">
                <table class="w-full border text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border p-2">কখন</th>
                            <th class="border p-2">কে</th>
                            <th class="border p-2 text-left">কাজ</th>
                            <th class="border p-2">বিষয়</th>
                            <th class="border p-2 text-left">বিস্তারিত</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($activities as $activity)
                        <tr>
                            <td class="border p-2 whitespace-nowrap">{{ $activity->created_at->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</td>
                            <td class="border p-2 text-center">{{ $activity->user->name ?? 'সিস্টেম' }}</td>
                            <td class="border p-2"><code>{{ $activity->action }}</code></td>
                            <td class="border p-2 text-center">{{ $activity->subject_type ? $activity->subject_type.' #'.$activity->subject_id : '—' }}</td>
                            <td class="border p-2 text-xs text-gray-600">{{ $activity->properties ? json_encode($activity->properties, JSON_UNESCAPED_UNICODE) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="border p-4 text-center">কোনো কার্যকলাপ নেই।</td></tr>
                    @endforelse
                    </tbody>
                </table>
                </div>
                <div class="mt-4">{{ $activities->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
