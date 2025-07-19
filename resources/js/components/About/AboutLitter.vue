<template>
    <section class="hero-section">
        <!-- Background pattern -->
        <div class="hero-bg-pattern"></div>

        <!-- Hero Copy -->
        <div class="hero-copy">
            <h1 class="hero-title">
                {{ $t('Every piece of litter tells a story.') }}
            </h1>
            <p class="hero-subtitle">
                {{
                    $t('Every photo captures valuable information on its location, time, brand, material, and effort.')
                }}
            </p>
        </div>

        <!-- Desktop burst visualization -->
        <div v-if="!isMobile" class="burst-container">
            <!-- Central image -->
            <div class="central-image">
                <div class="image-glow"></div>
                <div class="image-wrapper">
                    <img
                        src="/resources/js/assets/images/can.png"
                        :alt="$t('Example of litter found and documented')"
                        class="litter-image"
                        loading="eager"
                    />
                </div>
            </div>

            <!-- Data cards with lines -->
            <template v-for="(field, index) in dataFields" :key="field.key">
                <!-- Burst line -->
                <div
                    class="burst-line"
                    :style="{
                        '--index': index,
                        '--total': dataFields.length,
                    }"
                >
                    <div class="line-inner">
                        <div class="line-flow"></div>
                    </div>
                    <div class="line-dot"></div>
                </div>

                <!-- Data card -->
                <div
                    class="burst-card data-card"
                    :style="{
                        '--index': index,
                        '--total': dataFields.length,
                        'transition-delay': `${index * 0.1}s`,
                        '--offset-x': field.offsetX || '0',
                        width: field.cardWidth || '16rem',
                    }"
                >
                    <div class="card-icon" :aria-label="field.label">
                        {{ field.emoji }}
                    </div>
                    <div class="card-text">
                        <h4 class="card-label">{{ field.label }}</h4>
                        <p class="card-value">{{ field.value }}</p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Mobile list view -->
        <ul v-else class="mobile-list">
            <li v-for="field in dataFields" :key="field.key" class="data-row">
                <div class="row-icon" :aria-label="field.label">
                    {{ field.emoji }}
                </div>
                <div class="row-text">
                    <h4 class="row-label">{{ field.label }}</h4>
                    <p class="row-value">{{ field.value }}</p>
                </div>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

// Window size handling (SSR-safe)
const windowWidth = ref(768);
const isMobile = computed(() => windowWidth.value < 768);

const updateWindowWidth = () => {
    if (typeof window !== 'undefined') {
        windowWidth.value = window.innerWidth;
    }
};

onMounted(() => {
    updateWindowWidth();
    window.addEventListener('resize', updateWindowWidth);
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateWindowWidth);
    }
});

// Data fields
const dataFields = ref([
    {
        key: 'location',
        emoji: '📍',
        label: t('Location'),
        value: '51.8969° N, 8.4689° W',
        offsetX: '3em',
        cardWidth: '18rem',
    },
    {
        key: 'address',
        emoji: '🏛️',
        label: t('Address'),
        value: 'The Lough, Cork City, Ireland',
        cardWidth: '20rem',
    },
    {
        key: 'time',
        emoji: '🕐',
        label: t('Time'),
        value: 'Jan 15, 2025 14:32:18',
        cardWidth: '16rem',
    },
    {
        key: 'object',
        emoji: '🥫',
        label: t('Object'),
        value: t('Aluminum Can'),
    },
    {
        key: 'brand',
        emoji: '🏷️',
        label: t('Brand'),
        value: 'Coca-Cola Original',
        offsetX: '-2em',
        cardWidth: '15rem',
    },
    {
        key: 'material',
        emoji: '♻️',
        label: t('Material'),
        value: t('Aluminum, Paint, Plastic'),
        cardWidth: '18rem',
    },
    {
        key: 'user',
        emoji: '👤',
        label: t('Logged by'),
        value: 'User #42069',
        cardWidth: '12rem',
    },
    {
        key: 'condition',
        emoji: '📊',
        label: t('Condition'),
        value: t('Crushed, Weathered'),
        cardWidth: '15rem',
    },
]);
</script>

<style scoped>
/* Base styles */
.hero-section {
    @apply relative min-h-screen lg:min-h-[80vh];
    @apply bg-gradient-to-br from-slate-900 via-blue-900 to-blue-800;
    @apply flex flex-col items-center justify-center;
    @apply py-12 px-4 sm:px-6 lg:px-8;
    @apply overflow-hidden;
}

/* Optimized background pattern */
.hero-bg-pattern {
    @apply absolute inset-0 opacity-20 pointer-events-none;
    background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpattern id='dots' x='0' y='0' width='40' height='40' patternUnits='userSpaceOnUse'%3E%3Ccircle cx='20' cy='20' r='1' fill='%2360A5FA'/%3E%3C/pattern%3E%3Crect width='100%25' height='100%25' fill='url(%23dots)'/%3E%3C/svg%3E");
}

/* Hero copy - REDUCED BOTTOM MARGIN */
.hero-copy {
    @apply text-center relative z-10; /* was mb-12 lg:mb-16 */
}

.hero-title {
    @apply text-4xl sm:text-5xl lg:text-6xl xl:text-7xl;
    @apply font-bold text-white lg:mb-6;
    @apply leading-tight;
}

.hero-subtitle {
    @apply text-lg sm:text-xl lg:text-2xl;
    @apply text-blue-100 max-w-4xl mx-auto;
    @apply leading-relaxed;
    font-size: 22px;
}

/* Burst container */
.burst-container {
    @apply relative mx-auto;
    width: min(700px, 90vw);
    height: min(700px, 90vw);
}

/* Pull burst upward on large screens */
@screen lg {
    .burst-container {
        margin-top: -1.5rem;
    }
}

