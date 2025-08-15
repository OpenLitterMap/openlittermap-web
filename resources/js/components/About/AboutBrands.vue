<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { usePointsStore } from '../../stores/points/index.js';

const { t } = useI18n();
const pointsStore = usePointsStore();

// Map refs and state
const mapContainer = ref(null);
let map = null;
let markersLayer = null;

// Set the specific location you requested
const defaultLocation = {
    center: [52.14387921854451, 4.414891835941198],
    zoom: 15,
};

// Format date with time and day name (for statistics)
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

// Calculate litter rates per hour and minute
const calculateRates = () => {
    const features = pointsStore.pointsGeojson?.features;
    if (!features || features.length === 0) return null;

    // Get date range from the current viewport data only
    const dates = features
        .map((f) => f.properties.datetime)
        .filter((d) => d)
        .sort();

    if (dates.length < 2) return null; // Need at least 2 dates for a range

    const earliest = dates[0];
    const latest = dates[dates.length - 1];

    const timeDiffMs = new Date(latest) - new Date(earliest);

    // If the time difference is too small (less than 1 minute), don't show rates
    if (timeDiffMs < 60000) return null;

    const hours = timeDiffMs / (1000 * 60 * 60);
    const minutes = timeDiffMs / (1000 * 60);

    // Only show rates if the time span is reasonable (less than 1 week)
    // This filters out data that spans months/years which gives misleading rates
    const oneWeekMs = 7 * 24 * 60 * 60 * 1000;
    if (timeDiffMs > oneWeekMs) {
        // For long time spans, just show the date range without rates
        const earliestFormatted = formatDateTime(earliest);
        const latestFormatted = formatDateTime(latest);

        return {
            perHour: null,
            perMinute: null,
            totalHours: hours.toFixed(1),
            dateRange: `Data from ${earliestFormatted.fullDate} to ${latestFormatted.fullDate}`,
            longTimeSpan: true,
        };
    }

    const earliestFormatted = formatDateTime(earliest);
    const latestFormatted = formatDateTime(latest);

    // Format the date range string
    let dateRangeStr;
    if (earliestFormatted.isSameDay(latest)) {
        dateRangeStr = `From ${earliestFormatted.time} to ${latestFormatted.time} on ${earliestFormatted.fullDate}`;
    } else {
        dateRangeStr = `From ${earliestFormatted.time} on ${earliestFormatted.fullDate} to ${latestFormatted.time} on ${latestFormatted.fullDate}`;
    }

    return {
        perHour: (features.length / hours).toFixed(1),
        perMinute: (features.length / minutes).toFixed(2),
        totalHours: hours.toFixed(1),
        dateRange: dateRangeStr,
        longTimeSpan: false,
    };
};

// Initialize map
async function initMap() {
    if (!mapContainer.value || map) return;

    console.log('Initializing map...');

    map = L.map(mapContainer.value, {
        center: defaultLocation.center,
        zoom: defaultLocation.zoom,
        scrollWheelZoom: false,
        preferCanvas: true, // Use canvas renderer for better performance
    });

    // OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    // Create layer group for markers
    markersLayer = L.layerGroup().addTo(map);

    console.log('Map initialized at:', defaultLocation.center, 'zoom:', defaultLocation.zoom);

    // Load points when map moves
    map.on('moveend zoomend', () => {
        console.log('Map moved/zoomed, loading points...');
        loadPoints();
    });

    // Initial load
    await loadPoints();
}

// Load points with brand data
async function loadPoints() {
    if (!map) {
        console.log('No map available');
        return;
    }

    const bounds = map.getBounds();
    const bbox = {
        left: bounds.getWest(),
        bottom: bounds.getSouth(),
        right: bounds.getEast(),
        top: bounds.getNorth(),
    };

    console.log('Loading points for bbox:', bbox);
    console.log('Current zoom:', Math.round(map.getZoom()));

    // Check if zoom level meets minimum requirement (backend requires 15-20)
    const currentZoom = Math.round(map.getZoom());
    if (currentZoom < 15) {
        console.log('Zoom level too low, minimum is 15');
        pointsStore.setError('Zoom in closer to see litter data (minimum zoom level 15)');
        clearMarkers();
        return;
    }

    try {
        // Check if we already have data for these bounds
        if (pointsStore.hasDataForBounds(bbox, currentZoom)) {
            console.log('Using cached data');
            renderPoints();
            return;
        }

        // Fetch new data
        console.log('Fetching new data...');
        const data = await pointsStore.GET_POINTS({
            zoom: currentZoom,
            bbox,
        });

        console.log('Data received:', data);
        console.log('Features count:', data?.features?.length || 0);

        // Update current bounds for caching
        pointsStore.updateCurrentBounds(bbox, currentZoom);

        // Render the points
        renderPoints();
    } catch (error) {
        console.error('Failed to load points:', error);

        // Show user-friendly error
        if (error.response?.status === 422) {
            pointsStore.setError('Invalid request parameters - try zooming to a smaller area');
        } else if (error.response?.status === 404) {
            pointsStore.setError('API endpoint not found - check your routes');
        } else {
            pointsStore.setError('Failed to load litter data');
        }
    }
}

