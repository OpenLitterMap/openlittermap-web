<template>
    <section class="py-20 sm:py-32 bg-blue-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-6">
                    {{ $t('about.howItWorks.title') }}
                </h2>
                <p class="text-xl text-blue-100 max-w-3xl mx-auto">
                    {{ $t('about.howItWorks.subtitle') }}
                </p>
            </div>

            <!-- Steps -->
            <div class="grid md:grid-cols-3 gap-8 mb-16">
                <div v-for="(step, index) in steps" :key="index" class="text-center">
                    <div class="relative mb-8">
                        <div class="bg-white/20 backdrop-blur-sm rounded-2xl p-8 border border-white/30">
                            <div class="text-6xl mb-4">{{ step.emoji }}</div>
                            <div
                                class="absolute -top-4 -right-4 bg-white text-blue-600 w-12 h-12 rounded-full flex items-center justify-center font-bold text-xl"
                            >
                                {{ index + 1 }}
                            </div>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">{{ step.title }}</h3>
                    <p class="text-blue-100">{{ step.description }}</p>
                </div>
            </div>

            <!-- App download section -->
            <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 sm:p-12 border border-white/20">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h3 class="text-2xl font-bold mb-4">{{ $t('about.howItWorks.download.title') }}</h3>
                        <p class="text-blue-100 mb-6">{{ $t('about.howItWorks.download.description') }}</p>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <button
                                @click="ios"
                                class="flex items-center justify-center gap-2 bg-black text-white px-6 py-3 rounded-lg hover:bg-gray-900 transition-colors"
                            >
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.53 4.08zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"
                                    />
                                </svg>
                                {{ $t('about.howItWorks.download.ios') }}
                            </button>
                            <button
                                @click="android"
                                class="flex items-center justify-center gap-2 bg-black text-white px-6 py-3 rounded-lg hover:bg-gray-900 transition-colors"
                            >
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M3 20.5v-17c0-.83.67-1.5 1.5-1.5h15c.83 0 1.5.67 1.5 1.5v17c0 .83-.67 1.5-1.5 1.5h-15c-.83 0-1.5-.67-1.5-1.5zM16.5 8L12 12.5 7.5 8 5 10.5l7 7 7-7-2.5-2.5z"
                                    />
                                </svg>
                                {{ $t('about.howItWorks.download.android') }}
                            </button>
                        </div>
                    </div>
                    <div class="flex justify-center">
                        <div class="relative">
                            <!--                            <img src="/assets/app-mockup.png" alt="OpenLitterMap app" class="max-w-xs w-full" />-->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Final CTA -->
            <div class="text-center mt-16">
                <router-link to="/signup">
                    <button
                        class="bg-white text-blue-600 px-12 py-5 rounded-lg font-bold text-xl hover:bg-gray-100 transition-all transform hover:scale-105 shadow-xl"
                    >
                        {{ $t('about.howItWorks.cta') }}
                    </button>
                </router-link>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'AboutHow',
    data() {
        return {
            steps: [
                {
                    emoji: '📸',
                    title: this.$t('about.howItWorks.step1.title'),
                    description: this.$t('about.howItWorks.step1.description'),
                },
                {
                    emoji: '🏷️',
                    title: this.$t('about.howItWorks.step2.title'),
                    description: this.$t('about.howItWorks.step2.description'),
                },
                {
                    emoji: '📍',
                    title: this.$t('about.howItWorks.step3.title'),
                    description: this.$t('about.howItWorks.step3.description'),
                },
            ],
        };
    },
    methods: {
        android() {
            window.open('https://play.google.com/store/apps/details?id=com.geotech.openlittermap', '_blank');
        },
        ios() {
            window.open('https://apps.apple.com/us/app/openlittermap/id1475982147', '_blank');
        },
    },
};
</script>
