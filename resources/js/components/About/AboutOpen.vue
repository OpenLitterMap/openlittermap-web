<template>
    <section class="py-20 sm:py-32 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-900 mb-6">
                    {{ $t('about.openSystems.title') }}
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    {{ $t('about.openSystems.subtitle') }}
                </p>
            </div>

            <!-- Principles grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-8 mb-16">
                <div v-for="(principle, index) in principles" :key="index" class="text-center group">
                    <div class="mb-6 transform transition-transform group-hover:scale-110">
                        <div
                            class="w-20 h-20 bg-gradient-to-br from-blue-500 to-green-500 rounded-full flex items-center justify-center mx-auto text-3xl text-white"
                        >
                            {{ principle.icon }}
                        </div>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">{{ principle.title }}</h3>
                    <p class="text-gray-600">{{ principle.description }}</p>
                </div>
            </div>

            <!-- Open Data visualization -->
            <div class="bg-gray-50 rounded-2xl p-8 sm:p-12">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h3 class="text-2xl font-bold mb-6">{{ $t('about.openSystems.openData.title') }}</h3>
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <div class="w-6 h-6 bg-green-500 rounded-full mr-3"></div>
                                <p class="text-gray-700">{{ $t('about.openSystems.openData.point1') }}</p>
                            </div>
                            <div class="flex items-center">
                                <div class="w-6 h-6 bg-green-500 rounded-full mr-3"></div>
                                <p class="text-gray-700">{{ $t('about.openSystems.openData.point2') }}</p>
                            </div>
                            <div class="flex items-center">
                                <div class="w-6 h-6 bg-green-500 rounded-full mr-3"></div>
                                <p class="text-gray-700">{{ $t('about.openSystems.openData.point3') }}</p>
                            </div>
                            <div class="flex items-center">
                                <div class="w-6 h-6 bg-green-500 rounded-full mr-3"></div>
                                <p class="text-gray-700">{{ $t('about.openSystems.openData.point4') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="relative">
                        <div class="bg-white rounded-lg shadow-xl p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold">{{ $t('about.openSystems.openData.download') }}</h4>
                                <button class="text-blue-600 hover:text-blue-700">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <div class="space-y-2 text-sm text-gray-600">
                                <p>Format: CSV, JSON, GeoJSON</p>
                                <p>License: CC0 1.0 Universal</p>
                                <p>Last updated: {{ new Date().toLocaleDateString() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'AboutOpen',
    data() {
        return {
            principles: [
                {
                    icon: '🤝',
                    title: this.$t('about.openSystems.principles.trust.title'),
                    description: this.$t('about.openSystems.principles.trust.description'),
                },
                {
                    icon: '🧠',
                    title: this.$t('about.openSystems.principles.understanding.title'),
                    description: this.$t('about.openSystems.principles.understanding.description'),
                },
                {
                    icon: '✋',
                    title: this.$t('about.openSystems.principles.participation.title'),
                    description: this.$t('about.openSystems.principles.participation.description'),
                },
                {
                    icon: '🌍',
                    title: this.$t('about.openSystems.principles.inclusion.title'),
                    description: this.$t('about.openSystems.principles.inclusion.description'),
                },
                {
                    icon: '🔄',
                    title: this.$t('about.openSystems.principles.reproducibility.title'),
                    description: this.$t('about.openSystems.principles.reproducibility.description'),
                },
            ],
        };
    },
};
</script>