// Clear existing markers
function clearMarkers() {
    if (markersLayer) {
        markersLayer.clearLayers();
    }
}

// Render points on the map using CircleMarkers (more reliable than glify)
function renderPoints() {
    const features = pointsStore.pointsGeojson?.features;

    console.log('renderPoints called');
    console.log('Features available:', features?.length || 0);

    if (!map) {
        console.log('No map available for rendering');
        return;
    }

    if (!features || features.length === 0) {
        console.log('No features to render');
        clearMarkers();
        return;
    }

    // Clear existing markers
    clearMarkers();

    console.log(`Rendering ${features.length} points`);

    // Group markers by proximity for better performance
    const zoom = map.getZoom();
    const markerSize = zoom < 17 ? 2 : zoom < 19 ? 3 : 4; // Smaller sizes

    // Create markers
    features.forEach((feature, index) => {
        const [lon, lat] = feature.geometry.coordinates;

        // Create a circle marker for each point
        const marker = L.circleMarker([lat, lon], {
            radius: markerSize,
            fillColor: '#ef4444', // red-500 color for litter
            color: '#dc2626', // red-600
            weight: 1,
            opacity: 0.8,
            fillOpacity: 0.6, // Slightly less opaque
        });

        // Add popup with details
        if (feature.properties) {
            const props = feature.properties;
            let popupContent = '<div class="popup-content">';
            if (props.datetime) {
                popupContent += `<strong>Date:</strong> ${new Date(props.datetime).toLocaleDateString()}<br>`;
            }
            if (props.total_litter) {
                popupContent += `<strong>Items:</strong> ${props.total_litter}<br>`;
            }
            if (props.username) {
                popupContent += `<strong>User:</strong> ${props.username}<br>`;
            }
            popupContent += '</div>';

            marker.bindPopup(popupContent);
        }

        // Add click handler
        marker.on('click', () => {
            console.log('Clicked point:', feature.properties);
        });

        // Add to layer group
        markersLayer.addLayer(marker);
    });

    console.log(`Rendered ${features.length} markers`);

    // Force a map update
    setTimeout(() => {
        map.invalidateSize();
    }, 100);
}

onMounted(async () => {
    console.log('Component mounted, initializing map...');
    await initMap();
});

onBeforeUnmount(() => {
    console.log('Component unmounting...');

    if (markersLayer) {
        markersLayer.clearLayers();
        map.removeLayer(markersLayer);
    }

    if (map) {
        map.off();
        map.remove();
    }

    pointsStore.CLEAR_POINTS();
});
</script>