@screen sm {
    .hero-subtitle {
        padding: 0;
    }
}

/* Central image */
.central-image {
    @apply absolute left-1/2 top-1/2;
    @apply transform -translate-x-1/2 -translate-y-1/2;
    @apply z-30;
}

.image-glow {
    @apply absolute inset-0;
    @apply bg-blue-500/20 blur-2xl scale-125;
    @apply pointer-events-none;
}

.image-wrapper {
    @apply relative;
    @apply bg-gray-900/60 backdrop-blur-sm;
    @apply p-4 lg:p-6 rounded-2xl;
    @apply border border-gray-700;
}

.litter-image {
    @apply w-32 h-32 lg:w-48 lg:h-48;
    @apply object-contain;
}

/* Burst lines */
.burst-line {
    --angle: calc((360deg / var(--total)) * var(--index));
    --line-length: clamp(8rem, 25vw, 12rem);

    position: absolute;
    inset: 50%;
    transform: translate(-50%, -50%) rotate(var(--angle));
    transform-origin: center;
    pointer-events: none;
    z-index: 10;
}

.line-inner {
    @apply relative;
    width: var(--line-length);
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(96, 165, 250, 0.4) 30%, rgba(96, 165, 250, 0.1) 100%);
}

.line-flow {
    @apply absolute inset-y-0 left-0;
    @apply w-8 h-full;
    background: linear-gradient(90deg, transparent 0%, rgba(96, 165, 250, 0.8) 50%, transparent 100%);
}

.line-dot {
    @apply absolute;
    @apply w-2 h-2 bg-blue-400 rounded-full;
    right: -4px;
    top: 50%;
    transform: translateY(-50%);
}

/* Data cards positioning */
.burst-card {
    --radius: clamp(10rem, 30vw, 15rem);
    position: absolute;
    inset: 50%;
    transform: translate(-50%, -50%) rotate(calc((360deg / var(--total)) * var(--index))) translateX(var(--radius))
        rotate(calc(-1 * (360deg / var(--total)) * var(--index))) translateX(var(--offset-x));
}

/* Data cards */
.data-card {
    @apply flex items-center space-x-3 lg:space-x-4;
    @apply bg-gray-900/90 backdrop-blur-sm;
    @apply rounded-xl px-4 py-4 lg:py-6;
    @apply border border-gray-600;
    @apply opacity-0 animate-fade-in;
    @apply cursor-pointer;
    line-height: 1.6;
    z-index: 20;
}

/* Hover state without transitions or scale */
.data-card:hover {
    @apply border-blue-500/50;
    @apply bg-gray-900/95;
}

/* Icons without transitions */
.card-icon,
.row-icon {
    @apply text-2xl lg:text-3xl;
}

.card-text,
.row-text {
    @apply flex-1;
}

.card-label,
.row-label {
    @apply text-[10px] lg:text-xs;
    @apply text-gray-400 uppercase tracking-wider font-medium;
    @apply mb-0.5;
}

.card-value,
.row-value {
    @apply text-sm lg:text-base;
    @apply text-white font-semibold;
    @apply truncate;
}

/* Mobile list */
.mobile-list {
    @apply space-y-4 max-w-sm mx-auto mt-12;
    @apply list-none;
}

/* Mobile rows - INCREASED PADDING, NO TRANSITIONS */
.data-row {
    @apply flex items-center space-x-4;
    @apply bg-gray-900/90 backdrop-blur-sm;
    @apply rounded-xl px-6 py-5; /* Increased padding */
    @apply border border-gray-600;
    line-height: 1.6; /* Better line spacing */
}

/* Hover state without transitions */
.data-row:hover {
    @apply border-blue-500/50;
    @apply bg-gray-900/95;
}

/* Summary section */
.summary-section {
    @apply max-w-4xl mx-auto mt-16 text-center;
}

.summary-card {
    @apply bg-gray-900/60 backdrop-blur-xl;
    @apply rounded-2xl border border-gray-600;
    @apply p-6 lg:p-8 mb-8 lg:mb-12;
}

.summary-highlight {
    @apply text-lg lg:text-xl text-blue-200 mb-2 lg:mb-4;
}

.summary-text {
    @apply text-sm lg:text-base text-gray-300;
}

.summary-multiplier {
    @apply text-xl lg:text-2xl text-blue-200;
    @apply mb-6 lg:mb-8;
    @apply leading-relaxed;
}

/* CTA Button */
.cta-container {
    @apply text-center mt-8;
}

.cta-button {
    @apply relative;
    @apply bg-gradient-to-r from-blue-600 to-blue-500;
    @apply px-8 lg:px-10 py-4 lg:py-5;
    @apply rounded-xl;
    @apply text-white font-bold;
    @apply transform transition-all duration-200;
    @apply shadow-2xl;
}

.cta-button:hover {
    @apply from-blue-500 to-blue-400;
    @apply scale-105;
}

.cta-button:focus {
    @apply outline-none ring-4 ring-blue-500/50;
}

.cta-text {
    @apply block text-base lg:text-lg;
}

.cta-helper {
    @apply block text-sm opacity-90 mt-1;
    @apply font-normal;
}

/* Animations */
@keyframes fade-in {
    from {
        opacity: 0;
        transform: inherit scale(0.8);
    }
    to {
        opacity: 1;
        transform: inherit scale(1);
    }
}

.animate-fade-in {
    animation: fade-in 0.5s ease-out forwards;
}

/* Respect motion preferences */
@media (prefers-reduced-motion: no-preference) {
    .line-flow {
        animation: flow 3s linear infinite;
    }

    .line-dot {
        @apply animate-pulse;
    }
}

@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

@keyframes flow {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(calc(var(--line-length) + 100%));
    }
}
</style>
