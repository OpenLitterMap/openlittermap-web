<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import axios from 'axios';

const { t } = useI18n();

// Place definitions
const places = [
    {
        id: 'eu',
        center: [50.83957919204694, 4.3740783135703065],
        zoom: 16,
        titleKey: 'EU Parliament, Brussels',
        copyKey:
            "Check out this map of litter outside the EU Parliament - where they debate how to spend billions in 'green' budgets on public health, education, and the environment, but they can't even see what's on their doorstep.",
    },
    {
        id: 'de',
        center: [52.50894381678627, 13.38110858789401],
        zoom: 16,
        titleKey: 'Bundesrat, Berlin',
        copyKey:
            'Check out this map of cigarette litter around the Bundesrat (the Federal government buildings of Germany), where billions of euro of public money is spent on public health, education and the environment.',
    },
];

// State
const mapRefs = reactive({});
const maps = reactive({});
const pointsData = reactive({});
const visibleMaps = reactive({});
const dataTimeRange = reactive({});

// Format date with time and day name
const formatDateTime = (dateStr) => {
    const date = new Date(dateStr);
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const months = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    const dayName = days[date.getDay()];
    const day = date.getDate();
    const month = months[date.getMonth()];
    const year = date.getFullYear();

    // Add ordinal suffix
    const ordinal = (d) => {
        if (d > 3 && d < 21) return 'th';
        switch (d % 10) {
            case 1:
                return 'st';
            case 2:
                return 'nd';
            case 3:
                return 'rd';
            default:
                return 'th';
        }
    };

    // Format time
    let hours = date.getHours();
    const minutes = date.getMinutes();
    const ampm = hours >= 12 ? 'pm' : 'am';
    hours = hours % 12;
    hours = hours ? hours : 12; // 0 should be 12
    const minutesStr = minutes < 10 ? '0' + minutes : minutes;
    const timeStr = hours + ':' + minutesStr + ampm;

    return {
        time: timeStr,
        fullDate: `${dayName} ${day}${ordinal(day)} ${month} ${year}`,
        isSameDay: (otherDate) => {
            const other = new Date(otherDate);
            return (
                date.getDate() === other.getDate() &&
                date.getMonth() === other.getMonth() &&
                date.getFullYear() === other.getFullYear()
            );
        },
    };
};

// Calculate cigarette butts per hour and minute
const calculateRates = (placeId) => {
    if (!pointsData[placeId] || !dataTimeRange[placeId]) return null;

    const { earliest, latest } = dataTimeRange[placeId];
    const totalButts = pointsData[placeId].features.length;

    if (!earliest || !latest || totalButts === 0) return null;

    const timeDiffMs = new Date(latest) - new Date(earliest);
    const hours = timeDiffMs / (1000 * 60 * 60);
    const minutes = timeDiffMs / (1000 * 60);

    if (hours <= 0) return null;

    const earliestFormatted = formatDateTime(earliest);
    const latestFormatted = formatDateTime(latest);

    // Format the date range string
    let dateRangeStr;
    if (earliestFormatted.isSameDay(latest)) {
        // Same day: "From 2pm to 3:30pm on Monday 29th August 2024"
        dateRangeStr = `From ${earliestFormatted.time} to ${latestFormatted.time} on ${earliestFormatted.fullDate}`;
    } else {
        // Different days: show full range
        dateRangeStr = `From ${earliestFormatted.time} on ${earliestFormatted.fullDate} to ${latestFormatted.time} on ${latestFormatted.fullDate}`;
    }

    return {
        perHour: (totalButts / hours).toFixed(1),
        perMinute: (totalButts / minutes).toFixed(2),
        totalHours: hours.toFixed(1),
        dateRange: dateRangeStr,
    };
};

// Build OpenLitterMap URL
function buildOsmUrl(place) {
    return `https://openlittermap.com/global?lat=${place.center[0]}&lon=${place.center[1]}&zoom=${place.zoom}`;
}

// Initialize a single map
async function initMap(mapEl, place) {
    if (!mapEl || maps[place.id]) return;

    // Create map
    const map = L.map(mapEl, {
        center: place.center,
        zoom: place.zoom,
        scrollWheelZoom: false,
    });

    // Add tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors',
    }).addTo(map);

    // Store map reference
    maps[place.id] = map;

    // Remove opacity once loaded
    map.whenReady(() => {
        mapEl.classList.remove('opacity-0');
    });

    // Load smoking data
    await loadSmokingData(place.id);
}

