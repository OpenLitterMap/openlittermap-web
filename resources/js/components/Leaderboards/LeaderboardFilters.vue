<template>
    <div>
        <div class="leaderboard-filters-container">
            <p
                v-for="option in options"
                class="leaderboard-option"
                :class="option === selected ? 'is-selected' : ''"
                @click="changeOption(option)"
            >
                {{ getNameForOption(option) }}
            </p>
        </div>

        <div class="mobile-filters-container">
            <select
                v-model="selected"
                class="input mb1"
                @change="optionChanged"
            >
                <option
                    v-for="option in options"
                    :value="option"
                >
                    {{ getNameForOption(option) }}
                </option>
            </select>
        </div>
    </div>
</template>

<script>
export default {
    name: "LeaderboardFilters",
    props: [
        'locationType',
        'locationId'
    ],
    data () {
        return {
            processing: false,
            selected: "today",
            options: [
               "all-time",
               "today",
               "yesterday",
               "this-month",
               "last-month",
               "this-year"
            ]
        };
    },
    methods: {
        /**
         * A different option has been selected
         */
        async changeOption (option)
        {
            this.selected = option;

            this.processing = true;

            if (this.locationId && this.locationType)
            {
                await this.$store.dispatch('GET_USERS_FOR_LOCATION_LEADERBOARD', {
                    timeFilter: option,
                    locationId: this.locationId,
                    locationType: this.locationType
                });
            }
            else
            {
                await this.$store.dispatch('GET_USERS_FOR_GLOBAL_LEADERBOARD', option);
            }

            this.processing = false;
        },

        /**
         * Todo: needs translation
         */
        getNameForOption (option) {
            if (option === "today") {
                return "Today";
            }
            else if (option === "yesterday") {
                return "Yesterday";
            }
            else if (option === "this-month") {
                return "This Month";
            }
            else if (option === "last-month") {
                return "Last Month";
            }
            else if (option === "this-year") {
                return "This Year";
            }
            else if (option === "all-time") {
                return "All Time";
            }

            return "";
        },

        /**
         * on mobile view, the option has changed
         */
        async optionChanged (e)
        {
            const option = e.target.value

            this.selected = option;

            this.processing = true;

            await this.$store.dispatch('GET_GLOBAL_LEADERBOARD', option);

            this.processing = false;
        }
    }
}
</script>

<style scoped>

    .leaderboard-filters-container {
        display: flex;
        justify-content: space-evenly;
        max-width: 800px;
        margin: auto;
        padding-bottom: 1em;
    }

    .leaderboard-option {
        border: 1px solid black;
        padding: 10px;
        border-radius: 6px;
        background-color: white;
        color: black;
        cursor: pointer;
    }

    .leaderboard-option.is-selected {
        background-color: #48c774;
    }

    /** DESKTOP */
    @media screen and (min-width: 687px) {
        .mobile-filters-container {
            display: none;
        }
    }

    /** MOBILE */
    @media screen and (max-width: 687px) {
        .leaderboard-filters-container {
            display: none;
        }
    }
</style>
