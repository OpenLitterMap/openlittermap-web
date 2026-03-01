<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted } from 'vue';

const { t } = useI18n();

// Timeline data
const timeline = [
    {
        year: '2008',
        title: 'Introduced to GIS',
        description: 'The idea for a real-world data collection game was born',
        icon: '🗺️',
        color: 'from-blue-500 to-indigo-500',
    },
    {
        year: '2011',
        title: 'Undergraduate Dissertation',
        description: 'Undergraduate dissertation on walkability and mapping anti-social behaviour',
        icon: '📚',
        color: 'from-indigo-500 to-purple-500',
    },
    {
        year: '2012',
        title: 'Inspired by tropics',
        description: 'Worked as a divemaster in Koh Tao where I was inspired to develop citizen science',
        icon: '🌴',
        color: 'from-green-500 to-emerald-500',
    },
    {
        year: '2013',
        title: 'Masters in GIS. Introduced to OpenStreetMap',
        description:
            'Introduced to OpenStreetMap and inspired its active community, the idea for OpenLitterMap was born as its real-time data collection layer.',
        icon: '🌍',
        color: 'from-emerald-500 to-teal-500',
    },
    {
        year: '2015',
        title: 'Finished 2nd MSc in Coastal & Marine Environments',
        description:
            'Did a second masters to review the literature on plastic pollution and citizen science ending with a 1st class honours dissertation',
        icon: '🎓',
        color: 'from-purple-500 to-pink-500',
    },
    {
        year: '2016',
        title: 'Started teaching myself how to code',
        description:
            'After watching many video tutorials I wrote stacks of code with pen & paper and started building OpenLitterMap from scratch',
        icon: '💻',
        color: 'from-pink-500 to-red-500',
    },
    {
        year: '2017',
        title: '15th April 2017 - Launched OpenLitterMap.com',
        description:
            'After months of study and development, OpenLitterMap.com goes live to the world and starts accepting uploads globally',
        icon: '🚀',
        color: 'from-red-500 to-orange-500',
    },
    {
        year: '2018',
        title: 'Started working as dev',
        description:
            'Founder gets 1st job as a software engineer where he begins to develop his skills, continues to develop OLM evenings and weekends',
        icon: '👨‍💻',
        color: 'from-orange-500 to-amber-500',
    },
    {
        year: '2019',
        title: 'Features & Refactoring',
        description: 'Continuous improvement and optimization',
        icon: '🔧',
        color: 'from-amber-500 to-yellow-500',
    },
    {
        year: '2020',
        title: 'Launched iOS & Android',
        description: 'Mobile apps bring mapping to everyone',
        icon: '📱',
        color: 'from-yellow-500 to-lime-500',
    },
    {
        year: '2021',
        title: '1st crypto funding',
        description: 'Pioneering new funding models',
        icon: '₿',
        color: 'from-lime-500 to-green-500',
    },
    {
        year: '2022',
        title: 'Features & Refactoring',
        description: 'Building robust infrastructure',
        icon: '🏗️',
        color: 'from-green-500 to-emerald-500',
    },
    {
        year: '2023',
        title: 'Features & Refactoring',
        description: 'Scaling for global impact',
        icon: '🌐',
        color: 'from-emerald-500 to-cyan-500',
    },
    {
        year: '2024',
        title: 'Features & Refactoring',
        description: 'Preparing for the next leap',
        icon: '⚡',
        color: 'from-cyan-500 to-blue-500',
    },
    {
        year: '2025',
        title: 'AI transformation',
        description:
            'A major AI-powered transformation of OLM begins. Years of hand-coded work begins to be refactored and improved with AI.',
        icon: '🤖',
        color: 'from-violet-500 to-purple-500',
    },
    {
        year: '2026',
        title: 'Earn Littercoin',
        description: 'The worlds first climate currency',
        icon: '🪙',
        color: 'from-green-500 to-orange-500',
    },
];

// Animation state
const visibleItems = ref(new Set());

// Intersection observer for timeline animations
onMounted(() => {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const index = entry.target.dataset.index;
                    visibleItems.value.add(parseInt(index));
                }
            });
        },
        { threshold: 0.1 }
    );

    document.querySelectorAll('.timeline-item').forEach((el) => {
        observer.observe(el);
    });
});
</script>

