<template>
    <div>
        <nav class="flex flex-wrap justify-around items-center">
            <div class="m-4">
                <LitterChart :width="300" :height="300" :litter="litter_data" />
            </div>
            <div class="m-4" v-if="total_brands">
                <LitterBrands :width="300" :height="300" :brands="brands_data" />
            </div>
        </nav>
    </div>
</template>

<script setup>
import LitterChart from './LitterChart.vue';
import LitterBrands from './LitterBrands.vue';

const props = defineProps({
    litter_data: {
        type: Object,
        required: true,
    },
    brands_data: {
        type: Object,
        required: false,
    },
    total_brands: {
        type: Boolean,
        default: false,
    },
});
</script>
