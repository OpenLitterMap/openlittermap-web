<template>
    <div class="min-h-screen bg-gradient-to-b from-slate-900 via-blue-900 to-emerald-900 text-white">
        <div class="max-w-5xl mx-auto px-5 md:px-8 py-8 md:py-12">
            <!-- Hero -->
            <header class="text-center max-w-2xl mx-auto mb-6">
                <h1 class="text-4xl md:text-5xl font-bold leading-tight">The OpenLitterMap Story</h1>
            </header>

            <!-- Controls -->
            <div class="mb-5 rounded-2xl bg-white/5 border border-white/10 p-3 md:p-4">
                <div class="flex flex-col md:flex-row md:items-center gap-3">
                    <!-- Text search -->
                    <div class="relative flex-1">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <circle cx="11" cy="11" r="7" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search milestones…"
                            aria-label="Search milestones"
                            class="w-full h-11 pl-10 pr-9 rounded-xl bg-white/5 border border-white/10 text-white placeholder-white/30 focus:outline-none focus:border-emerald-400/50 focus:bg-white/[0.07] transition"
                        />
                        <button
                            v-if="search"
                            type="button"
                            @click="search = ''"
                            aria-label="Clear search"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white text-lg leading-none"
                        >
                            &times;
                        </button>
                    </div>

                    <!-- Year -->
                    <select
                        v-model="activeYear"
                        aria-label="Filter by year"
                        class="h-11 rounded-xl bg-white/5 border border-white/10 text-white px-3 focus:outline-none focus:border-emerald-400/50 transition"
                    >
                        <option class="bg-slate-800" value="All">All years</option>
                        <option v-for="y in years" :key="y" :value="y" class="bg-slate-800">{{ y }}</option>
                    </select>

                    <!-- Date sort -->
                    <button
                        type="button"
                        @click="toggleSort"
                        class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl bg-white/5 border border-white/10 text-white/80 hover:text-white hover:border-white/20 transition"
                    >
                        {{ sortDir === 'desc' ? 'Newest first' : 'Oldest first' }}
                        <span aria-hidden="true">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
                    </button>
                </div>

                <!-- Category chips / legend -->
                <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-white/10">
                    <button
                        type="button"
                        @click="activeCategory = 'All'"
                        :class="[
                            'rounded-full px-3.5 py-1.5 text-sm font-medium border transition',
                            activeCategory === 'All'
                                ? 'bg-white/10 text-white border-white/25'
                                : 'text-white/50 border-white/10 hover:text-white/80 hover:border-white/20',
                        ]"
                    >
                        All
                    </button>
                    <button
                        v-for="cat in categoriesPresent"
                        :key="cat"
                        type="button"
                        @click="activeCategory = cat"
                        :class="[
                            'inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium border transition',
                            activeCategory === cat
                                ? 'bg-white/10 text-white border-white/25'
                                : 'text-white/50 border-white/10 hover:text-white/80 hover:border-white/20',
                        ]"
                    >
                        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: meta(cat).dot }"></span>
                        {{ cat }}
                    </button>
                </div>
            </div>

            <!-- Results count -->
            <p class="text-white/40 text-sm mb-4">
                Showing {{ filteredEntries.length }} of {{ allEntries.length }} milestones
            </p>

            <!-- Timeline -->
            <ol ref="listEl" class="tl-list">
                <li
                    v-for="(entry, index) in filteredEntries"
                    :key="entry.date + entry.impact"
                    class="tl-item"
                >
                    <span
                        class="tl-node"
                        :class="{ 'tl-node--latest': isLatest(entry) }"
                        :style="{ '--dot': entry.highlight ? '#fcd34d' : meta(entry.category).dot }"
                    ></span>

                    <div v-if="entry.highlight" class="tl-marker">
                        <p class="tl-marker-title">{{ entry.highlight }}</p>
                        <p class="tl-marker-date">{{ formatDate(entry.date) }}</p>
                    </div>

                    <div
                        class="tl-card group rounded-2xl bg-white/5 border border-white/10 p-4 backdrop-blur-sm transition hover:bg-white/[0.08] hover:border-white/20"
                    >
                        <div class="flex items-center gap-3 mb-2">
                            <time class="text-xs font-semibold tracking-wide text-white/45">{{ formatDate(entry.date) }}</time>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                                :class="meta(entry.category).badge"
                            >
                                {{ entry.category }}
                            </span>
                        </div>

                        <p class="text-white/90 leading-relaxed">{{ entry.impact }}</p>

                        <template v-if="entry.url">
                            <router-link
                                v-if="isInternal(entry.url)"
                                :to="entry.url"
                                class="tl-link inline-flex items-center gap-1 mt-2 text-sm font-medium text-emerald-300 hover:text-emerald-200"
                            >
                                Learn more
                                <span aria-hidden="true" class="transition-transform group-hover:translate-x-0.5">&rarr;</span>
                            </router-link>
                            <a
                                v-else
                                :href="entry.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="tl-link inline-flex items-center gap-1 mt-2 text-sm font-medium text-emerald-300 hover:text-emerald-200"
                            >
                                Learn more
                                <span aria-hidden="true" class="transition-transform group-hover:translate-x-0.5">&rarr;</span>
                            </a>
                        </template>
                    </div>
                </li>
            </ol>

            <!-- Empty state -->
            <div v-if="filteredEntries.length === 0" class="text-center py-16">
                <p class="text-white/60">No milestones match your filters.</p>
                <button
                    type="button"
                    @click="resetFilters"
                    class="mt-3 text-sm font-medium text-emerald-300 hover:text-emerald-200 underline underline-offset-2"
                >
                    Clear all filters
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import timelineData from '@/data/timeline.json';