<template>
    <section
        class="py-16 sm:py-20 bg-gradient-to-br from-slate-950 via-indigo-950 to-purple-950 relative overflow-hidden"
    >
        <!-- Timeline background pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="absolute top-0 w-full h-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 800">
                <defs>
                    <pattern id="dots" width="40" height="40" patternUnits="userSpaceOnUse">
                        <circle cx="20" cy="20" r="1" fill="currentColor" class="text-indigo-500" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#dots)" />
            </svg>

            <!-- Gradient overlay -->
            <div class="absolute inset-0 bg-gradient-to-b from-transparent via-black/30 to-black/60"></div>
        </div>

        <div class="w-full mx-auto relative z-10 px-8 md:px-16 lg:px-20 xl:px-28">
            <!-- Header -->
            <div class="text-center mb-16">
                <h2 class="text-5xl sm:text-6xl lg:text-7xl font-black mb-6 leading-tight">
                    <span
                        class="bg-gradient-to-r from-indigo-200 via-purple-200 to-pink-200 bg-clip-text text-transparent"
                    >
                        {{ t('17+ Years') }}
                    </span>
                    <br />
                    <span class="text-white/90 text-4xl sm:text-5xl lg:text-6xl">
                        {{ t('of Research + Development') }}
                    </span>
                </h2>
            </div>

            <!-- Timeline -->
            <div class="relative max-w-6xl mx-auto">
                <!-- Central line -->
                <div
                    class="absolute left-1/2 transform -translate-x-1/2 w-1 h-full bg-gradient-to-b from-indigo-500/20 via-purple-500/20 to-pink-500/20"
                ></div>

                <!-- Timeline items -->
                <div class="space-y-12">
                    <div
                        v-for="(item, index) in timeline"
                        :key="item.year"
                        :data-index="index"
                        class="timeline-item relative"
                    >
                        <div
                            :class="[
                                'flex items-center gap-8',
                                index % 2 === 0 ? 'lg:flex-row' : 'lg:flex-row-reverse',
                            ]"
                        >
                            <!-- Content -->
                            <div
                                :class="[
                                    'flex-1 transform transition-all duration-700',
                                    index % 2 === 0 ? 'lg:text-right' : 'lg:text-left',
                                    visibleItems.has(index)
                                        ? 'translate-x-0 opacity-100'
                                        : (index % 2 === 0 ? '-translate-x-10' : 'translate-x-10') + ' opacity-0',
                                ]"
                            >
                                <div :class="index % 2 === 0 ? 'lg:ml-auto' : 'lg:mr-auto'" class="max-w-md">
                                    <div class="relative group">
                                        <!-- Glow effect -->
                                        <div
                                            :class="`absolute -inset-2 bg-gradient-to-r ${item.color} rounded-2xl blur-xl opacity-30 group-hover:opacity-50 transition duration-500`"
                                        ></div>

                                        <!-- Card -->
                                        <div
                                            class="relative bg-gradient-to-br from-slate-900/80 to-purple-900/80 backdrop-blur-xl rounded-2xl p-6 border border-purple-500/20"
                                        >
                                            <div class="text-5xl mb-4">{{ item.icon }}</div>
                                            <h3
                                                :class="`text-2xl font-bold mb-2 bg-gradient-to-r ${item.color} bg-clip-text text-transparent`"
                                            >
                                                {{ t(item.title) }}
                                            </h3>
                                            <p class="text-gray-300">{{ t(item.description) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Center node -->
                            <div
                                :class="[
                                    'relative z-10 transform transition-all duration-700',
                                    visibleItems.has(index) ? 'scale-100 opacity-100' : 'scale-0 opacity-0',
                                ]"
                                :style="{ transitionDelay: `${index * 50}ms` }"
                            >
                                <!-- Year bubble -->
                                <div class="relative group">
                                    <div
                                        :class="`absolute -inset-4 bg-gradient-to-r ${item.color} rounded-full blur-md opacity-50 group-hover:opacity-75 transition duration-300`"
                                    ></div>
                                    <div
                                        class="relative bg-slate-900 rounded-full w-24 h-24 flex items-center justify-center border-4 border-purple-500/30"
                                    >
                                        <span class="text-white font-bold text-lg">{{ item.year }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Spacer for opposite side -->
                            <div class="flex-1 hidden lg:block"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom CTA -->
            <div class="text-center mt-20">
                <h3
                    class="text-3xl sm:text-4xl font-bold mb-6 text-transparent bg-gradient-to-r from-purple-300 to-pink-300 bg-clip-text"
                >
                    {{ t('Help shape its future direction...') }}
                </h3>
                <p class="text-xl text-purple-200/80 max-w-2xl mx-auto">
                    {{ t('Join us as we revolutionize environmental data collection with AI and community power') }}
                </p>
            </div>
        </div>

        <!-- Floating particles -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="history-particle history-particle-1"></div>
            <div class="history-particle history-particle-2"></div>
            <div class="history-particle history-particle-3"></div>
        </div>
    </section>
</template>

<style scoped>
/* History particles */
.history-particle {
    position: absolute;
    width: 4px;
    height: 4px;
    background: linear-gradient(45deg, rgba(99, 102, 241, 0.8), rgba(168, 85, 247, 0.8));
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);
    animation: float-up 40s infinite ease-in-out;
}

.history-particle::before {
    content: '';
    position: absolute;
    inset: -8px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%);
    border-radius: 50%;
}

.history-particle-1 {
    left: 20%;
    animation-delay: 0s;
}

.history-particle-2 {
    left: 50%;
    animation-delay: 13s;
}

.history-particle-3 {
    left: 80%;
    animation-delay: 26s;
}

@keyframes float-up {
    0% {
        transform: translateY(110vh) translateX(0) scale(0);
        opacity: 0;
    }
    10% {
        opacity: 1;
        transform: translateY(90vh) translateX(20px) scale(1);
    }
    90% {
        opacity: 1;
        transform: translateY(10vh) translateX(-20px) scale(1);
    }
    100% {
        transform: translateY(-10vh) translateX(0) scale(0);
        opacity: 0;
    }
}

/* Mobile adjustments */
@media (max-width: 1024px) {
    .timeline-item > div {
        flex-direction: column !important;
    }

    .timeline-item .flex-1 {
        text-align: center !important;
    }
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
    .history-particle {
        animation: none;
    }

    .transform {
        transform: none !important;
    }
}
</style>
