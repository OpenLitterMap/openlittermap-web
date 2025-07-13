<template>
    <section class="py-20 sm:py-32 bg-gradient-to-br from-blue-50 to-green-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-900 mb-6">
                    {{ $t('about.technology.title') }}
                </h2>
                <div class="text-6xl sm:text-8xl font-bold text-blue-600 mb-4">5+ billion</div>
                <p class="text-xl text-gray-700 max-w-3xl mx-auto">
                    {{ $t('about.technology.subtitle') }}
                </p>
            </div>

            <!-- Comparison grid -->
            <div class="grid md:grid-cols-2 gap-8 max-w-5xl mx-auto">
                <!-- Social Media Side -->
                <div class="bg-gray-100 rounded-2xl p-8 relative">
                    <div
                        class="absolute -top-4 -right-4 bg-gray-500 text-white rounded-full w-12 h-12 flex items-center justify-center text-2xl"
                    >
                        👎
                    </div>
                    <h3 class="text-2xl font-bold mb-6 text-gray-700">
                        {{ $t('about.technology.socialMedia.title') }}
                    </h3>
                    <ul class="space-y-4">
                        <li v-for="(item, index) in socialMediaPoints" :key="index" class="flex items-start">
                            <span class="text-red-500 mr-3">✗</span>
                            <span class="text-gray-600">{{ item }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Citizen Science Side -->
                <div class="bg-green-100 rounded-2xl p-8 relative">
                    <div
                        class="absolute -top-4 -right-4 bg-green-500 text-white rounded-full w-12 h-12 flex items-center justify-center text-2xl"
                    >
                        👍
                    </div>
                    <h3 class="text-2xl font-bold mb-6 text-gray-900">
                        {{ $t('about.technology.citizenScience.title') }}
                    </h3>
                    <ul class="space-y-4">
                        <li v-for="(item, index) in citizenSciencePoints" :key="index" class="flex items-start">
                            <span class="text-green-500 mr-3">✓</span>
                            <span class="text-gray-700">{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Stats -->
            <div class="mt-16 text-center">
                <div class="inline-grid grid-cols-1 sm:grid-cols-3 gap-8 sm:gap-16">
                    <div>
                        <div class="text-4xl font-bold text-blue-600 mb-2">30 sec</div>
                        <p class="text-gray-600">{{ $t('about.technology.stat1') }}</p>
                    </div>
                    <div>
                        <div class="text-4xl font-bold text-blue-600 mb-2">150+</div>
                        <p class="text-gray-600">{{ $t('about.technology.stat2') }}</p>
                    </div>
                    <div>
                        <div class="text-4xl font-bold text-blue-600 mb-2">∞</div>
                        <p class="text-gray-600">{{ $t('about.technology.stat3') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'AboutTechnology',
    computed: {
        socialMediaPoints() {
            return this.$t('about.technology.socialMedia.points');
        },
        citizenSciencePoints() {
            return this.$t('about.technology.citizenScience.points');
        },
    },
};
</script>
