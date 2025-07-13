<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <section class="py-16 sm:py-24 bg-gradient-to-b from-black to-red-950">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-8 items-center">
                <!-- Left Column: Text -->
                <div class="text-white">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold mb-6 mr-6">
                        {{ t('Trillions of cigarette butts leech toxic chemicals into the environment.') }}
                    </h2>
                    <p class="text-lg sm:text-xl text-red-100">
                        {{
                            t(
                                'They poison water and release nicotine, arsenic, and microplastics into plants and animals.'
                            )
                        }}
                    </p>
                </div>

                <!-- Right Column: Image -->
                <div class="relative">
                    <img
                        src="/resources/js/assets/images/butts.png"
                        alt="Cigarette butts"
                        class="rounded-2xl shadow-2xl w-full h-[400px] object-cover"
                        loading="lazy"
                    />
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Minimal styles - leveraging Tailwind classes */
</style>