// Load smoking category data for a specific map
async function loadSmokingData(placeId) {
    const map = maps[placeId];
    if (!map) return;

    const bounds = map.getBounds();

    try {
        const response = await axios.get('/api/points', {
            params: {
                zoom: Math.round(map.getZoom()),
                'bbox[left]': bounds.getWest(),
                'bbox[bottom]': bounds.getSouth(),
                'bbox[right]': bounds.getEast(),
                'bbox[top]': bounds.getNorth(),
            },
        });
        // Store the data
        pointsData[placeId] = response.data;

        // Calculate time range
        if (response.data.features && response.data.features.length > 0) {
            const dates = response.data.features
                .map((f) => f.properties.datetime)
                .filter((d) => d)
                .sort();

            dataTimeRange[placeId] = {
                earliest: dates[0],
                latest: dates[dates.length - 1],
            };
        }

        // Clear existing markers
        map.eachLayer((layer) => {
            if (layer instanceof L.CircleMarker) {
                map.removeLayer(layer);
            }
        });

        // Add red markers for smoking litter
        response.data.features.forEach((feature) => {
            const [lon, lat] = feature.geometry.coordinates;
            L.circleMarker([lat, lon], {
                radius: 2,
                fillColor: '#ff0000',
                color: '#ff0000',
                weight: 1,
                opacity: 1,
                fillOpacity: 0.8,
            }).addTo(map);
        });
    } catch (error) {
        console.error(`Error loading data for ${placeId}:`, error);
        if (error.response) {
            console.error('Error response:', error.response.data);
        }
    }
}

// Set up intersection observer for lazy loading
onMounted(() => {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const placeId = entry.target.dataset.placeId;
                    if (!visibleMaps[placeId]) {
                        visibleMaps[placeId] = true;
                        const place = places.find((p) => p.id === placeId);
                        if (place && mapRefs[placeId]) {
                            initMap(mapRefs[placeId], place);
                        }
                    }
                }
            });
        },
        { rootMargin: '100px' }
    );

    // Observe all map containers
    Object.keys(mapRefs).forEach((placeId) => {
        if (mapRefs[placeId]) {
            mapRefs[placeId].dataset.placeId = placeId;
            observer.observe(mapRefs[placeId]);
        }
    });
});
</script>

