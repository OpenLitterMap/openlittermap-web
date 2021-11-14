<template>
    <div>
        <h1 class="title is-4">
            {{ $t('settings.privacy.maps') }}:
        </h1>

        <div class="mb1">
            <!-- Show Name on Maps -->
            <input
                id="settings_maps_change_name"
                v-model="maps_name"
                name="settings_maps_change_name"
                type="checkbox"
            />

            <label class="checkbox" for="settings_maps_change_name">
                {{ $t('settings.privacy.credit-name') }}
            </label>

            <br>

            <!-- Show Username on Maps -->
            <input
                id="settings_maps_change_username"
                v-model="maps_username"
                name="settings_maps_change_username"
                type="checkbox"
            />

            <label class="checkbox" for="settings_maps_change_username">
                {{ $t('settings.privacy.credit-username') }}
            </label>
        </div>

        <!-- Text value to show -->
        <div class="mb1">
            <h1 v-if="maps_name && maps_username" class="success-privacy-text">
                Both your name and username will appear on each image you upload to the maps.
            </h1>

            <h1 v-else-if="maps_name && !maps_username" class="success-privacy-text">
                {{ $t('settings.privacy.name-imgs-yes') }}
            </h1>

            <h1 v-else-if="!maps_name && maps_username" class="success-privacy-text">
                {{ $t('settings.privacy.username-imgs-yes') }}
            </h1>

            <h1 v-else-if="!maps_name && !maps_username" class="failed-privacy-text">
                {{ $t('settings.privacy.name-username-map-no') }}
            </h1>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MapsPrivacy',
    computed: {
        /**
         * Show personal name on any datapoints on any maps the user uploads data to
         */
        maps_name: {
            get () {
                return this.$store.getters.user.show_name_maps;
            },
            set (v) {
                this.$store.commit('changePrivacy', {
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
                return this.$store.getters.user.show_username_maps;
            },
            set (v) {
                this.$store.commit('changePrivacy', {
                    column: 'show_username_maps',
                    v
                });
            }
        }
    }
}
</script>
