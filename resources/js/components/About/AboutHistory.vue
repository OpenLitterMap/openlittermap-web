<template>
    <section class="py-20 sm:py-32 bg-gradient-to-b from-gray-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-900 mb-6">
                    {{ $t('about.history.title') }}
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    {{ $t('about.history.subtitle') }}
                </p>
            </div>

            <!-- Timeline -->
            <div class="relative">
                <!-- Vertical line -->
                <div class="absolute left-1/2 transform -translate-x-1/2 h-full w-0.5 bg-gray-300"></div>

                <!-- Timeline items -->
                <div class="space-y-12">
                    <div
                        v-for="(milestone, index) in milestones"
                        :key="index"
                        :class="['relative flex items-center', index % 2 === 0 ? 'justify-start' : 'justify-end']"
                    >
                        <!-- Content -->
                        <div
                            :class="[
                                'w-5/12 p-6 rounded-lg shadow-lg',
                                milestone.highlight ? 'bg-blue-600 text-white' : 'bg-white',
                            ]"
                        >
                            <div class="flex items-center mb-2">
                                <span
                                    :class="[
                                        'text-2xl font-bold',
                                        milestone.highlight ? 'text-white' : 'text-blue-600',
                                    ]"
                                >
                                    {{ milestone.year }}
                                </span>
                                <span class="ml-3 text-2xl">{{ milestone.icon }}</span>
                            </div>
                            <h3
                                :class="[
                                    'text-lg font-semibold mb-2',
                                    milestone.highlight ? 'text-white' : 'text-gray-900',
                                ]"
                            >
                                {{ milestone.title }}
                            </h3>
                            <p :class="milestone.highlight ? 'text-blue-100' : 'text-gray-600'">
                                {{ milestone.description }}
                            </p>
                        </div>

                        <!-- Center dot -->
                        <div
                            class="absolute left-1/2 transform -translate-x-1/2 w-4 h-4 bg-blue-600 rounded-full border-4 border-white shadow"
                        ></div>
                    </div>
                </div>

                <!-- Future indicator -->
                <div class="relative flex items-center justify-center mt-12">
                    <div class="w-5/12 text-center">
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-600 to-green-600 rounded-full mb-4"
                        >
                            <span class="text-white text-2xl">🚀</span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $t('about.history.future.title') }}</h3>
                        <p class="text-gray-600">{{ $t('about.history.future.description') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'AboutHistory',
    computed: {
        milestones() {
            return this.$t('about.history.milestones');
        },
    },
};
</script>