// Category colour system — badge classes are Tailwind literals (JIT-scanned),
// dot is an explicit hex used for the spine node and legend dots.
const CATEGORIES = {
    Milestone: { badge: 'bg-emerald-400/15 text-emerald-200 ring-emerald-400/30', dot: '#34d399' },
    Product: { badge: 'bg-sky-400/15 text-sky-200 ring-sky-400/30', dot: '#38bdf8' },
    Funding: { badge: 'bg-amber-400/15 text-amber-200 ring-amber-400/30', dot: '#fbbf24' },
    Academic: { badge: 'bg-violet-400/15 text-violet-200 ring-violet-400/30', dot: '#a78bfa' },
    Policy: { badge: 'bg-indigo-400/15 text-indigo-200 ring-indigo-400/30', dot: '#818cf8' },
    'EU Presidency 2026': { badge: 'bg-blue-500/15 text-blue-200 ring-blue-400/30', dot: '#60a5fa' },
    Award: { badge: 'bg-rose-400/15 text-rose-200 ring-rose-400/30', dot: '#fb7185' },
    Community: { badge: 'bg-teal-400/15 text-teal-200 ring-teal-400/30', dot: '#2dd4bf' },
    Media: { badge: 'bg-orange-400/15 text-orange-200 ring-orange-400/30', dot: '#fb923c' },
};
const FALLBACK = { badge: 'bg-white/10 text-white/70 ring-white/20', dot: '#94a3b8' };

function meta(category) {
    return CATEGORIES[category] || FALLBACK;
}

// A bare year ("2019") sorts to the end of that year so year-only talks
// appear after that year's specifically-dated releases. A year-month
// ("2008-10") sorts to the start of that month.
function yearOf(date) {
    return date.slice(0, 4);
}

const allEntries = timelineData; // authored oldest → newest in the JSON

// Filter state
const search = ref('');
const activeCategory = ref('All');
const activeYear = ref('All');
const sortDir = ref('desc'); // 'desc' = newest first, 'asc' = oldest first

const years = computed(() => {
    const present = new Set(allEntries.map((e) => yearOf(e.date)));
    return [...present].sort((a, b) => Number(b) - Number(a));
});

const categoriesPresent = computed(() => {
    const present = new Set(allEntries.map((e) => e.category));
    return Object.keys(CATEGORIES).filter((c) => present.has(c));
});

const filteredEntries = computed(() => {
    const term = search.value.trim().toLowerCase();

    const matches = allEntries.filter((e) => {
        if (activeCategory.value !== 'All' && e.category !== activeCategory.value) {
            return false;
        }
        if (activeYear.value !== 'All' && yearOf(e.date) !== activeYear.value) {
            return false;
        }
        if (term && !e.impact.toLowerCase().includes(term)) {
            return false;
        }
        return true;
    });

    // Render strictly in authored order; the toggle only flips direction, so
    // mixed date granularity (year, year-month, full) never disturbs ordering.
    return sortDir.value === 'desc' ? [...matches].reverse() : matches;
});

function toggleSort() {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc';
}

function resetFilters() {
    search.value = '';
    activeCategory.value = 'All';
    activeYear.value = 'All';
}

const latestEntry = allEntries[allEntries.length - 1];
function isLatest(entry) {
    return entry.date === latestEntry.date && entry.impact === latestEntry.impact;
}

function isInternal(url) {
    return typeof url === 'string' && url.startsWith('/');
}