<template>
    <section
        class="py-20 sm:py-32 bg-gradient-to-br from-slate-950 via-purple-950 to-indigo-950 relative overflow-hidden"
    >
        <!-- Fancy geometric background pattern -->
        <div class="absolute inset-0 opacity-10">
            <svg class="absolute top-0 w-full h-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 800">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path
                            d="M 40 0 L 0 0 0 40"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1"
                            class="text-purple-500"
                        />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Header -->
            <header class="text-center mb-16">
                <h2
                    class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-6 bg-gradient-to-r from-purple-300 via-pink-300 to-indigo-300 bg-clip-text text-transparent"
                >
                    {{ t('Use OpenLitterMap to Tell A Story About The World.') }}
                </h2>
                <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                    {{
                        t(
                            'Maps are powerful tools that help us see and understand the world. ' +
                                'OpenLitterMap empowers you to use your device for its data collection purpose and communicate your story with the world.'
                        )
                    }}
                </p>
            </header>

            <!-- Map sections -->
            <div v-for="(place, i) in places" :key="place.id" class="mb-20 last:mb-0">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <!-- Text column -->
                    <div :class="i % 2 === 0 ? 'order-2 lg:order-1' : 'lg:order-2'">
                        <h3 class="text-2xl font-semibold text-white mb-4 flex items-center">
                            <span
                                class="w-2 h-2 bg-gradient-to-r from-purple-400 to-indigo-400 rounded-full mr-3 animate-pulse"
                            ></span>
                            {{ t(place.titleKey) }}
                        </h3>
                        <p class="text-lg text-purple-100 mb-6">
                            <template v-if="place.id === 'de'">
                                {{ t('Check out this map of cigarette litter around the') }}
                                <a
                                    href="https://en.wikipedia.org/wiki/German_Bundesrat"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-purple-300 hover:text-purple-200 underline"
                                >
                                    {{ t('Bundesrat') }}
                                </a>
                                {{
                                    t(
                                        ' (the Federal government buildings of Germany), where billions of euro of public money is spent on public health, education and the environment.'
                                    )
                                }}
                            </template>
                            <template v-else-if="place.id === 'eu'">
                                {{ t('Check out this map of litter outside the') }}
                                <a
                                    href="https://en.wikipedia.org/wiki/European_Parliament"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-purple-300 hover:text-purple-200 underline"
                                >
                                    {{ t('EU Parliament') }}
                                </a>
                                {{
                                    t(
                                        " - where they debate how to spend billions in 'green' budgets on public health, education, and the environment, but they can't even see what's on their doorstep."
                                    )
                                }}
                            </template>
                            <template v-else>
                                {{ t(place.copyKey) }}
                            </template>
                        </p>

                        <!-- Stats indicators -->
                        <div v-if="pointsData[place.id]" class="mb-6 space-y-2">
                            <div class="text-sm text-purple-300">
                                <i class="fas fa-map-marker-alt mr-2"></i>
                                {{ pointsData[place.id]?.features?.length || 0 }} {{ t('smoking litter points found') }}
                            </div>

                            <!-- Cigarette butt rates -->
                            <div v-if="calculateRates(place.id)" class="text-sm text-purple-300 space-y-1">
                                <div>
                                    <i class="fas fa-clock mr-2"></i>
                                    {{ t('Rate:') }}
                                    <span class="font-semibold">{{ calculateRates(place.id).perHour }}</span>
                                    {{ t('butts/hour') }},
                                    <span class="font-semibold">{{ calculateRates(place.id).perMinute }}</span>
                                    {{ t('butts/minute') }}
                                </div>
                                <div class="text-xs text-purple-400">
                                    {{ calculateRates(place.id).dateRange }}
                                </div>
                            </div>
                        </div>

                        <!-- Fancy button -->
                        <a
                            :href="buildOsmUrl(place)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-medium rounded-xl hover:from-purple-700 hover:to-indigo-700 transform hover:scale-105 transition-all duration-300 shadow-lg hover:shadow-purple-500/25"
                        >
                            {{ t('View on OpenLitterMap') }}
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                ></path>
                            </svg>
                        </a>
                    </div>

                    <!-- Map column -->
                    <div :class="i % 2 === 0 ? 'order-1 lg:order-2' : ''" class="relative group">
                        <!-- Fancy border glow effect -->
                        <div
                            class="absolute -inset-1 bg-gradient-to-r from-purple-500 to-indigo-500 rounded-2xl blur-md opacity-50 group-hover:opacity-75 transition duration-500"
                        ></div>

                        <div class="relative">
                            <div
                                :ref="(el) => (mapRefs[place.id] = el)"
                                class="map-container rounded-2xl shadow-2xl ring-2 ring-white/10 overflow-hidden opacity-0 transition-opacity duration-400 transform group-hover:scale-[1.01] transition-transform"
                                :aria-label="`Map of smoking-related litter outside ${place.titleKey}`"
                            ></div>

                            <!-- Map overlay gradient -->
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-purple-900/20 to-transparent rounded-2xl pointer-events-none"
                            ></div>

                            <!-- Loading indicator -->
                            <div
                                v-if="visibleMaps[place.id] && !pointsData[place.id]"
                                class="absolute inset-0 flex items-center justify-center bg-black/50 rounded-2xl"
                            >
                                <div class="text-purple-400">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>
                                    {{ t('Loading smoking data...') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating data particles animation -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="particle particle-1"></div>
            <div class="particle particle-2"></div>
            <div class="particle particle-3"></div>
            <div class="particle particle-4"></div>
        </div>
    </section>
</template>

<style scoped>
/* Animated gradient background */
@keyframes gradient {
    0%,
    100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

.animate-gradient {
    background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
    background-size: 400% 400%;
    animation: gradient 15s ease infinite;
}

/* Map container with proper width and height */
.map-container {
    position: relative;
    width: 100%;
    height: 0;
    padding-bottom: 56.25%; /* 16:9 ratio */
    background: #111;
}

.map-container > :deep(.leaflet-container) {
    position: absolute;
    inset: 0;
}

/* Canvas glow and blend mode */
:deep(canvas) {
    mix-blend-mode: screen;
    filter: drop-shadow(0 0 8px rgba(239, 68, 68, 0.6));
}

/* Improve label contrast on dark maps */
:deep(.leaflet-container) {
    background-color: #111;
    font-weight: 500;
}

:deep(.leaflet-control-attribution) {
    background-color: rgba(0, 0, 0, 0.7);
    color: #ccc;
}

/* Fade in animation */
.transition-opacity {
    transition: opacity 0.4s ease-out;
}

/* Floating data particles */
.particle {
    position: absolute;
    width: 4px;
    height: 4px;
    background: linear-gradient(45deg, rgba(168, 85, 247, 0.8), rgba(99, 102, 241, 0.8));
    border-radius: 50%;
    box-shadow: 0 0 10px rgba(168, 85, 247, 0.5);
    animation: float-data 20s infinite ease-in-out;
}

.particle-1 {
    left: 10%;
    animation-delay: 0s;
    animation-duration: 18s;
}

.particle-2 {
    left: 30%;
    animation-delay: 5s;
    animation-duration: 22s;
}

.particle-3 {
    left: 60%;
    animation-delay: 10s;
    animation-duration: 20s;
}

.particle-4 {
    left: 85%;
    animation-delay: 15s;
    animation-duration: 24s;
}

@keyframes float-data {
    0% {
        transform: translateY(100vh) translateX(0) scale(0);
        opacity: 0;
    }
    10% {
        opacity: 1;
        transform: translateY(90vh) translateX(10px) scale(1);
    }
    90% {
        opacity: 1;
        transform: translateY(10vh) translateX(-10px) scale(1);
    }
    100% {
        transform: translateY(0) translateX(0) scale(0);
        opacity: 0;
    }
}

/* Pulse animation */
@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s ease-in-out infinite;
}

/* Respect reduced motion preference */
@media (prefers-reduced-motion: reduce) {
    .transition-opacity {
        transition: none;
    }

    .opacity-0 {
        opacity: 1;
    }

    .particle {
        animation: none;
    }

    .animate-pulse {
        animation: none;
    }
}

:deep(.leaflet-container) {
    background-color: #111;
}
</style>
