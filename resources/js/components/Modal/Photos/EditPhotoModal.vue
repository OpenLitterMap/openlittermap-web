<template>
    <div>
       tags go here {{ photo.tags }}
        {{ photo.custom_tags }}
    </div>
</template>

<script>
export default {
    name: 'EditPhotoModal',
    computed: {
        tags ()
        {
            return this.$store.state.litter.recentTags;
        },

        customTags ()
        {
            return this.$store.state.litter.recentCustomTags;
        },

        photo ()
        {
            const photoId = this.$store.state.photos.chosenPhotoId;
            return this.$store.state.photos.paginate.data.find(p => p.id === photoId);
        },
    },
    methods: {

    },
};
</script>

<style scoped>

</style>
