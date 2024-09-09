<template>
	<div class="sidebar-menu scrollbar-hidden">
		<transition-group name="list" mode="out-in">
			<span
                v-for="(event, index) in events"
                :key="event.id"
                class="list-item"
            >
                <component
                    :is="event.type"
                    :payload="event.payload"
                    @click="click(event, index)"
                />
			</span>
		</transition-group>
	</div>
</template>

<script>
import CleanupCreated from "./Notifications/CleanupCreated.vue";
import ImageUploaded from './Notifications/ImageUploaded.vue';
import LittercoinMined from './Notifications/LittercoinMined.vue';
import NewCountryAdded from './Notifications/NewCountryAdded.vue';
import NewStateAdded from './Notifications/NewStateAdded.vue';
import NewCityAdded from './Notifications/NewCityAdded.vue';
import TeamCreated from './Notifications/TeamCreated.vue';
import UserSignedUp from './Notifications/UserSignedUp.vue';

export default {
	name: 'live-events',
    components: {
        CleanupCreated,
        ImageUploaded,
        LittercoinMined,
        NewCityAdded,
        NewStateAdded,
        NewCountryAdded,
        TeamCreated,
        UserSignedUp,
    },
    data () {
        return {
            events: [],
            clicks: 0,
            timer: null
        };
    },
    created () {
        this.listenForEvents();
    },
	methods: {

        addEvent (event, payload)
        {
            this.events.unshift({
                id: new Date().getTime(),
                type: event,
                payload: payload
            });

            this.updateDocumentTitle();
        },

        /**
         * This is usually how double-clicks are handled
         * without overlapping with the click events
         * https://stackoverflow.com/a/41309853/5828796
         */
        click (event, index)
        {
            this.clicks++;

            if (this.clicks === 1) {
                this.timer = setTimeout(() => {
                    this.flyToLocation(event);
                    this.clicks = 0
                }, 300);
            } else {
                clearTimeout(this.timer);
                this.removeEvent(index);
                this.clicks = 0;
            }
        },

        listenForEvents () {

            Echo.channel('main')
                .listen('.App\\Events\\Cleanups\\CleanupCreated', (payload) => {
                    this.addEvent('CleanupCreated', payload);
                })
                .listen('ImageUploaded', (payload) => {
                    this.addEvent('ImageUploaded', payload);
                })
                .listen('NewCountryAdded', (payload) => {
                    this.addEvent('NewCountryAdded', payload);
                })
                .listen('NewStateAdded', (payload) => {
                    this.addEvent('NewStateAdded', payload);
                })
                .listen('NewCityAdded', (payload) => {
                    this.addEvent('NewCityAdded', payload);
                })
                .listen('.App\\Events\\Littercoin\\LittercoinMined', (payload) => {
                    this.addEvent('LittercoinMined', payload);
                })
                .listen('TeamCreated', (payload) => {
                    this.addEvent('TeamCreated', payload);
                })
                .listen('UserSignedUp', (payload) => {
                    this.addEvent('UserSignedUp', payload.now);
                })
        },

        /**
         * Emits an event to fly to the event's location, if any
         * @param event
         */
        flyToLocation (event)
        {
            if (event.payload?.latitude && event.payload?.longitude) {
                this.$emit('fly-to-location', {...event.payload, zoom: 17});
            }
        },

        /**
         * Removes the event at the specified index
         * @param index
         */
        removeEvent (index)
        {
            this.events.splice(index, 1);

            this.updateDocumentTitle();
        },

        /**
         * Updates the document title depending on the number of events
         */
        updateDocumentTitle ()
        {
            document.title = this.events.length > 0
                ? '(' + this.events.length + ') OpenLitterMap'
                : 'OpenLitterMap';
        }
    }
}
</script>

<style lang="scss">

    .list-enter-active {
        transition: all 1s ease;
    }
    .list-leave-active {
        transition: all .3s ease;
    }
    .list-move {
        transition: transform 1s ease-in-out;
    }

    .list-enter, .list-leave-to {
        transform: translateX(100px);
        opacity: 0;
    }

    .list-item {
        display: grid;
    }

    .sidebar-menu {
        position: absolute;
        top: 70px;
        right: 10px;
        width: 20rem;
        max-height: 80vh;
        overflow-y: scroll;
        z-index: 999;
        font-size: 0.8rem;
        .event {
            width: 20rem;
        }
    }

    .grid-img {
        padding: 16px;
    }

    .grid-main {
        padding-top: 10px;
        padding-bottom: 10px;
    }

    @media (max-width: 1024px) {
        .sidebar-menu {
            width: 16rem;
            font-size: 0.7rem;
            .event {
                width: 16rem;
            }
        }
        .grid-img {
            padding: 12px;
        }
        .grid-main {
            padding-top: 8px;
            padding-bottom: 8px;
        }
    }

    @media (max-width: 768px) {
        .sidebar-menu {
            width: 12rem;
            .event {
                width: 12rem;
            }
        }
    }

    @media (max-width: 640px) {
        .sidebar-menu {
            width: 10rem;
            max-height: 74vh;
            font-size: 0.6rem;
            .event {
                width: 10rem;
            }
        }
    }

    .sidebar-title {
        padding: 20px;
        text-align: center;
        font-size: 24px;
        font-weight: 700;
    }

</style>
