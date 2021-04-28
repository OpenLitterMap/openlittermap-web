<template>
    <div>
        <p>Are you sure you want to delete {{ this.$store.state.photos.selectedCount }} photos?</p>

        <button class="button is-medium is-danger" @click="confirm">Delete</button>
    </div>
</template>

<script>
export default {
    name: 'ConfirmDeleteManyPhotos',
    methods: {

        async confirm ()
        {
            await this.$store.dispatch('DELETE_SELECTED_PHOTOS');
        }
    }
};
</script>

<style scoped>

</style>
