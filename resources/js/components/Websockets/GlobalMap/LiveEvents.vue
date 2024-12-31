<template>
    <div
        class="absolute top-16 right-2 w-80 max-h-[80vh] overflow-y-scroll z-[999] text-sm p-4"
    >
        <transition-group
            name="list"
            @after-enter="handleAfterEnter"
        >
            <span
                v-for="(event, index) in events"
                :key="event.id"
                class="grid gap-2"
            >
                <component
                    :is="components[event.type]"
                    :payload="event.payload"
                    @click="handleClick(event, index)"
                />
            </span>
        </transition-group>
    </div>
</template>

<script setup>
import { defineProps, ref, onMounted } from 'vue';
import CleanupCreated from "./Notifications/CleanupCreated.vue";
import ImageUploaded from './Notifications/ImageUploaded.vue';
import LittercoinMined from './Notifications/LittercoinMined.vue';
import NewCountryAdded from './Notifications/NewCountryAdded.vue';
import NewStateAdded from './Notifications/NewStateAdded.vue';
import NewCityAdded from './Notifications/NewCityAdded.vue';
import TeamCreated from './Notifications/TeamCreated.vue';
import UserSignedUp from './Notifications/UserSignedUp.vue';

const components = {
    CleanupCreated,
    ImageUploaded,
    LittercoinMined,
    NewCountryAdded,
    NewStateAdded,
    NewCityAdded,
    TeamCreated,
    UserSignedUp
};

defineProps({
    mapInstance: Object
});

const emit = defineEmits(['fly-to-location']);

const events = ref([]);          // The “shown” events (animated into the UI)
const pendingEvents = ref([]);   // Events waiting to be shown
const animating = ref(false);    // Flag to allow only 1 event to animate at a time
const clicks = ref(0);
const timer = ref(null);

onMounted(() => {
    listenForEvents();
});

/**
 * 1) Push new event into 'pendingEvents' queue.
 * 2) If we’re not currently animating, move one event from 'pendingEvents' -> 'events'.
 */
const addEvent = (eventType, payload) => {
    if (!components[eventType]) {
        console.error(`Component "${eventType}" is not registered.`);
        return;
    }

    pendingEvents.value.unshift({
        id: new Date().getTime(),
        type: eventType,
        payload
    });

    processQueue();
};

/**
 * If not currently animating, move the next event from 'pendingEvents' into 'events'
 */
const processQueue = () => {
    if (!animating.value && pendingEvents.value.length > 0) {
        animating.value = true;
        const nextEvent = pendingEvents.value.shift();
        events.value.unshift(nextEvent);
    }
};

/**
 * Fired by transition-group when an item finishes “entering”.
 * We'll set animating to false and attempt to process next event in the queue.
 */
const handleAfterEnter = () => {
    animating.value = false;
    processQueue();
};

/**
 * Tracks single or double-clicks on an event.
 */
const handleClick = (event, index) => {
    clicks.value++;

    if (clicks.value === 1) {
        timer.value = setTimeout(() => {
            flyToLocation(event);
            clicks.value = 0;
        }, 300);
    } else {
        clearTimeout(timer.value);
        removeEvent(index);
        clicks.value = 0;
    }
};

const flyToLocation = (event) => {
    if (event.payload?.latitude && event.payload?.longitude) {
        emit("fly-to-location", { ...event.payload, zoom: 17, mapInstance: mapInstance.value });
    }
};

const removeEvent = (index) => {
    events.value.splice(index, 1);

    updateDocumentTitle();
};

const listenForEvents = () => {
    Echo.channel('main')
        .listen('.App\\Events\\Cleanups\\CleanupCreated', (payload) => {
            addEvent('CleanupCreated', payload);
        })
        .listen('ImageUploaded', (payload) => {
            addEvent('ImageUploaded', payload);
        })
        .listen('NewCountryAdded', (payload) => {
            addEvent('NewCountryAdded', payload);
        })
        .listen('NewStateAdded', (payload) => {
            addEvent('NewStateAdded', payload);
        })
        .listen('NewCityAdded', (payload) => {
            addEvent('NewCityAdded', payload);
        })
        .listen('.App\\Events\\Littercoin\\LittercoinMined', (payload) => {
            addEvent('LittercoinMined', payload);
        })
        .listen('TeamCreated', (payload) => {
            addEvent('TeamCreated', payload);
        })
        .listen('UserSignedUp', (payload) => {
            addEvent('UserSignedUp', payload.now);
        });
};

const updateDocumentTitle = () => {
    document.title = events.value.length === 0
        ? 'OpenLitterMap'
        : `(${events.value.length}) OpenLitterMap`;
};

</script>

<style scoped>
    .list-item {
        display: grid;
    }
    .grid-img {
        padding: 16px;
    }
    .grid-main {
        padding-top: 10px;
        padding-bottom: 10px;
    }

    /* Slow slide in from the right */
    @keyframes slideInRightCalm {
        0% {
            transform: translateX(100%);
            opacity: 0;
        }
        100% {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Slide out to the left */
    @keyframes slideOutLeft {
        0% {
            transform: translateX(0);
            opacity: 1;
        }
        100% {
            transform: translateX(-100%);
            opacity: 0;
        }
    }

    /* =========================
     * Vue Transition Classes
     * ========================= */

    /* Slower, calm approach (1.5s) */
    .list-enter-active {
        animation: slideInRightCalm 1.5s ease-in-out forwards;
    }
    .list-leave-active {
        animation: slideOutLeft 1.5s ease forwards;
    }

    /* Vue needs these initial/end states for transitions to work properly */
    .list-enter {
        opacity: 0;
        transform: translateX(100%);
    }
    .list-leave-to {
        opacity: 0;
        transform: translateX(-100%);
    }

    /*
      The .list-move transition ensures items reorder smoothly,
      but with our queue approach, you’ll typically see only one
      new item entering at a time anyway.
    */
    .list-move {
        transition: transform 0.4s ease-in-out;
    }
</style>
