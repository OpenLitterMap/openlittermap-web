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
                <img class="litter" v-img="{sourceButton: true, openOn: 'dblclick'}" :src="photo.filename" />

                <img
                    v-if="photo.selected"
                    src="/assets/images/checkmark.png"
                    class="grid-checkmark"
                />
            </div>

        </div>

        <div class="flex bottom-actions">
            <div class="bottom-navigation">
                <button
                    class="button is-medium mr1"
                    v-show="this.paginate.prev_page_url"
                    @click="previous"
                >{{$t('common.previous') }}</button>

                <button
                    class="button is-medium mr1"
                    v-show="this.paginate.next_page_url"
                    @click="next"
                >{{$t('common.next') }}</button>

                <div class="photos-info">
                    <div class="info-icon"><i class="fa fa-info"></i></div>
                    <div>{{ $t('profile.dashboard.bulk-tag-dblclick-info') }}</div>
                </div>
            </div>

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
                >{{$t('common.add-tags') }}</button>
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
               title: this.$t('common.add-many-tags') //'Add Many Tags'
            });
        },

        /**
         * Load a modal to confirm delete of the selected photos
         */
        deletePhotos ()
        {
            this.$store.commit('showModal', {
                type: 'ConfirmDeleteManyPhotos',
                title: this.$t('common.confirm-delete') //'Confirm Delete'
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

<style lang="scss" scoped>

    .my-photos-grid-container {
        display: grid;
        grid-template-rows: repeat(5, 1fr);
        grid-template-columns: repeat(6, 1fr);
        grid-row-gap: 0.5em;
        grid-column-gap: 0.5em;
    }

    .my-grid-photo {
        max-height: 10em;
        max-width: 10em;
        position: relative;

        .litter {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 5px;
        }
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

    .photos-info {
        display: flex;
        gap: 8px;
        align-items: center;

        .info-icon {
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            background-color: #00d1b2;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            i {
                margin-top: 2px;
            }
        }
    }

    .bottom-actions {
        margin-top: 16px;
        justify-content: space-between;

        .bottom-navigation {
            display: flex;
            flex-direction: row;
        }
    }

    /* Laptop and above */
    @media (min-width: 1027px)
    {
        .my-photos-grid-container {
            grid-template-rows: repeat(3, 1fr);
            grid-template-columns: repeat(10, 1fr);
            grid-row-gap: 1em;
            grid-column-gap: 1em;
        }
    }

</style>
