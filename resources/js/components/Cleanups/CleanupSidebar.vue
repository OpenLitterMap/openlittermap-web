<template>
    <div>
        <p class="title is-3 pt1 mb2">
            {{ getTitle }}
        </p>

        <div class="cleanup-buttons">
            <div v-if="!creatingCleanup">
                <img
                    :src="getCreateCleanupImg"
                >

                <button
                    v-if="auth"
                    class="button is-medium is-info mb1"
                    @click="startCreatingCleanup"
                >
                    Create a cleanup
                </button>

                <p
                    v-else
                    class="mb1"
                >
                    Log in to create a cleanup event
                </p>

                <p class="mb1">
                   Cleanups are a great way to bring people together.
                </p>

                <p>
                    Clean up, have fun and share data!
                </p>
            </div>

            <div
                v-if="creatingCleanup"
                class="cleanup-container"
            >
                <p>Name</p>

                <input
                    class="input mb1"
                    v-model="name"
                    placeholder="My Awesome Cleanup"
                />

                <p>Date</p>

                <input
                    class="input mb1"
                    v-model="date"
                    type="date"
                />

                <p>Location:</p>

                <div class="mb1">

                    <p v-if="!cleanup.lat">
                        Click anywhere on the map to set the location
                    </p>

                    <div v-else>
                        <p>
                            Lat: {{ cleanup.lat }}
                        </p>

                        <p>
                            Lon: {{ cleanup.lon }}
                        </p>
                    </div>
                </div>

                <p>Time</p>

                <input
                    class="input mb1"
                    v-model="time"
                    placeholder="Enter time"
                />

                <p>Description</p>

                <textarea
                    class="input mb1"
                    v-model="description"
                    placeholder="Enter information about your event"
                    style="height: 3em;"
                />

                <p>Create an invite link</p>

                <input
                    class="input mb2"
                    v-model="inviteLink"
                    placeholder="openlittermap.com/my-cleanup-event"
                />

                <div class="flex">
                    <div class="flex-1">
                        <p>Attending</p>

                        <div class="mb1">
                            <p>Just you for now.</p>
                        </div>
                    </div>

                    <button
                        class="button is-info is-medium"
                        :class="processing ? 'is-loading' : ''"
                        :disabled="processing"
                        @click="createCleanup"
                    >
                        Cleanup!
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
const pickLitterImg = "https://img.freepik.com/free-photo/hand-person-blue-latex-glove-picks-up-plastic-bottle-from-ground_176532-10351.jpg?w=1380&t=st=1659282375~exp=1659282975~hmac=cbd7540fbf81fef9ffe4a00e7dce755f3a25a49a1cc77376c226c86a89efb73b";
const groupLitterImg = "https://img.freepik.com/free-vector/volunteers-cleaning-up-garbage-city-park_74855-17942.jpg?w=1380&t=st=1659282438~exp=1659283038~hmac=b3c1ecc87fa677a97391b1f182f0e8674f32684d632f8d5df366bfe8204ee62e";

export default {
    name: "CleanupSidebar",
    props: [
        'creatingCleanup'
    ],
    data () {
        return {
            name: '',
            description: '',
            time: '',
            date: '',
            inviteLink: '',
            processing: false
        };
    },
    computed: {
        /**
         * Return True if a user is authenticated
         */
        auth ()
        {
            return this.$store.state.user.auth;
        },

        /**
         * Shortcut to cleanup/s state
         */
        cleanup ()
        {
            return this.$store.state.cleanups;
        },

        /**
         * Return group image to show for Create Cleanup
         */
        getCreateCleanupImg ()
        {
            return groupLitterImg;
        },

        /**
         * Get the title depending on state
         */
        getTitle ()
        {
            return (this.$store.state.globalmap.creating)
                ? "Create a new cleanup event!"
                : "Help us clean up the planet!";
        }
    },
    methods: {
        /**
         * Create a new cleanup in the database
         */
        async createCleanup ()
        {
            this.processing = true;

            await this.$store.dispatch('CREATE_CLEANUP_EVENT', {
                name: this.name,
                date: this.date,
                lat: this.cleanup.lat,
                lon: this.cleanup.lon,
                time: this.time,
                description: this.description,
                inviteLink: this.inviteLink
            });

            this.processing = false;
        },

        /**
         * Start creating a cleanup
         *
         * Step 1: Mark your location
         */
        startCreatingCleanup ()
        {
            this.$store.commit('creatingCleanup', true);
        }
    }
}
</script>

<style scoped>

    .cleanup-container {
        text-align: left;
        padding: 0 1em;
    }

    .cleanup-buttons {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

</style>
