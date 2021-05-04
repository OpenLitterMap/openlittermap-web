<template>
    <div>
        <FilterMyPhotos />

        <div class="my-photos-grid-container">

            <div
                v-for="photo in photos"
                class="my-grid-photo"
                :key="photo.id"
                @click="select(photo.id)"
            >
                <img :src="photo.filename" />

                <img
                    v-if="photo.selected"
                    src="/assets/images/checkmark.png"
                    class="grid-checkmark"
                />
            </div>

        </div>

        <div class="flex">
            <button
                class="button is-medium mr1"
                v-show="this.paginate.prev_page_url"
                @click="previous"
            >Previous</button>

            <button
                class="button is-medium"
                v-show="this.paginate.next_page_url"
                @click="next"
            >Next</button>

            <div class="my-photos-buttons">
<!-- Todo - test this on production -->
<!--                <button-->
<!--                    class="button is-medium is-danger mr1"-->
<!--                    @click="deletePhotos"-->
<!--                    :disabled="selectedCount === 0"-->
<!--                >Delete</button>-->

                <button
                    class="button is-medium is-primary"
                    @click="addTags"
                    :disabled="selectedCount === 0"
                >Add Tags</button>
            </div>
        </div>
    </div>
</template>

<script>
import moment from 'moment';
import FilterMyPhotos from '../../Profile/bottom/MyPhotos/FilterMyPhotos';

export default {
    name: 'MyPhotos',
    components: {
        FilterMyPhotos
    },
    data () {
        return {
            loading: true
        };
    },
    computed: {

        /**
         * Class to show when calender is open
         */
        calendar ()
        {
            return this.showCalendar
                ? 'dropdown is-active mr1'
                : 'dropdown mr1';
        },

        /**
         * Shortcut to pagination object
         */
        paginate ()
        {
            return this.$store.state.photos.paginate;
        },

        /**
         * Array of paginated photos
         */
        photos ()
        {
            return this.paginate.data;
        },

        /**
         * Number of photos that have been selected
         */
        selectedCount ()
        {
            return this.$store.state.photos.selectedCount;
        }
    },
    methods: {

        /**
         * Load a modal to add 1 or more tags to the selected photos
         */
        addTags ()
        {
            this.$store.commit('showModal', {
               type: 'AddManyTagsToManyPhotos',
               title: 'Add Many Tags'
            });
        },

        /**
         * Load a modal to confirm delete of the selected photos
         */
        deletePhotos ()
        {
            this.$store.commit('showModal', {
                type: 'ConfirmDeleteManyPhotos',
                title: 'Confirm Delete'
            });
        },

        /**
         * Return formatted date
         */
        getDate (date)
        {
            return moment(date).format('LL');
        },

        /**
         * Load the previous page of photos
         */
        previous ()
        {
            this.$store.dispatch('PREVIOUS_PHOTOS_PAGE');
        },

        /**
         * Load the next page of photos
         */
        next ()
        {
            this.$store.dispatch('NEXT_PHOTOS_PAGE');
        },

        /**
         * A photo was was selected or de-selected
         */
        select (photo_id)
        {
            this.$store.commit('togglePhotoSelected', photo_id);
        }
    }
};
</script>

<style scoped>

    .my-photos-grid-container {
        display: grid;
        grid-template-rows: repeat(3, 1fr);
        grid-template-columns: repeat(8, 1fr);
        grid-row-gap: 1em;
        grid-column-gap: 1em;
    }

    .my-grid-photo {
        max-height: 10em;
        max-width: 10em;
        position: relative;
    }

    .grid-checkmark {
        position: absolute;
        height: 3em;
        bottom: 0;
        right: 0;
        border: 5px solid #0ca3e0;
        border-radius: 50%;
        padding: 5px;
    }

    .my-photos-buttons {
        display: flex;
        width: 100%;
        justify-content: flex-end;
    }

</style>
