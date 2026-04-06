<template>
    <div class="space-y-4">
        <h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Filters</h3>

        <!-- Status -->
        <div>
            <label class="text-xs text-gray-300 block mb-1.5">Status</label>
            <div class="space-y-1">
                <button
                    v-for="opt in statusOptions"
                    :key="opt.value"
                    @click="$emit('update-status', opt.value)"
                    class="w-full text-left px-3 py-1.5 text-sm rounded transition-colors"
                    :class="status === opt.value
                        ? 'bg-blue-600 text-white'
                        : 'text-gray-300 hover:bg-gray-700'"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <!-- Date range -->
        <div>
            <label class="text-xs text-gray-300 block mb-1.5">Date From</label>
            <input
                type="date"
                :value="dateFrom"
                @input="$emit('update-date-from', $event.target.value)"
                class="w-full px-2 py-1.5 text-sm bg-gray-700 border border-gray-600 rounded text-white focus:outline-none focus:border-blue-500"
            />
        </div>

        <div>
            <label class="text-xs text-gray-300 block mb-1.5">Date To</label>
            <input
                type="date"
                :value="dateTo"
                @input="$emit('update-date-to', $event.target.value)"
                class="w-full px-2 py-1.5 text-sm bg-gray-700 border border-gray-600 rounded text-white focus:outline-none focus:border-blue-500"
            />
        </div>

        <!-- Reset -->
        <button
            @click="$emit('reset')"
            class="w-full px-3 py-1.5 text-sm text-gray-300 hover:text-white border border-gray-600 rounded hover:border-gray-500 transition-colors"
        >
            Reset Filters
        </button>
    </div>
</template>

<script setup>
defineProps({
    status: {
        type: String,
        default: 'pending',
    },
    dateFrom: {
        type: String,
        default: '',
    },
    dateTo: {
        type: String,
        default: '',
    },
});

defineEmits(['update-status', 'update-date-from', 'update-date-to', 'reset']);

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'all', label: 'All' },
];
</script>
