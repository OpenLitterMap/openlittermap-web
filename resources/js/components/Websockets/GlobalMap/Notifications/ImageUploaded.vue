<template>
    <div class="relative rounded-lg mb-2 p-2 bg-green-500 cursor-pointer">
        <div class="flex items-center gap-2">
            <img
                v-if="payload.countryCode"
                :src="countryFlag(payload.countryCode)"
                :alt="payload.countryCode"
                class="object-fill rounded-full h-4 w-4 lg:h-6 lg:w-6"
            />
            <i v-else class="fa fa-image fa-2x" />
            <div>
                <p class="font-bold">
                    <span v-if="payload.isPickedUp === true">Litter picked up</span>
                    <span v-else-if="payload.isPickedUp === false">Litter uploaded</span>
                    <span v-else>New upload</span>
                </p>
                <p class="text-xs lg:text-sm">
                    <i class="hidden md:inline">{{ cityText }}</i
                    >{{ country }}
                </p>
            </div>
            <div class="event-source">
                <i class="fa" :class="photoSource"></i>
            </div>
        </div>

        <p v-if="payload.user.name || payload.user.username" class="text-sm">
            by
            <span class="font-bold">
                {{ payload.user.name }}
                {{ payload.user.username ? '@' + payload.user.username : '' }}
            </span>
        </p>

        <p v-if="payload.teamName" class="text-sm truncate">
            Team
            <span class="font-bold">{{ payload.teamName }}</span>
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    payload: {
        type: Object,
        default: () => ({}),
    },
});

const country = computed(() => {
    return props.payload.country?.includes('error_') ? null : props.payload.country;
});

const state = computed(() => {
    return props.payload.state?.includes('error_') ? null : props.payload.state;
});

const city = computed(() => {
    return props.payload.city?.includes('error_') ? null : props.payload.city;
});

const cityText = computed(() => {
    let result = [city.value, state.value].filter((t) => t).join(', ');
    if (result && country.value) result += ', ';
    return result;
});

const countryFlag = (countryCode) => {
    if (!countryCode) return '';

    return '/assets/icons/flags/' + countryCode.toLowerCase() + '.png';
};

const photoSource = computed(() => {
    return props.payload.photoSource === 'web' ? 'fa-desktop' : 'fa-mobile large-icon';
});
</script>

<style scoped>
.large-icon {
    font-size: 1rem;
}
</style>