function formatDate(date) {
    if (/^\d{4}$/.test(date)) {
        return date;
    }
    if (/^\d{4}-\d{2}$/.test(date)) {
        const yearMonth = new Date(`${date}-01T00:00:00`);
        return Number.isNaN(yearMonth.getTime())
            ? date
            : yearMonth.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
    }
    const parsed = new Date(`${date}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) {
        return date;
    }
    return parsed.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
}

// Scroll-reveal (skipped entirely when the user prefers reduced motion).
const listEl = ref(null);
let observer = null;
const reduceMotion =
    typeof window !== 'undefined' &&
    window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function observeItems() {
    if (!observer || !listEl.value) {
        return;
    }
    listEl.value.querySelectorAll('.tl-item:not(.is-visible)').forEach((el) => observer.observe(el));
}

onMounted(() => {
    if (reduceMotion || typeof IntersectionObserver === 'undefined') {
        return;
    }
    observer = new IntersectionObserver(
        (records) => {
            records.forEach((record) => {
                if (record.isIntersecting) {
                    record.target.classList.add('is-visible');
                    observer.unobserve(record.target);
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -10% 0px' }
    );
    observeItems();
});

watch(filteredEntries, async () => {
    await nextTick();
    observeItems();
});

onBeforeUnmount(() => {
    if (observer) {
        observer.disconnect();
    }
});
</script>

<style scoped>
.tl-list {
    position: relative;
    list-style: none;
    margin: 0;
    padding: 0;
}

/* The spine */
.tl-list::before {
    content: '';
    position: absolute;
    top: 0.5rem;
    bottom: 0.5rem;
    left: 20px;
    width: 2px;
    background: linear-gradient(to bottom, transparent, #34d399 6%, #38bdf8 50%, #34d399 94%, transparent);
    opacity: 0.55;
}

.tl-item {
    position: relative;
    padding-left: 56px;
    padding-bottom: 1rem;
    opacity: 0;
    transform: translateY(24px);
    transition:
        opacity 0.6s ease,
        transform 0.6s ease;
}

.tl-item:last-child {
    padding-bottom: 0;
}

.tl-item.is-visible {
    opacity: 1;
    transform: none;
}

.tl-node {
    position: absolute;
    left: 20px;
    top: 0.5rem;
    width: 14px;
    height: 14px;
    transform: translateX(-50%);
    border-radius: 9999px;
    background: var(--dot, #34d399);
    box-shadow:
        0 0 0 4px rgba(255, 255, 255, 0.06),
        0 0 16px var(--dot, #34d399);
}

.tl-node--latest::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    animation: tl-ping 2.6s ease-out infinite;
}

@keyframes tl-ping {
    0% {
        box-shadow: 0 0 0 1px var(--dot, #34d399);
    }
    70%,
    100% {
        box-shadow: 0 0 0 12px transparent;
    }
}

.tl-card {
    display: inline-block;
    width: 100%;
    text-align: left;
}

/* Gold "important date" marker (shown across the spine on desktop) */
.tl-marker {
    margin-bottom: 0.4rem;
}

.tl-marker-title {
    color: #fcd34d;
    font-weight: 700;
    line-height: 1.2;
    font-size: 1.05rem;
}

.tl-marker-date {
    margin-top: 0.1rem;
    color: rgba(251, 191, 36, 0.75);
    font-weight: 600;
    font-size: 0.8rem;
    letter-spacing: 0.02em;
}

/* Alternating layout on wide screens */
@media (min-width: 1024px) {
    .tl-list::before {
        left: 50%;
    }

    .tl-item {
        width: 50%;
        padding-left: 0;
    }

    .tl-item:nth-child(odd) {
        left: 0;
        padding-right: 48px;
        text-align: right;
    }

    .tl-item:nth-child(even) {
        left: 50%;
        padding-left: 48px;
    }

    .tl-card {
        max-width: 26rem;
    }

    .tl-node {
        transform: none;
    }

    .tl-item:nth-child(odd) .tl-node {
        left: auto;
        right: -7px;
    }

    .tl-item:nth-child(even) .tl-node {
        left: -7px;
    }

    /* Gold marker sits across the spine, opposite the story card */
    .tl-marker {
        position: absolute;
        top: 0.15rem;
        width: 100%;
        margin-bottom: 0;
    }

    .tl-item:nth-child(odd) .tl-marker {
        left: 100%;
        padding-left: 48px;
        text-align: left;
    }

    .tl-item:nth-child(even) .tl-marker {
        right: 100%;
        padding-right: 48px;
        text-align: right;
    }
}

@media (prefers-reduced-motion: reduce) {
    .tl-item {
        opacity: 1;
        transform: none;
        transition: none;
    }

    .tl-node--latest::after {
        animation: none;
    }
}
</style>
