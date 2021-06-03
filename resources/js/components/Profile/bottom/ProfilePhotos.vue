<template>
    <div class="profile-card">
        <p class="mb1">{{ $t('profile.dashboard.manage-my-photos') }}</p>

        <button class="button is-medium is-primary" @click="load">{{ $t('profile.dashboard.view-my-photos') }}</button>
    </div>
</template>

<script>
export default {
    name: "ProfilePhotos",
    methods: {

        /**
         * Load a modal to view the users photos
         */
        async load ()
        {
            this.$store.commit('resetPhotoState');

            await this.$store.dispatch('LOAD_MY_PHOTOS');

            this.$store.commit('showModal', {
                type: 'MyPhotos',
                title: this.$t('profile.dashboard.my-photos') //'My Photos'
            });
        }
    }
}
</script>

<style scoped>

</style>
