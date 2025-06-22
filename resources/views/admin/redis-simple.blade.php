{{-- resources/views/admin/redis-simple.blade.php --}}
@extends('app')

@section('content')
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold mb-6">Redis Data Viewer</h1>

        {{-- Server Stats --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Server Statistics</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($stats as $key => $value)
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $value }}</div>
                        <div class="text-sm text-gray-600">{{ Str::title(str_replace('_', ' ', $key)) }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Global Metrics --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Global Metrics</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($global as $type => $metrics)
                    <div class="border rounded p-4">
                        <h3 class="font-semibold capitalize mb-2">{{ $type }}</h3>
                        <p class="text-sm">Total: {{ number_format($metrics['total']) }}</p>
                        <p class="text-sm">Unique: {{ $metrics['unique'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Top Users --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Top Users</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                    <tr class="border-b">
                        <th class="px-4 py-2 text-left">User</th>
                        <th class="px-4 py-2 text-right">Uploads</th>
                        <th class="px-4 py-2 text-right">XP</th>
                        <th class="px-4 py-2 text-right">Streak</th>
                        <th class="px-4 py-2 text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($users as $user)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <div>{{ $user['name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $user['email'] }}</div>
                            </td>
                            <td class="px-4 py-2 text-right">{{ number_format($user['uploads']) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($user['xp']) }}</td>
                            <td class="px-4 py-2 text-right">{{ $user['streak'] }}</td>
                            <td class="px-4 py-2 text-center">
                                <a href="{{ route('admin.redis.user', $user['id']) }}"
                                   class="text-blue-600 hover:text-blue-800 text-sm">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Time Series --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Monthly Activity</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                    <tr class="border-b">
                        <th class="px-4 py-2 text-left">Month</th>
                        <th class="px-4 py-2 text-right">Photos</th>
                        <th class="px-4 py-2 text-right">XP</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($timeSeries as $month => $data)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2">{{ $month }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($data['photos']) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($data['xp']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
