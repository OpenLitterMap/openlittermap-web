<template>
    <div>
        <h1 class="title is-4">
            {{ $t('settings.privacy.leaderboards') }}:
        </h1>

        <div class="mb1">
            <!-- Show Name on Leaderboards -->
            <input
                id="settings_privacy_leaderboards_name"
                v-model="leaderboard_name"
                name="settings_privacy_leaderboards_name"
                type="checkbox"
            />

            <label class="checkbox" for="settings_privacy_leaderboards_name">
                {{ $t('settings.privacy.credit-my-name') }}
            </label>

            <br>

            <!-- Show Username on Leaderboards -->
            <input
                id="settings_privacy_leaderboards_username"
                v-model="leaderboard_username"
                name="settings_privacy_leaderboards_username"
                type="checkbox"
            />

            <label class="checkbox" for="settings_privacy_leaderboards_username">
                {{ $t('settings.privacy.credit-my-username') }}
            </label>
        </div>

        <!-- Text value to show -->
        <div class="mb1">
            <h1 v-if="leaderboard_name && leaderboard_username" class="success-privacy-text">
                Both your name and username will appear on the Leaderboards. Good luck!
            </h1>

            <h1 v-else-if="leaderboard_name && !leaderboard_username" class="success-privacy-text">
                {{ $t('settings.privacy.name-leaderboards-yes') }}
            </h1>

            <h1 v-else-if="!leaderboard_name && leaderboard_username" class="success-privacy-text">
                {{ $t('settings.privacy.username-leaderboards-yes') }}
            </h1>

            <h1 v-else-if="!leaderboard_name && !leaderboard_username" class="failed-privacy-text">
                {{ $t('settings.privacy.name-username-leaderboards-no') }}
            </h1>
        </div>
    </div>
</template>

<script>
export default {
    name: 'LeaderboardsPrivacy',
    computed: {
        /**
         * Show personal name on any leaderboard the user qualifies for
         */
        leaderboard_name: {
            get () {
                return this.$store.getters.user.show_name;
            },
            set (v) {
                this.$store.commit('changePrivacy', {
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
                return this.$store.getters.user.show_username;
            },
            set (v) {
                this.$store.commit('changePrivacy', {
                    column: 'show_username',
                    v
                });
            }
        }
    }
}
</script>