<template>
    <section
        class="py-16 sm:py-20 bg-gradient-to-br from-gray-950 via-amber-950 to-orange-950 relative overflow-hidden"
    >
        <!-- Corporate pattern background -->
        <div class="absolute inset-0 opacity-10">
            <svg class="absolute top-0 w-full h-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 800">
                <defs>
                    <pattern
                        id="hexagons"
                        width="50"
                        height="43.4"
                        patternUnits="userSpaceOnUse"
                        patternTransform="scale(2) rotate(0)"
                    >
                        <polygon
                            points="24.8,22 37.3,11 49.8,22 49.8,43.4 37.3,54.4 24.8,43.4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.5"
                            class="text-amber-500"
                        />
                        <polygon
                            points="0,22 12.5,11 25,22 25,43.4 12.5,54.4 0,43.4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.5"
                            class="text-amber-500"
                        />
                        <polygon
                            points="49.8,22 62.3,11 74.8,22 74.8,43.4 62.3,54.4 49.8,43.4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.5"
                            class="text-amber-500"
                        />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#hexagons)" />
            </svg>

            <!-- Additional gradient overlay for depth -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
        </div>

        <div class="w-full mx-auto relative z-10 px-8 md:px-16 lg:px-20 xl:px-28">
            <div class="grid lg:grid-cols-2 items-center gap-8 md:gap-16 lg:gap-20 xl:gap-28">
                <!-- Left Column: Text -->
                <div class="text-white">
                    <!-- Decorative element -->
                    <div class="flex items-center mb-6">
                        <span
                            class="w-16 h-1.5 bg-gradient-to-r from-amber-500 via-yellow-500 to-orange-500 rounded-full shadow-lg shadow-amber-500/50"
                        ></span>
                        <span class="ml-4 text-amber-400 font-semibold tracking-wider uppercase text-sm">{{
                            t('Accountability')
                        }}</span>
                    </div>

                    <h2
                        class="text-4xl sm:text-5xl lg:text-6xl font-black mb-8 bg-gradient-to-r from-amber-200 via-yellow-200 to-orange-200 bg-clip-text text-transparent leading-tight"
                    >
                        {{ t('What Brands are Polluting Your Community?') }}
                    </h2>

                    <p class="text-xl sm:text-2xl text-amber-100/90 mb-8 leading-relaxed font-light">
                        {{
                            t(
                                'Taxpayers and volunteers have to work long hours to cleanup the waste produced by super-profitable ocean-polluting megacorporations.'
                            )
                        }}
                    </p>

                    <p class="text-lg sm:text-xl text-amber-100/80 mb-10 leading-relaxed">
                        {{
                            t(
                                'They privatize all the gains and socialize all the losses, leaving communities to deal with the mess they benefit from.'
                            )
                        }}
                    </p>

                    <!-- Brand stats preview with enhanced styling -->
                    <div class="grid grid-cols-3 gap-6 mb-6">
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-amber-600 to-orange-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-amber-900/40 to-orange-900/40 backdrop-blur-md border border-amber-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-yellow-400 to-amber-400 bg-clip-text"
                                >
                                    1500+
                                </div>
                                <div class="text-sm text-amber-200/90 mt-1">{{ t('Brands identified') }}</div>
                            </div>
                        </div>
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-amber-600 to-orange-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-amber-900/40 to-orange-900/40 backdrop-blur-md border border-amber-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-yellow-400 to-amber-400 bg-clip-text"
                                >
                                    100+
                                </div>
                                <div class="text-sm text-amber-200/90 mt-1">{{ t('Countries') }}</div>
                            </div>
                        </div>
                        <div class="relative group">
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-amber-600 to-orange-600 rounded-2xl blur-xl opacity-50 group-hover:opacity-75 transition duration-500"
                            ></div>
                            <div
                                class="relative bg-gradient-to-br from-amber-900/40 to-orange-900/40 backdrop-blur-md border border-amber-600/30 rounded-2xl p-6 transform hover:scale-105 transition duration-300"
                            >
                                <div
                                    class="text-3xl font-black text-transparent bg-gradient-to-r from-yellow-400 to-amber-400 bg-clip-text"
                                >
                                    {{ pointsStore.pointsCount || '24/7' }}
                                </div>
                                <div class="text-sm text-amber-200/90 mt-1">
                                    {{ pointsStore.pointsCount ? 'Points loaded' : 'Live tracking' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Litter statistics box -->
                    <div
                        v-if="pointsStore.pointsCount > 0 && calculateRates()"
                        class="mb-6 bg-red-900/20 border border-red-500/20 rounded-lg p-4"
                    >
                        <div class="text-red-200 font-semibold mb-2">
                            <i class="fas fa-trash mr-2"></i>
                            {{ pointsStore.pointsCount }} {{ t('litter points found') }}
                        </div>
                        <div v-if="!calculateRates().longTimeSpan" class="space-y-1">
                            <div class="text-red-300">
                                <i class="fas fa-chart-line mr-2"></i>
                                {{ t('Rate:') }}
                                <span class="font-bold text-red-200">{{ calculateRates().perHour }}</span>
                                {{ t('items/hour') }},
                                <span class="font-bold text-red-200">{{ calculateRates().perMinute }}</span>
                                {{ t('items/minute') }}
                            </div>
                            <div class="text-sm text-red-400">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                {{ calculateRates().dateRange }}
                            </div>
                        </div>
                        <div v-else class="text-sm text-red-400">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            {{ calculateRates().dateRange }}
                        </div>
                    </div>

                    <!-- Loading indicator -->
                    <div v-if="pointsStore.loading" class="mt-6 flex items-center text-amber-300">
                        <svg
                            class="animate-spin -ml-1 mr-3 h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        {{ t('Loading litter data...') }}
                    </div>

                    <!-- Error indicator -->
                    <div v-if="pointsStore.error" class="mt-6 text-red-400">
                        {{ t('Error loading data:') }} {{ pointsStore.error }}
                    </div>
                </div>

                <!-- Right Column: Map -->
                <div class="relative group">
                    <!-- Enhanced glow effect with multiple layers -->
                    <div
                        class="absolute -inset-3 bg-gradient-to-r from-amber-600 via-yellow-500 to-orange-600 rounded-3xl blur-2xl opacity-60 group-hover:opacity-100 transition duration-700 animate-pulse"
                    ></div>
                    <div
                        class="absolute -inset-2 bg-gradient-to-r from-amber-500 to-orange-500 rounded-3xl blur-lg opacity-40 group-hover:opacity-70 transition duration-500"
                    ></div>

                    <div class="relative">
                        <div
                            ref="mapContainer"
                            class="rounded-3xl shadow-2xl w-full h-[500px] overflow-hidden transform group-hover:scale-[1.02] transition-all duration-500 ring-1 ring-white/10 z-10"
                        ></div>

                        <!-- Enhanced overlay gradients -->
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-amber-950/50 via-transparent to-transparent rounded-3xl pointer-events-none"
                        ></div>
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-transparent via-transparent to-orange-900/30 rounded-3xl pointer-events-none"
                        ></div>

                        <!-- Additional corner accent -->
                        <div
                            class="absolute bottom-6 right-6 bg-gradient-to-r from-amber-500/20 to-orange-500/20 backdrop-blur-md rounded-xl px-4 py-2 border border-amber-500/20"
                        >
                            <span class="text-amber-300 text-xs font-medium">{{ t('Click points to zoom') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating brand logos animation -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="brand-particle brand-particle-1"></div>
            <div class="brand-particle brand-particle-2"></div>
            <div class="brand-particle brand-particle-3"></div>
        </div>
    </section>
</template>

<style scoped>
/* Map styling with enhanced visual effects */
:deep(.leaflet-container) {
    background: linear-gradient(135deg, #0a0a0a 0%, #1a0f00 100%);
    font-weight: 500;
    position: relative;
    z-index: 1;
}

:deep(.leaflet-control-attribution) {
    background-color: rgba(0, 0, 0, 0.9);
    color: #fbbf24;
    backdrop-filter: blur(10px);
    border-radius: 8px;
    padding: 4px 8px;
    border: 1px solid rgba(251, 191, 36, 0.2);
}

/* Ensure map panes are visible */
:deep(.leaflet-pane) {
    z-index: 400;
}

:deep(.leaflet-overlay-pane) {
    z-index: 400;
}

/* Style for circle markers */
:deep(.leaflet-marker-pane) {
    z-index: 600;
}

/* Popup styling */
:deep(.leaflet-popup-content-wrapper) {
    background: rgba(0, 0, 0, 0.9);
    color: #fbbf24;
    border: 1px solid rgba(251, 191, 36, 0.3);
}

:deep(.leaflet-popup-tip) {
    background: rgba(0, 0, 0, 0.9);
}

:deep(.popup-content) {
    font-size: 12px;
    line-height: 1.4;
}

/* Enhanced brand particle animation */
.brand-particle {
    position: absolute;
    width: 60px;
    height: 60px;
    background: radial-gradient(
        circle,
        rgba(251, 191, 36, 0.4) 0%,
        rgba(251, 191, 36, 0.1) 40%,
        rgba(251, 191, 36, 0) 70%
    );
    border-radius: 50%;
    opacity: 0;
    animation: drift 30s infinite ease-in-out;
    filter: blur(1px);
}

.brand-particle::before {
    content: '';
    position: absolute;
    inset: 25%;
    background: radial-gradient(circle, rgba(251, 191, 36, 0.8) 0%, rgba(251, 191, 36, 0.4) 50%, transparent 70%);
    border-radius: 50%;
    box-shadow: 0 0 20px rgba(251, 191, 36, 0.6);
}

.brand-particle::after {
    content: '';
    position: absolute;
    inset: 40%;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    animation: sparkle 2s infinite ease-in-out;
}

.brand-particle-1 {
    left: 5%;
    animation-delay: 0s;
    animation-duration: 28s;
}

.brand-particle-2 {
    left: 45%;
    animation-delay: 10s;
    animation-duration: 32s;
}

.brand-particle-3 {
    left: 85%;
    animation-delay: 20s;
    animation-duration: 30s;
}

@keyframes drift {
    0% {
        transform: translateY(110vh) translateX(0) rotate(0deg) scale(0.8);
        opacity: 0;
    }
    5% {
        opacity: 0.7;
    }
    50% {
        transform: translateY(50vh) translateX(80px) rotate(180deg) scale(1.2);
        opacity: 0.9;
    }
    95% {
        opacity: 0.7;
    }
    100% {
        transform: translateY(-10vh) translateX(-30px) rotate(360deg) scale(0.8);
        opacity: 0;
    }
}

@keyframes sparkle {
    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(0.6);
        opacity: 0.6;
    }
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
    .brand-particle {
        animation: none;
    }

    .transform {
        transform: none !important;
    }
}
</style>
