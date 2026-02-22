<template>
    <div>
        <h1 class="title is-4">
            {{ $t('settings.privacy.created-by') }}:
        </h1>

        <div class="mb1">

            <!-- Show Name on location.created_by -->
            <input
                id="settings_privacy_createdby_name"
                v-model="createdby_name"
                name="settings_privacy_createdby_name"
                type="checkbox"
            />

            <label class="checkbox" for="settings_privacy_createdby_name">
                {{ $t('settings.privacy.credit-name') }}
            </label>

            <br>

            <!-- Show Username on Location.created_by -->
            <input
                id="settings_privacy_createdby_username"
                v-model="createdby_username"
                name="settings_privacy_createdby_username"
                type="checkbox"
            >

            <label class="checkbox" for="settings_privacy_createdby_username">
                {{ $t('settings.privacy.credit-username') }}
            </label>
        </div>

        <!-- Text value to show -->
        <div class="mb1">
            <h1 v-if="createdby_name && createdby_username" class="success-privacy-text">
                Both your name and username will appear in the Created By section of any new locations you create by being the first to upload.
            </h1>

            <h1 v-else-if="createdby_name && !createdby_username" class="success-privacy-text">
                {{ $t('settings.privacy.name-locations-yes') }}
            </h1>

            <h1 v-else-if="!createdby_name && createdby_username" class="success-privacy-text">
                {{ $t('settings.privacy.username-locations-yes') }}
            </h1>

            <!-- The key here is wrong, but the value is correct -->
            <h1 v-else-if="!createdby_name && !createdby_username" class="failed-privacy-text">
                {{ $t('settings.privacy.name-username-locations-yes') }}
            </h1>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CreatedByPrivacy',
    computed: {
        /**
         * Show personal name on the createdBy sections of any locations the user added
         */
        createdby_name: {
            get () {
                return this.$store.getters.user.show_name_createdby;
            },
            set (v) {
                this.$store.commit('changePrivacy', {
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
                return this.$store.getters.user.show_username_createdby;
            },
            set (v) {
                this.$store.commit('changePrivacy', {
                    column: 'show_username_createdby',
                    v
                });
            }
        }
    }
}
</script>
