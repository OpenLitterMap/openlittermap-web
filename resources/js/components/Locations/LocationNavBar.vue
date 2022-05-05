<template>
    <div class="container w100">
        <br>
        <div class="control locations-control">
            <div class="select">
                <select v-model="sortLocationsBy">
                    <option
                        v-for="opt in options"
                        :key="opt.value"
                        :value="opt.value"
                    >{{ opt.text }}</option>
                </select>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'LocationNavbar',
    data () {
        return {
            'options': [
                {
                    text: 'A-Z',
                    value: 'alphabetical'
                },
                {
                    text: this.$t('location.most-data'),
                    value: 'most-data'
                },
                {
                    text: this.$t('location.most-data-person'),
                    value: 'most-data-per-person'
                },
                {
                    text: 'Total Contributors',
                    value: 'total-contributors'
                },
                {
                    text: 'First Created',
                    value: 'first-created'
                },
                {
                    text: 'Most Recently Created',
                    value: 'most-recently-created'
                },
                {
                    text: 'Most Recently Updated',
                    value: 'most-recently-updated'
                }
            ]
        }
    },
    computed: {
        /**
         * Key to sort the locations by
         */
        sortLocationsBy: {
            get () {
                return this.$store.state.locations.sortLocationsBy;
            },
            set (v) {
                this.$store.commit('setSortLocationsBy', v);
            }
        }
    }
}
</script>

<style scoped>

    .locations-control {
        text-align: right;
    }

    /* Small devices */
    @media screen and (max-width: 768px) {
        .locations-control {
            text-align: center;
        }
    }
</style>
