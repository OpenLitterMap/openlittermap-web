<template>
    <section class="hero fullheight is-primary is-bold" style="padding: 2em 5em 0em 5em;">

        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <div v-else>

            <!-- No Image available for tagging -->
            <div v-if="photos.length == 0" class="hero-body">
                <div class="container has-text-centered">
                    <h3 class="subtitle is-1">{{ $t('tags.no-tags') }}</h3>
                    <h3 class="subtitle is-3">
                        <router-link to="/submit">
                            {{ $t('tags.please-upload') }}
                        </router-link>
                    </h3>
                </div>
            </div>

            <!-- Image is available for tagging -->
            <div v-else>

                <div v-for="photo in photos" class="mb2">

                    <h2 class="title is-2 mb1 has-text-centered">
                        <strong>#{{ photo.id }}</strong>
                        <!-- was profile.profile5 "Uploaded". now "taken on" -->
                        {{ $t('tags.taken') }}: {{ getDate(photo.created_at) }}
                    </h2>

                    <div class="columns">
                        <!-- the info box, Left -->
                        <div class="column" id="image-metadata">
                            <div class="box">
                                <!-- Coordinates. was profile6 -->
                                <p><strong>{{ $t('tags.coordinates') }}: </strong>{{ photo.lat }}, {{ photo.lon }}</p>
                                <br>
                                <!-- Full address. was profile7 -->
                                <p><strong>{{ $t('tags.address') }}: </strong>{{ photo.display_name }}</p>
                                <br>
                                <!-- Presence button. was profile8 -->
                                <div>
                                    <strong>{{ $t('tags.picked-up-title') }}</strong>
                                    <presence />
                                </div>
                                <br>
                                <!-- Model of the device -->
                                <p><strong>{{ $t('tags.device') }}: </strong>{{ photo.model }}</p>
                                <br>
                                <!-- Delete photo button -->
                                <profile-delete :photoid="photo.id" />
                            </div>
                        </div> <!-- end info box -->

                        <!-- The Image, Middle -->
                        <div class="column is-6" style="text-align: center;">
                            <!-- The Image -->
                            <img :src="photo.filename" style="max-height: 30em;" />
                        </div>

                        <!-- Info, Tags, Right -->
                        <div class="column is-3" id="image-counts">
                            <div class="box">
                                <!-- was profile14, 15-->
                                <li class="list-group-item">{{ $t('tags.to-tag') }}: {{ photos.length }}</li>
                                <li class="list-group-item">{{ $t('tags.total-uploaded') }}: {{ user.photos_count }}</li>
                            </div>

                            <!-- These are the tags the user has added -->
                            <tags />
                        </div>
                    </div>

                    <div class="columns">
                        <div class="column is-10 is-offset-1">
                            <add-tags :id="photo.id" />
                        </div>
                    </div>
                    <br>
                    <!-- <h4 style="color: green;">This image has been verified! No further action is required.</h4>-->

                    <div class="column" style="text-align: center;">
                        <!-- if($photos->total() > 1)-->
                        <!-- paginator -->
                        <!-- {{ $photos }}-->
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
import AddTags from '../../components/Litter/AddTags'
import ProfileDelete from '../../components/Litter/ProfileDelete'
import Presence from '../../components/Litter/Presence'
import Tags from '../../components/Litter/Tags'

export default {
    name: 'Tag',
    components: {
        Loading,
        AddTags,
        ProfileDelete,
        Presence,
        Tags
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
            return moment(date).format('LLL');
        }
    }
}
</script>

<style scoped>

</style>
