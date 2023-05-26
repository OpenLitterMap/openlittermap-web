<template>
    <div>
        <h1 class="title is-4">
            Prevent others tagging my photos:
        </h1>

        <div class="mb1">
            <input
                id="settings_privacy_prevent_others_tagging_my_photos"
                v-model="prevent_others_tagging_my_photos"
                name="settings_privacy_prevent_others_tagging_my_photos"
                type="checkbox"
            />

            <label class="checkbox" for="settings_privacy_prevent_others_tagging_my_photos">
                Prevent others tagging my photos
            </label>
        </div>
    </div>
</template>

<script>
export default {
    name: 'PreventOthersTaggingMyPhotos',
    computed: {
        /**
         * Prevent admins or other people from tagging your photos for you
         */
        prevent_others_tagging_my_photos: {
            get () {
                return this.$store.getters.user.prevent_others_tagging_my_photos;
            },
            set (v) {
                this.$store.commit('changePrivacy', {
                    column: 'prevent_others_tagging_my_photos',
                    v
                });
            }
        },
    }
}
</script>
