<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            স্বাধীন আলো CMS Dashboard
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    <h1 class="text-3xl font-bold mb-4">
                        স্বাধীন আলো CMS
                    </h1>

                    <p>স্বাগতম, {{ Auth::user()->name }}!</p>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>