<template>
    <div class="absolute top-16 right-2 w-80 max-h-[80vh] overflow-y-scroll z-[999] text-sm p-4">
        <transition-group
            name="list"
            @after-enter="handleAfterEnter"
            @before-leave="handleBeforeLeave"
            @after-leave="handleAfterLeave"
        >
            <span v-for="(event, index) in events" :key="event.id" class="grid gap-2 text-dark-text">
                <component :is="components[event.type]" :payload="event.payload" @click="handleClick(event, index)" />
            </span>
        </transition-group>
    </div>
</template>

<script setup>
import { defineProps, ref, toRefs, onMounted, onUnmounted } from 'vue';
import { v4 as uuidv4 } from 'uuid';
import CleanupCreated from './Notifications/CleanupCreated.vue';
import ImageUploaded from './Notifications/ImageUploaded.vue';
import LittercoinMined from './Notifications/LittercoinMined.vue';
import NewCountryAdded from './Notifications/NewCountryAdded.vue';
import NewStateAdded from './Notifications/NewStateAdded.vue';
import NewCityAdded from './Notifications/NewCityAdded.vue';
import TeamCreated from './Notifications/TeamCreated.vue';
import UserSignedUp from './Notifications/UserSignedUp.vue';
import BadgeCreated from './Notifications/BadgeCreated.vue';

const components = {
    CleanupCreated,
    ImageUploaded,
    LittercoinMined,
    NewCountryAdded,
    NewStateAdded,
    NewCityAdded,
    TeamCreated,
    UserSignedUp,
    BadgeCreated,
};

const props = defineProps({
    mapInstance: {
        type: Object,
        required: true,
    },
});
const { mapInstance } = toRefs(props);
const emit = defineEmits(['fly-to-location']);

const events = ref([]); // The “shown” events (animated into the UI)
const pendingEvents = ref([]); // Events waiting to be shown
const animating = ref(false); // Flag to allow only 1 event to animate at a time
const clicks = ref(0);
const timer = ref(null);

onMounted(() => {
    listenForEvents();
});

onUnmounted(() => {
    clearTimeout(timer.value);
    Echo.leaveChannel('main');
});

/**
 * 1) Push new event into 'pendingEvents' queue.
 * 2) If we’re not currently animating, move one event from 'pendingEvents' -> 'events'.
 */
const addEvent = (eventType, payload) => {
    console.log({ eventType });
    console.log({ payload });

    if (!components[eventType]) {
        console.error(`Component "${eventType}" is not registered.`);
        return;
    }

    const existingEvent = pendingEvents.value.find((event) => event.payload.id === payload.id);
    if (existingEvent) {
        console.warn('Duplicate event detected, skipping:', payload.id);
        return;
    }

    const id = uuidv4();

    pendingEvents.value.unshift({
        id,
        type: eventType,
        payload,
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

// Prevent accidental overlap in animations
const handleBeforeLeave = (el) => {
    el.style.pointerEvents = 'none';
};

// Prevent accidental overlap in animations
const handleAfterLeave = (el) => {
    el.style.pointerEvents = '';
};

/**
 * Tracks single or double-clicks on an event.
 */
const handleClick = (event, index) => {
    // Check if a timer exists (indicating a potential double-click sequence)
    if (timer.value) {
        clearTimeout(timer.value);
        timer.value = null;
        clicks.value = 0;

        // perform doubleClick action
        removeEvent(index);
    } else {
        clicks.value++;

        timer.value = setTimeout(() => {
            timer.value = null;
            clicks.value = 0;

            // Perform single-click action
            flyToLocation(event);
        }, 300);
    }
};

const flyToLocation = (event) => {
    if (event.payload?.latitude && event.payload?.longitude) {
        emit('fly-to-location', { ...event.payload, zoom: 17, mapInstance: mapInstance.value });
    }
};

const removeEventTimeouts = new Map();
const removeEvent = (index) => {
    // Prevent multiple calls for the same index.
    if (removeEventTimeouts.has(index)) return;

    removeEventTimeouts.set(
        index,
        setTimeout(() => {
            events.value.splice(index, 1);
            removeEventTimeouts.delete(index);
            updateDocumentTitle();
        }, 100)
    );
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
        })
        .listen('.App\\Events\\Images\\BadgeCreated', (payload) => {
            addEvent('BadgeCreated', payload);
        });
};

const updateDocumentTitle = () => {
    document.title = events.value.length === 0 ? 'OpenLitterMap' : `(${events.value.length}) OpenLitterMap`;
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
    transition: transform 0.6s cubic-bezier(0.25, 0.8, 0.5, 1);
}
</style>
