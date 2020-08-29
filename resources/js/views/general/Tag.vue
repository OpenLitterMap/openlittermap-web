<template>
    <section class="hero fullheight is-primary is-bold" style="padding: 1em 5em;">

        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else id="image">
            <div v-if="photos.length == 0" class="hero-body">
                <div class="container has-text-centered">
                    <h3 class="subtitle is-1">You don't have anything to tag at the moment.</h3>
                    <h3 class="subtitle is-3">
                        Please <a href="/submit"><u>upload</u></a>
                        some photos and come back to this page.</h3>
                </div>
            </div>

            <div v-else>
                <div v-for="photo in photos" class="photoid" :id="photo.id">
                <br>
                <h2 class="title is-2" id="photo-id" style="text-align: center;">
                    <strong>#{{ photo.id }}</strong>
                    {{ $t('profile.profile5') }}:
                    {{ getDate(photo.created_at) }}
                </h2>

                <div class="columns">
                    <!-- the info box, Left -->
                    <div class="column" id="image-metadata">
                        <div class="box">
                            <!-- Coordinates -->
                            <p><strong>{{ $t('profile.profile6') }}: </strong>{{ photo.lat }}, {{ photo.lon }}</p>
                            <br>
                            <!-- Full address -->
                            <p><strong>{{ $t('profile.profile7') }}: </strong>{{ photo.display_name }}</p>
                            <br>
                            <!-- Presence button -->
                            <div id="removed">
                                <strong>{{ $t('profile.profile8') }}</strong>
                                <presence :itemsr="user.items_remaining" />
                            </div>
                            <br>
                            <!-- Model of the device -->
                            <p><strong>Device: </strong>{{ photo.model }}</p>
                            <br>
                            <!-- Delete photo button -->
                            <profile-delete :photoid="photo.id" />
                        </div>
                    </div> <!-- end info box -->

                    <!-- the image, Middle -->
                    <div class="column is-6" style="text-align: center;">
                        <!-- The Image -->
                        <img :src="photo.filename" height="420" style="margin-bottom: 20px;" />
                    </div>

                    <!-- Info, Right -->
                    <div class="column is-3" id="image-counts">
                        <div class="box">
                            <li class="list-group-item">{{ $t('profile.profile14') }}: {{ photos.length }}</li>
                            <li class="list-group-item">{{ $t('profile.profile15') }}: {{ user.photos_count }}</li>
                        </div>
                        <h3 style="text-align: center;">{{ $t('profile.profile16') }}:</h3>

                        <added-items />
                    </div>
                </div>

                <div class="columns">
                    <div class="column is-10 is-offset-1">
                        <profile-add :id="photo.id" :itemsr="user.items_remaining" />
                    </div>
                </div>
                <br>
<!--                    <h4 style="color: green;">This image has been verified! No further action is required.</h4>-->

                <div class="column" style="text-align: center;">
<!--                         if($photos->total() > 1)-->
<!--                         paginator -->
<!--                        {{ $photos }}-->
                </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
import moment from 'moment'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import AddedItems from '../../components/Litter/AddedItems'
import ProfileDelete from '../../components/Litter/ProfileDelete'
import ProfileAdd from '../../components/Litter/ProfileAdd'
import Presence from '../../components/Litter/Presence'

export default {
    name: 'Tag',
    components: {
        Loading,
        AddedItems,
        ProfileDelete,
        ProfileAdd,
        Presence
    },
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_PHOTOS_FOR_TAGGING');

        this.loading = false;
    },
    data ()
    {
        return {
            loading: true
        };
    },
    computed: {

        /**
         * Paginated array of the users photos where verification = 0
         */
        photos ()
        {
            return this.$store.state.photos.photos.data;
        },

        /**
         * Number of photos the user has left to verify. Verification = 0
         */
        remaining ()
        {
            return this.$store.state.photos.remaining;
        },

        /**
         * Total number of photos the user has uploaded. Verification = 0-3
         */
        total ()
        {
            return this.$store.state.photos.total;
        },

        /**
         * Currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        }
    },

    methods: {

        /**
         * Format date
         */
        getDate (date)
        {
            return moment(date).format('LL');
        }
    }
}
</script>

<style scoped>

</style>
