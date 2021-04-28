<template>
    <div>
        <FilterMyPhotos />

        <p v-if="loading">Loading...</p>

        <table v-else class="table is-fullwidth is-hoverable has-text-centered">
            <thead>
                <th @click="toggleSelectAll">
                    <input type="checkbox" v-model="selectAll" />
                </th>
                <th>ID</th>
                <th>Filename</th>
                <th>Litter</th>
                <th>Taken at</th>
                <th>Uploaded at</th>
            </thead>

            <tbody>
                <tr v-for="photo in photos">

                    <td @click="toggle(photo.id)">
                        <input
                            type="checkbox"
                            v-model="photo.selected"
                        />
                    </td>

                    <td>
                        <p>{{ photo.id }}</p>
                    </td>

                    <td>
                        <p class="has-text-left">{{ photo.filename }}</p>
                    </td>

                    <td>
                        <p>{{ photo.total_litter }}</p>
                    </td>

                    <td>{{ getDate(photo.datetime) }}</td>

                    <td>{{ getDate(photo.created_at) }}</td>
                </tr>
            </tbody>
        </table>

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
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('LOAD_MY_PHOTOS');

        this.loading = false;
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
         * Toggle the selected-all checkbox, and all photo.selected values
         */
        selectAll: {
            get () {
                return this.$store.state.photos.selectAll;
            },
            set (v) {
                this.$store.commit('selectAllPhotos', v);
            }
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
         * A checkbox was was selected or de-selected
         */
        toggle (photo_id)
        {
            this.$store.commit('togglePhotoSelected', photo_id);
        },

        /**
         *
         */
        toggleSelectAll ()
        {
            this.$store.commit('selectAllPhotos', this.selectAll);
        }
    }
};
</script>

<style scoped>

    .my-photos-buttons {
        display: flex;
        width: 100%;
        justify-content: flex-end;
    }

</style>
