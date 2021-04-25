<template>
    <div class="profile-card">
        <p class="mb1">View your photos, select multiple, delete them or add tags!</p>

        <button class="button is-medium is-primary" @click="load">View my Photos</button>
    </div>
</template>

<script>
export default {
    name: "ProfilePhotos",
    methods: {

        /**
         * Load a modal to view the users photos
         */
        load ()
        {
            this.$store.commit('showModal', {
                type: 'MyPhotos',
                title: 'View My Photos'
            });
        }
    }
}
</script>

<style scoped>

</style>
