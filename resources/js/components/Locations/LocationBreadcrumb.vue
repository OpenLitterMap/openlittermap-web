<template>
    <nav v-if="items.length > 0" class="mb-6">
        <ol class="flex items-center gap-1 text-sm">
            <li v-for="(crumb, i) in items" :key="i" class="flex items-center">
                <span v-if="i > 0" class="mx-2 text-white/30">/</span>

                <!-- Last item (current page) — not a link -->
                <span v-if="i === items.length - 1" class="text-white font-semibold">
                    {{ crumb.name }}
                </span>

                <!-- Navigable items -->
                <router-link v-else :to="routeFor(crumb)" class="text-white/60 hover:text-white transition-colors">
                    {{ crumb.name }}
                </router-link>
            </li>
        </ol>
    </nav>
</template>

<script setup>
defineProps({
    items: { type: Array, default: () => [] },
});

function routeFor(crumb) {
    if (crumb.type === 'global' || !crumb.id) {
        return { name: 'locations.global' };
    }
    return { name: `locations.${crumb.type}`, params: { type: crumb.type, id: crumb.id } };
}
</script>
