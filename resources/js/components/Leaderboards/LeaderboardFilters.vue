<template>
    <div class="mb1">
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

        <nav>
            <menu>

                <!-- OPTIONS -->
                <menuitem
                    v-for="option in options"
                    @click="changeOption(option)"
                >
                    <a
                        :class="option === selected ? 'is-selected' : ''"
                    >
                        {{ getNameForOption(option) }}
                    </a>
                </menuitem>

                <!-- MONTHS -->
                <menuitem>
                    <a
                        :class="selectedMonth === selected ? 'is-selected' : ''"
                    >
                        {{ getNameForSelectedMonth }}
                    </a>
                    <menu>
                        <menuitem
                            v-for="month in availableMonths"
                            :value="month"
                            :key="month"
                            @click="selectMonth(month)"
                        >
                            <a>{{ month }}</a>
                        </menuitem>
                    </menu>
                </menuitem>

                <!-- YEARS -->
                <menuitem>
                    <a
                        :class="selectedYear === selected ? 'is-selected' : ''"
                    >
                        {{ this.selectedYear }}
                    </a>
                    <menu>
                        <menuitem
                            v-for="year in previousYearsOptions"
                            :value="year"
                            :key="year"
                            @click="getYear(year)"
                        >
                            <a>{{ year }}</a>
                        </menuitem>
                    </menu>
                </menuitem>
            </menu>
        </nav>
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
            selectedYear: new Date().getFullYear(),
            selectedMonth: null,
            options: [
               "all-time",
               "today",
               "yesterday",
               "this-month",
               "last-month",
            ],
            monthNames: [
                "January",
                "February",
                "March",
                "April",
                "May",
                "June",
                "July",
                "August",
                "September",
                "October",
                "November",
                "December"
            ],

        };
    },
    computed: {
        /**
         * Available months
         */
        availableMonths ()
        {
            const currentYear = new Date().getFullYear();
            const currentMonth = new Date().getMonth() + 1;
            const selectedYear = this.selectedYear;
            const months = [];

            if (selectedYear === currentYear)
            {
                for (let i = 0; i < currentMonth; i++)
                {
                    months.push(this.monthNames[i]);
                }
            }
            else if (selectedYear < currentYear)
            {
                months.push(...this.monthNames);
            }

            console.log({months});

            return months;
        },

        /**
         * Get name for selected month
         */
        getNameForSelectedMonth ()
        {
            if (this.selectedMonth)
            {
                const selectedMonthIndex = this.monthNames.indexOf(this.selectedMonth);
                return this.monthNames[selectedMonthIndex];
            }

            const currentMonth = new Date().getMonth();

            return this.monthNames[currentMonth];
        },

        /**
         * Get previous years options
         */
        previousYearsOptions() {
            const currentYear = new Date().getFullYear();
            const startYear = 2017; // Start year for the options
            const availableYears = [];

            for (let year = startYear; year <= currentYear; year++)
            {
                availableYears.push(year);
            }

            return availableYears;
        },
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
            else if (option === "all-time") {
                return "All Time";
            }

            return "";
        },

        /**
         * Get the leaderboard data for the selected year
         */
        getYear (year)
        {
            this.selected = year;

            this.selectedYear = year;

            this.$store.dispatch('GET_USERS_FOR_LOCATION_LEADERBOARD', {
                year
            });
        },

        /**
         * on mobile view, the option has changed
         */
        async optionChanged (e)
        {
            const option = e.target.value

            this.selected = option;

            this.selectedYear = null;

            this.processing = true;

            await this.$store.dispatch('GET_USERS_FOR_GLOBAL_LEADERBOARD', option);

            this.processing = false;
        },

        /**
         *
         */
        selectMonth(month)
        {
            this.selected = month;

            this.selectedMonth = month;
            console.log({month});
        },
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
        nav {
            display: none;
        }
    }

    html, body{
        padding:0px;
        margin:0px;
        background:#191A1D;
        font-family: 'Karla', sans-serif;
        width:100vw;
    }
    body * {
        margin:0;
        padding:0;
    }

    /* HTML Nav Styles */
    /* HTML Nav Styles */
    /* HTML Nav Styles */
    nav menuitem {
        position:relative;
        display:block;
        opacity:0;
        cursor:pointer;
        z-index: 9;
    }

    nav menuitem > menu {
        position: absolute;
        pointer-events:none;
    }
    nav > menu {
        display:flex;
        justify-content: center;
    }

    nav > menu > menuitem { pointer-events: all; opacity:1; }
    menu menuitem a { white-space:nowrap; display:block; }

    menuitem:hover > menu {
        pointer-events:initial;
    }
    menuitem:hover > menu > menuitem,
    menu:hover > menuitem{
        opacity:1;
    }
    nav > menu > menuitem menuitem menu {
        transform:translateX(100%);
        top:0; right:0;
    }
    /* User Styles Below Not Required */
    /* User Styles Below Not Required */
    /* User Styles Below Not Required */

    nav {
        margin-top: 40px;
    }

    nav a {
        background: #ffffff;
        color: black !important;
        transition: background 0.5s, color 0.5s, transform 0.5s;
        margin:0px 6px 6px 0px;
        padding: 10px 35px;
        box-sizing:border-box;
        border-radius:3px;
        box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.5);
        position:relative;
    }

    nav a.is-selected {
        background-color: #48c774 !important;
    }

    nav a:hover {
        background: #48c774;
    }

    /*nav > menu > menuitem > a + menu:after{*/
    /*    content: '';*/
    /*    position:absolute;*/
    /*    border:10px solid transparent;*/
    /*    border-top: 10px solid #3273dc;*/
    /*    left:12px;*/
    /*    top: -32px;*/
    /*}*/
    nav menuitem > menu > menuitem > a + menu:after{
        content: '';
        position:absolute;
        border:10px solid transparent;
        border-left: 10px solid white;
        top: 20px;
        left:-180px;
        transition: opacity 0.6, transform 0s;
    }

    nav > menu > menuitem > menu > menuitem{
        transition: transform 0.6s, opacity 0.6s;
        transform:translateY(150%);
        opacity:0;
        z-index: 999;
    }
    nav > menu > menuitem:hover > menu > menuitem,
    nav > menu > menuitem.hover > menu > menuitem{
        transform:translateY(0%);
        opacity: 1;
    }

    menuitem > menu > menuitem > menu > menuitem{
        transition: transform 0.6s, opacity 0.6s;
        transform:translateX(195px) translateY(0%);
        opacity: 0;
    }
    menuitem > menu > menuitem:hover > menu > menuitem,
    menuitem > menu > menuitem.hover > menu > menuitem{
        transform:translateX(0) translateY(0%);
        opacity: 1;
    }





</style>
