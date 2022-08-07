<template>
    <div>
        <div class="pt3 pb3 flex" style="align-items: center;">
            <i
                v-if="joiningCleanup || creatingCleanup"
                class="fa fa-arrow-left pointer"
                @click="goBack"
            />

            <p
                class="title is-3 flex-1"
            >
                {{ getTitle }}
            </p>
        </div>

        <div class="cleanup-buttons">
            <!-- Create or Join a Cleanup -->
            <div v-if="!creatingCleanup && !joiningCleanup">
                <img
                    :src="getCreateCleanupImg"
                    class="pb1"
                >

                <div v-if="auth">
                    <button
                        class="button is-medium is-info mb1"
                        @click="startCreatingCleanup"
                    >
                        Create a cleanup
                    </button>

                    <button
                        class="button is-medium is-primary mb1"
                        @click="startJoiningCleanup"
                    >
                        Join a cleanup
                    </button>
                </div>

                <p
                    v-else
                    class="mb1"
                >
                    Log In to Create or Join a Cleanup
                </p>

                <p class="mb1">
                   Cleanups are a great way to bring people together, quantify, and communicate your positive environmental impact.
                </p>

                <p>
                    Clean up, have fun and share data!
                </p>
            </div>

            <!-- Create a cleanup -->
            <CreateCleanup
                v-if="creatingCleanup"
            />

            <JoinCleanup
                v-if="joiningCleanup"
            />
        </div>
    </div>
</template>

<script>
import CreateCleanup from "./CreateCleanup";
import JoinCleanup from "./JoinCleanup";

const pickLitterImg = "https://img.freepik.com/free-photo/hand-person-blue-latex-glove-picks-up-plastic-bottle-from-ground_176532-10351.jpg?w=1380&t=st=1659282375~exp=1659282975~hmac=cbd7540fbf81fef9ffe4a00e7dce755f3a25a49a1cc77376c226c86a89efb73b";
const groupLitterImg = "https://img.freepik.com/free-vector/volunteers-cleaning-up-garbage-city-park_74855-17942.jpg?w=1380&t=st=1659282438~exp=1659283038~hmac=b3c1ecc87fa677a97391b1f182f0e8674f32684d632f8d5df366bfe8204ee62e";

export default {
    name: "CleanupSidebar",
    components: {
        CreateCleanup,
        JoinCleanup
    },
    props: [
        'creatingCleanup',
        'joiningCleanup'
    ],
    data () {
        return {
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
                : "Help us clean the planet!";
        }
    },
    methods: {
        /**
         * Stop trying to join or create a Cleanup
         */
        goBack ()
        {
            this.$store.commit('clearErrors');
            this.$store.commit('creatingCleanup', false);
            this.$store.commit('joiningCleanup', false);
        },

        /**
         * Start creating a cleanup
         *
         * Step 1: Mark your location
         */
        startCreatingCleanup ()
        {
            this.$store.commit('creatingCleanup', true);
        },

        /**
         * Show JoinCleanup component
         *
         * Enter code to join a cleanup
         */
        startJoiningCleanup ()
        {
            this.$store.commit('joiningCleanup', true);
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
