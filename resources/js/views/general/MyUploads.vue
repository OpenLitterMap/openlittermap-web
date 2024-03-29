<template>
    <LitterTable
        title="My Uploads"
        :paginatedPhotos="paginatedPhotos"
        action="GET_MY_PHOTOS"
    />
</template>

<script>
import LitterTable from "../../components/Litter/LitterTable";

export default {
    name: "MyUploads",
    components: {
        LitterTable,
    },
    async created ()
    {
        await this.$store.dispatch('GET_MY_PHOTOS');
    },
    computed: {
        /**
         * The users paginated photos
         */
        paginatedPhotos ()
        {
            return this.$store.state.photos.myUploadsPaginate;
        },
    }
}
</script>
