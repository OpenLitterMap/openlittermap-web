<template>
    <div
        @click="$emit('click')"
        class="bg-white rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
    >
        <!-- Header with Rank and Flag -->
        <div class="bg-gradient-to-r from-green-500 to-blue-500 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 backdrop-blur-sm rounded-full w-10 h-10 flex items-center justify-center">
                        <span class="text-white font-bold">#{{ index + 1 }}</span>
                    </div>
                    <h3 class="text-xl font-bold text-white">{{ locationName }}</h3>
                </div>
                <img
                    v-if="location.shortcode"
                    :src="`https://flagcdn.com/48x36/${location.shortcode.toLowerCase()}.png`"
                    :alt="locationName"
                    class="w-10 h-8 object-cover rounded shadow-md"
                    @error="handleFlagError"
                />
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-800">
                        {{ formatNumber(location.total_litter_redis) }}
                    </div>
                    <div class="text-xs text-gray-600">Total Litter</div>
                    <div class="text-xs font-semibold text-green-600">
                        {{ location.percentage_litter || calculatePercentage('litter') }}%
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-800">
                        {{ formatNumber(location.total_photos_redis) }}
                    </div>
                    <div class="text-xs text-gray-600">Photos</div>
                    <div class="text-xs font-semibold text-blue-600">
                        {{ location.percentage_photos || calculatePercentage('photos') }}%
                    </div>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Contributors:</span>
                    <span class="font-semibold">{{ location.total_contributors_redis || 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Avg/User:</span>
                    <span class="font-semibold">{{ location.avg_litter_per_user?.toFixed(1) || 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Updated:</span>
                    <span class="font-semibold">{{ location.updatedAtDiffForHumans || 'Recently' }}</span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mt-4">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div
                        class="bg-gradient-to-r from-green-500 to-blue-500 h-2 rounded-full transition-all duration-500"
                        :style="`width: ${Math.min(location.percentage_litter || calculatePercentage('litter'), 100)}%`"
                    ></div>
                </div>
            </div>

            <!-- Metadata Footer -->
            <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
                <div class="flex justify-between">
                    <span>Created: {{ location.diffForHumans || 'Unknown' }}</span>
                    <span>by {{ location.created_by_name || location.created_by_username || 'Anonymous' }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useWorldStore } from '@/stores/world';

const worldStore = useWorldStore();

const props = defineProps({
    location: {
        type: Object,
        required: true,
    },
    index: {
        type: Number,
        required: true,
    },
    locationType: {
        type: String,
        default: 'country',
    },
});

const emit = defineEmits(['click']);

const locationName = computed(() => {
    return props.location[props.locationType] || props.location.name || 'Unknown';
});

const formatNumber = (num) => {
    if (!num) return '0';
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toLocaleString();
};

const calculatePercentage = (type) => {
    if (type === 'litter' && worldStore.total_litter) {
        return ((props.location.total_litter_redis / worldStore.total_litter) * 100).toFixed(2);
    }
    if (type === 'photos' && worldStore.total_photos) {
        return ((props.location.total_photos_redis / worldStore.total_photos) * 100).toFixed(2);
    }
    return '0.00';
};

const handleFlagError = (e) => {
    e.target.style.display = 'none';
};
</script>
