<template>
    <div
        class="cleanup-container"
    >
        <form
            method="post"
            @submit.prevent="submit"
            @keydown="clearError($event.target.name)"
        >
            <div class="flex">
                <p class="flex-1">Name</p>

                <p
                    class="help is-danger"
                    v-if="errorExists('name')"
                    v-text="getFirstError('name')"
                />
            </div>

            <input
                class="input mb1"
                v-model="name"
                name="name"
                placeholder="My Awesome Cleanup"
                required
                :class="errorExists('name') ? 'is-danger' : ''"
            />

            <div class="flex">
                <p class="flex-1">Date</p>

                <p
                    class="help is-danger"
                    v-if="errorExists('date')"
                    v-text="getFirstError('date')"
                />
            </div>

            <input
                class="input mb1"
                v-model="date"
                name="date"
                type="date"
                :class="errorExists('date') ? 'is-danger' : ''"
            />

            <div class="flex">
                <p class="flex-1">Location:</p>

                <div v-if="errorExists('lat') || errorExists('lon')">
                    <p class="help is-danger">
                        You have not set a location.
                    </p>
                </div>
            </div>

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

            <div class="flex">
                <p class="flex-1">Time</p>

                <p
                    class="help is-danger"
                    v-if="errorExists('time')"
                    v-text="getFirstError('time')"
                />
            </div>

            <input
                class="input mb1"
                v-model="time"
                name="time"
                placeholder="Enter time"
                required
                :class="errorExists('time') ? 'is-danger' : ''"
            />

            <div class="flex">
                <p class="flex-1">Description</p>

                <p
                    class="help is-danger"
                    v-if="errorExists('description')"
                    v-text="getFirstError('description')"
                />
            </div>

            <textarea
                class="input mb1"
                v-model="description"
                name="description"
                placeholder="Enter information about your event"
                style="height: 2.65em;"
                required
                :class="errorExists('description') ? 'is-danger' : ''"
            />

            <div class="flex">
                <p class="flex-1">
                    Create an invite link
                </p>
                <p
                    class="help is-danger"
                    v-if="errorExists('invite_link')"
                    v-text="getFirstError('invite_link')"
                />
            </div>

            <input
                class="input mb-05"
                v-model="invite_link"
                name="invite_link"
                placeholder="openlittermap.com/cleanups/my-cleanup-event"
                :class="errorExists('invite_link') ? 'is-danger' : ''"
            />

            <p class="is-grey mb2">
                {{ getInviteLink }}
            </p>

            <div class="flex">
                <button
                    class="button is-info is-medium"
                    :class="processing ? 'is-loading' : ''"
                    :disabled="processing"
                    type="submit"
                >
                    Let's Cleanup!
                </button>
            </div>
        </form>
    </div>
</template>

<script>
import handleErrors from "../../mixins/errors/handleErrors";

export default {
    name: "CreateCleanup",
    mixins: [
        handleErrors
    ],
    data () {
        return {
            name: '',
            description: '',
            time: '',
            date: '',
            invite_link: '',
            processing: false
        };
    },
    computed: {
        /**
         * Shortcut to cleanup/s state
         */
        cleanup ()
        {
            return this.$store.state.cleanups;
        },

        /**
         * Return the invite link that the user created
         */
        getInviteLink ()
        {
            let url = "https://openlittermap.com/cleanups/";

            if (this.invite_link === '')
            {
                return url;
            }

            return url + this.invite_link + "/join";
        },
    },
    methods: {
        /**
         * Create a new cleanup in the database
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('CREATE_CLEANUP_EVENT', {
                name: this.name,
                date: this.date,
                lat: this.cleanup.lat,
                lon: this.cleanup.lon,
                time: this.time,
                description: this.description,
                invite_link: this.invite_link
            });

            this.processing = false;
        }
    }
}
</script>

<style scoped>

</style>
