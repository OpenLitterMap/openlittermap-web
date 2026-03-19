{{-- resources/views/admin/redis-user.blade.php --}}
@extends('app')

@section('content')
    <div class="container mx-auto p-6">
        <div class="mb-6">
            <a href="{{ route('admin.redis') }}" class="text-blue-600 hover:text-blue-800">← Back to Redis Overview</a>
        </div>

        <h1 class="text-3xl font-bold mb-6">User Redis Data: {{ $userData->name }}</h1>

        {{-- Basic Stats --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Basic Statistics</h2>
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600">{{ $raw['uploads'] }}</div>
                    <div class="text-sm text-gray-600">Uploads</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600">{{ round($raw['xp']) }}</div>
                    <div class="text-sm text-gray-600">XP</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-purple-600">{{ $raw['streak'] }}</div>
                    <div class="text-sm text-gray-600">Current Streak</div>
                </div>
            </div>
        </div>

        {{-- Objects --}}
        @if(!empty($metrics['objects']))
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Objects Tagged</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($metrics['objects'] as $item => $count)
                        <div class="border rounded p-3">
                            <div class="font-medium">{{ $item }}</div>
                            <div class="text-sm text-gray-600">{{ number_format($count) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Categories --}}
        @if(!empty($metrics['categories']))
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Categories</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($metrics['categories'] as $item => $count)
                        <div class="border rounded p-3">
                            <div class="font-medium">{{ $item }}</div>
                            <div class="text-sm text-gray-600">{{ number_format($count) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Materials --}}
        @if(!empty($metrics['materials']))
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Materials</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($metrics['materials'] as $item => $count)
                        <div class="border rounded p-3">
                            <div class="font-medium">{{ $item }}</div>
                            <div class="text-sm text-gray-600">{{ number_format($count) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Brands --}}
        @if(!empty($metrics['brands']))
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Brands</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($metrics['brands'] as $item => $count)
                        <div class="border rounded p-3">
                            <div class="font-medium">{{ $item }}</div>
                            <div class="text-sm text-gray-600">{{ number_format($count) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Custom Tags --}}
        @if(!empty($metrics['custom_tags']))
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Custom Tags</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($metrics['custom_tags'] as $item => $count)
                        <div class="border rounded p-3">
                            <div class="font-medium">{{ $item }}</div>
                            <div class="text-sm text-gray-600">{{ number_format($count) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
