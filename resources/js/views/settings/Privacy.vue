<template>
    <div>
        <h1 class="title is-4">
            {{ $t('settings.privacy.change-privacy') }}
        </h1>
        <hr>
        <div>
            <div class="field">
                <!-- Maps -->
                <h1 class="title is-4">
                    {{ $t('settings.privacy.maps') }}:
                </h1>
                <label class="checkbox">
                    <input v-model="maps_name" type="checkbox">
                    {{ $t('settings.privacy.credit-name') }}
                </label>
                <br>
                <label class="checkbox">
                    <input v-model="maps_username" type="checkbox">
                    {{ $t('settings.privacy.credit-username') }}
                </label>
                <br>
                <br>
                <h1 v-show="maps_name" class="title is-6" style="margin-bottom: 5px;">
                    <strong style="color: green;">
                        {{ $t('settings.privacy.name-imgs-yes') }}.
                    </strong>
                </h1>
                <h1 v-show="maps_username" class="title is-6">
                    <strong style="color: green;">
                        {{ $t('settings.privacy.username-imgs-yes') }}.
                    </strong>
                </h1>
                <br v-show="maps_name || maps_username">

                <h1 v-show="! maps_name && ! maps_username" class="title is-6">
                    <strong style="color: red;">
                        {{ $t('settings.privacy.name-username-map-no') }}.
                    </strong>
                </h1>

                <!-- Leaderboards -->
                <h1 class="title is-4">
                    {{ $t('settings.privacy.leaderboards') }}:
                </h1>
                <label class="checkbox">
                    <input v-model="leaderboard_name" type="checkbox">
                    {{ $t('settings.privacy.credit-my-name') }}
                </label>
                <br>
                <label class="checkbox">
                    <input v-model="leaderboard_username" type="checkbox">
                    {{ $t('settings.privacy.credit-my-username') }}
                </label>
                <br>
                <br>
                <h1 v-show="leaderboard_name" class="title is-6" style="margin-bottom: 5px;">
                    <strong style="color: green;">
                        {{ $t('settings.privacy.name-leaderboards-yes') }}.
                    </strong>
                </h1>
                <h1 v-show="leaderboard_username" class="title is-6">
                    <strong style="color: green;">
                        {{ $t('settings.privacy.username-leaderboards-yes') }}.
                    </strong>
                </h1>
                <br v-show="leaderboard_name || leaderboard_username">

                <h1 v-show="! leaderboard_name && ! leaderboard_username"
                    class="title is-6"
                >
                    <strong style="color: red;">
                        {{ $t('settings.privacy.name-username-leaderboards-no') }}.
                    </strong>
                </h1>

                <!-- Created By -->
                <h1 class="title is-4">
                    {{ $t('settings.privacy.created-by') }}:
                </h1>
                <label class="checkbox">
                    <input v-model="createdby_name" type="checkbox">
                    {{ $t('settings.privacy.name-locations-yes') }}
                </label>
                <br>
                <label class="checkbox">
                    <input v-model="createdby_username" type="checkbox">
                    {{ $t('settings.privacy.username-locations-yes') }}
                </label>
                <br>
                <br>
                <h1 v-show="createdby_name" class="title is-6" style="margin-bottom: 5px;">
                    <strong style="color: green;">
                        {{ $t('settings.privacy.name-username-locations-yes') }}
                    </strong>
                </h1>
                <h1 v-show="createdby_username" class="title is-6" style="margin-bottom: 5px;">
                    <strong style="color: green;">
                        {{ $t('settings.privacy.name-username-locations-yes') }}.
                    </strong>
                </h1>
                <br v-show="createdby_name || createdby_username">
                <h1 v-show="! createdby_name && ! createdby_username"
                    class="title is-6"
                >
                    <strong style="color: red;">
                        {{ $t('settings.privacy.name-username-locations-yes') }}.
                    </strong>
                </h1>
            </div>
            <div class="col-md-12 has-text-centered">
                <button :class="button" :disabled="processing" @click="submit">
                    {{ $t('settings.privacy.update') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Privacy',
    data ()
    {
        return {
            btn: 'button is-normal is-info',
            processing: false
        };
    },
    computed: {


        /**
         * Add ' is-loading' when processing
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

        /**
         * Show personal name on the createdBy sections of any locations the user added
         */
        createdby_name: {
            get () {
                return this.user.show_name_createdby;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_name_createdby',
                    v
                });
            }
        },

        /**
         * Show username on the createdBy sections of any locations the user added
         */
        createdby_username: {
            get () {
                return this.user.show_username_createdby;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_username_createdby',
                    v
                });
            }
        },

        /**
         * Show personal name on any leaderboard the user qualifies for
         */
        leaderboard_name: {
            get () {
                return this.user.show_name;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_name',
                    v
                });
            }
        },

        /**
         * Show username on any leaderboard the user qualifies for
         */
        leaderboard_username: {
            get () {
                return this.user.show_username;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_username',
                    v
                });
            }
        },

        /**
         * Show personal name on any datapoints on any maps the user uploads data to
         */
        maps_name: {
            get () {
                return this.user.show_name_maps;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_name_maps',
                    v
                });
            }
        },

        /**
         * Show username on any datapoints on any maps the user uploads data to
         */
        maps_username: {
            get () {
                return this.user.show_username_maps;
            },
            set (v) {
                this.$store.commit('privacy', {
                    column: 'show_username_maps',
                    v
                });
            }
        },

        /**
         * Currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        }
    },
    methods: {

        /**
         * Dispatch request to save all settings
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('SAVE_PRIVACY_SETTINGS');

            this.processing = false;
        }
    }
};
</script>
