<template>
    <div class="filters">
        <div class="filter-item">
            <label for="filterTag">
                Tag
            </label>

            <input
                id="filterTag"
                name="filterTag"
                class="input"
                v-model="filterTag"
                placeholder="Enter a tag"
            />
        </div>

        <div class="filter-item">
            <label for="filterCustomTag">
                Custom Tag
            </label>

            <input
                id="filterCustomTag"
                name="filterCustomTag"
                class="input"
                v-model="filterCustomTag"
                placeholder="Enter a custom tag"
            />
        </div>

        <div class="filter-item">
            <label for="uploadedFrom">
                Uploaded From
            </label>
            <input
                id="uploadedFrom"
                name="uploadedFrom"
                class="input"
                type="date"
                v-model="filterDateFrom"
                placeholder="From"
            />
        </div>

        <div class="filter-item">
            <label for="uploadedTo">
                Uploaded To
            </label>
            <input
                id="uploadedTo"
                name="uploadedTo"
                class="input"
                type="date"
                v-model="filterDateTo"
                placeholder="To"
            />
        </div>

        <div
            v-if="parent === 'global'"
            class="filter-item"
        >
            <label for="filterCountry">
                Country
            </label>
            <select
                class="input"
                v-model="filterCountry"
            >
                <option value="all" selected>All</option>

                <option
                    v-for="country in countries"
                    :key="country.id"
                    :value="country.id"
                >{{ country.country }}</option>
            </select>
        </div>

        <div class="filter-item">
            <label for="uploadedTo">
                Amount
            </label>
            <select
                class="input"
                v-model="paginationAmount"
            >
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>

        <button
            class="button is-small is-primary"
            @click="getData"
            style="margin-top: 25px;"
        >Apply Filters</button>
    </div>
</template>

<script>
export default {
    name: "FilterPhotos",
    props: [
        'action',
        'parent'
    ],
    methods: {
        /**
         * Actions include
         * - GET_MY_PHOTOS
         * - GET_ALL_PHOTOS_PAGINATED
         */
        async getData () {
            await this.$store.dispatch(this.action);
        }
    },
    computed: {
        countries () {
            return this.$store.state.locations.countryNames;
        },

        filterTag: {
            get () {
                return this.$store.state.user.filterPhotos.filterTag;
            },
            set (v) {
                this.$store.commit('setFilterTag', v);
            }
        },

        filterCustomTag: {
            get () {
                return this.$store.state.user.filterPhotos.filterCustomTag;
            },
            set (v) {
                this.$store.commit('setFilterCustomTag', v);
            }
        },

        filterDateFrom: {
            get () {
                return this.$store.state.user.filterPhotos.filterDateFrom;
            },
            set (v) {
                this.$store.commit('setFilterDateFrom', v);
            }
        },

        filterDateTo: {
            get () {
                return this.$store.state.user.filterPhotos.filterDateTo;
            },
            set (v) {
                this.$store.commit('setFilterDateTo', v);
            }
        },

        filterCountry: {
            get () {
                return this.$store.state.user.filterPhotos.filterCountry;
            },
            set (v) {
                this.$store.commit('setFilterCountry', v);
            }
        },

        paginationAmount: {
            get () {
                return this.$store.state.user.filterPhotos.paginationAmount;
            },
            set (v) {
                this.$store.commit('setFilterPhotosPaginationAmount', v);
            }
        }
    }
}
</script>

<style scoped>

</style>
