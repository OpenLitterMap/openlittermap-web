<template>
    <section class="py-20 sm:py-32 bg-gray-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-6">
                    {{ $t('about.brands.title') }}
                </h2>
                <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                    {{ $t('about.brands.subtitle') }}
                </p>
            </div>

            <!-- Brand leaderboard visualization -->
            <div class="max-w-4xl mx-auto mb-16">
                <div class="bg-gray-800 rounded-lg p-8">
                    <h3 class="text-2xl font-semibold mb-6">{{ $t('about.brands.leaderboard') }}</h3>

                    <div class="space-y-4">
                        <div v-for="(brand, index) in topBrands" :key="index" class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-semibold">{{ brand.name }}</span>
                                <span class="text-gray-400">{{ brand.count.toLocaleString() }} items</span>
                            </div>
                            <div class="bg-gray-700 rounded-full h-8 relative overflow-hidden">
                                <div
                                    :style="{ width: brand.percentage + '%' }"
                                    class="absolute inset-y-0 left-0 bg-gradient-to-r from-red-600 to-red-500 rounded-full transition-all duration-1000 ease-out"
                                    :class="{ 'animate-fill': isVisible }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <p class="text-gray-400 text-sm mt-6">
                        {{ $t('about.brands.disclaimer') }}
                    </p>
                </div>
            </div>

            <!-- CTA to contribute -->
            <div class="text-center">
                <p class="text-xl mb-8 text-gray-300">
                    {{ $t('about.brands.cta') }}
                </p>
                <router-link to="/signup">
                    <button
                        class="bg-red-600 text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-red-700 transition-colors"
                    >
                        {{ $t('about.brands.button') }}
                    </button>
                </router-link>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'AboutBrands',
    data() {
        return {
            isVisible: false,
            topBrands: [
                { name: 'Coca-Cola', count: 45823, percentage: 85 },
                { name: 'PepsiCo', count: 38291, percentage: 72 },
                { name: 'Nestlé', count: 29384, percentage: 55 },
                { name: 'Unilever', count: 21938, percentage: 41 },
                { name: 'Mondelez', count: 18472, percentage: 35 },
            ],
        };
    },
    mounted() {
        // Trigger animation when component is in view
        setTimeout(() => {
            this.isVisible = true;
        }, 500);
    },
};
</script>

<style scoped>
@keyframes fill {
    from {
        width: 0;
    }
}

.animate-fill {
    animation: fill 1.5s ease-out;
}
</style>
