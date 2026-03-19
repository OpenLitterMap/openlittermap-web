<template>
    <div class="relative w-full h-full rounded-xl overflow-hidden">
        <!-- Map container -->
        <div ref="mapContainer" class="w-full h-full" />

        <!-- Empty state overlay -->
        <div
            v-if="points.length === 0"
            class="absolute inset-0 flex items-center justify-center pointer-events-none"
        >
            <p class="text-white/20 text-sm">{{ $t('Your observations will appear here') }}</p>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    points: { type: Array, default: () => [] },
});

const mapContainer = ref(null);
let map = null;
let markerGroup = null;

const initMap = () => {
    if (!mapContainer.value || !window.L) return;

    map = window.L.map(mapContainer.value, {
        zoomControl: false,
        attributionControl: false,
    }).setView([30, 0], 2);

    // Dark tile layer
    window.L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        subdomains: 'abcd',
        maxZoom: 19,
    }).addTo(map);

    // Compact zoom control bottom-right
    window.L.control.zoom({ position: 'bottomright' }).addTo(map);

    markerGroup = window.L.layerGroup().addTo(map);
};

const addPoint = (point) => {
    if (!markerGroup || !map) return;

    const marker = window.L.circleMarker([point.lat, point.lon], {
        radius: 6,
        fillColor: '#10b981',
        color: '#ffffff',
        weight: 2,
        opacity: 1,
        fillOpacity: 0.9,
        className: 'upload-pin-enter',
    });

    marker.bindPopup(
        `<div style="font-size:13px;color:#e2e8f0"><strong>${point.city || 'Unknown'}</strong></div>`,
        { className: 'upload-map-popup', closeButton: false },
    );

    markerGroup.addLayer(marker);

    // Fit bounds to all points with padding
    const allPoints = props.points.map((p) => [p.lat, p.lon]);
    if (allPoints.length === 1) {
        map.setView(allPoints[0], 13, { animate: true, duration: 0.5 });
    } else if (allPoints.length > 1) {
        map.fitBounds(allPoints, { padding: [40, 40], animate: true, duration: 0.5 });
    }
};

// Watch for new points being added
watch(
    () => props.points.length,
    (newLen, oldLen) => {
        if (newLen > oldLen) {
            // Add only the newest point(s)
            for (let i = oldLen; i < newLen; i++) {
                addPoint(props.points[i]);
            }
        }
    },
);

onMounted(async () => {
    await nextTick();
    initMap();
});

onUnmounted(() => {
    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<style>
/* Pin entrance animation */
.upload-pin-enter {
    animation: pin-pop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes pin-pop {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

/* Dark popup styling */
.upload-map-popup .leaflet-popup-content-wrapper {
    background: rgba(15, 23, 42, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.upload-map-popup .leaflet-popup-tip {
    background: rgba(15, 23, 42, 0.95);
}

.upload-map-popup .leaflet-popup-content {
    margin: 8px 12px;
}
</style>
