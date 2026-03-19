<template>
    <div class="bg-gray-800 rounded-lg p-4 space-y-4">
        <h3 class="text-white font-semibold text-sm">Filters</h3>

        <!-- Country -->
        <div>
            <label class="block text-xs text-gray-400 mb-1">Country</label>
            <select
                :value="filters.country_id"
                @change="$emit('update-filter', 'country_id', $event.target.value)"
                class="w-full bg-gray-700 text-white text-sm rounded px-3 py-2 border border-gray-600 focus:outline-none focus:border-blue-500"
            >
                <option value="">All countries</option>
                <option v-for="(country, id) in countries" :key="id" :value="id">
                    {{ country.country }} ({{ country.total }})
                </option>
            </select>
        </div>

        <!-- Photo ID -->
        <div>
            <label class="block text-xs text-gray-400 mb-1">Photo ID</label>
            <input
                type="number"
                :value="filters.photo_id"
                @input="$emit('update-filter', 'photo_id', $event.target.value)"
                placeholder="e.g. 12345"
                class="w-full bg-gray-700 text-white text-sm rounded px-3 py-2 border border-gray-600 focus:outline-none focus:border-blue-500"
            />
        </div>

        <!-- User ID -->
        <div>
            <label class="block text-xs text-gray-400 mb-1">User ID</label>
            <input
                type="number"
                :value="filters.user_id"
                @input="$emit('update-filter', 'user_id', $event.target.value)"
                placeholder="e.g. 42"
                class="w-full bg-gray-700 text-white text-sm rounded px-3 py-2 border border-gray-600 focus:outline-none focus:border-blue-500"
            />
        </div>

        <!-- Date From -->
        <div>
            <label class="block text-xs text-gray-400 mb-1">Date From</label>
            <input
                type="date"
                :value="filters.date_from"
                @input="$emit('update-filter', 'date_from', $event.target.value)"
                class="w-full bg-gray-700 text-white text-sm rounded px-3 py-2 border border-gray-600 focus:outline-none focus:border-blue-500"
            />
        </div>

        <!-- Date To -->
        <div>
            <label class="block text-xs text-gray-400 mb-1">Date To</label>
            <input
                type="date"
                :value="filters.date_to"
                @input="$emit('update-filter', 'date_to', $event.target.value)"
                class="w-full bg-gray-700 text-white text-sm rounded px-3 py-2 border border-gray-600 focus:outline-none focus:border-blue-500"
            />
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2 pt-2">
            <button
                @click="$emit('apply')"
                class="flex-1 bg-blue-600 text-white text-sm font-medium rounded px-3 py-2 hover:bg-blue-500 transition-colors"
            >
                Apply
            </button>
            <button
                @click="$emit('reset')"
                class="flex-1 bg-gray-600 text-white text-sm font-medium rounded px-3 py-2 hover:bg-gray-500 transition-colors"
            >
                Reset
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    filters: {
        type: Object,
        required: true,
    },
    countries: {
        type: Object,
        default: () => ({}),
    },
});

defineEmits(['update-filter', 'apply', 'reset']);
</script>
