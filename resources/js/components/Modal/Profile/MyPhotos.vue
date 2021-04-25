<template>
    <div>
        <FilterMyPhotos />

        <p v-if="loading">Loading...</p>

        <table v-else class="table is-fullwidth is-hoverable has-text-centered">
            <thead>
                <th @click="toggleAll">
                    <input type="checkbox" v-model="selectAll" />
                </th>
                <th>ID</th>
                <th>Filename</th>
                <th>Litter</th>
                <th>Verification</th>
                <th>Taken at</th>
                <th>Uploaded at</th>
            </thead>

            <tbody>
                <tr v-for="photo in photos">

                    <td @click="toggle(photo.id)">
                        <input type="checkbox" v-model="photo.selected" />
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

                    <td>
                        <p>{{ photo.verified }}</p>
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
                <button class="button is-medium is-danger mr1">Delete</button>
                <button class="button is-medium is-primary">Add Tags</button>
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
            loading: true,
            selectAll: false
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
         * Shortcut to paginate object
         */
        paginate ()
        {
            return this.$store.state.photos.paginate;
        },

        /**
         *
         */
        photos ()
        {
            return this.$store.state.photos.paginate.data;
        },

    },
    methods: {

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
         *
         */
        toggle (photo_id)
        {
            this.$store.commit('togglePhotoSelected', photo_id);
        },

        /**
         * Change checkbox value to select all items,
         *
         * Or all filtered items
         */
        toggleAll ()
        {
            this.selectAll = ! this.selectAll;

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
