<template>
    <div class="container mt4 progress-bar">
        <div>
            <!-- XP bar variables -->
            <div class="flex mb1">
                <h4 class="flex-1 has-text-white">
                    {{ $t('location.previous-target') }}:
                    <br>
                    <strong class="has-text-white">
                        {{ this.previousXp | commas }} {{ $t('location.litter') }}
                    </strong>
                </h4>
                <h4 class="has-text-white">{{ $t('location.next-target') }}:
                    <br>
                    <strong class="has-text-white">
                        {{ this.nextXp | commas }} {{ $t('location.litter') }}
                    </strong>
                </h4>
            </div>

            <ProgressBar
                :currentxp="total_litter"
                :startingxp="previousXp"
                :xpneeded="nextXp"
                class="mb1em"
            />

            <p
                v-if="loading"
                class="has-text-centered has-text-white mb2"
            >...%</p>

            <p
                v-else
                class="has-text-centered has-text-white mb2"
            >{{ this.progress }}%</p>
        </div>
    </div>
</template>

<script>
import ProgressBar from '../ProgressBar.vue';

export default {
    name: "Progress",
    props: [
        'loading'
    ],
    components: {
        ProgressBar
    },
    methods: {
        /**
         * Format number value
         */
        commas (n)
        {
            return parseInt(n).toLocaleString();
        }
    },
    computed: {
        /**
         * Total littercoin owed to users for proof of citizen science
         */
        littercoin ()
        {
            return this.$store.state.locations.littercoin;
        },

        /**
         * The amount of XP we achieved at the current level
         */
        previousXp ()
        {
            return this.$store.state.locations.level.previousXp;
        },

        /**
         * The amount of XP we need to reach the next level
         */
        nextXp ()
        {
            return this.$store.state.locations.level.nextXp;
        },

        /**
         * The total amount of verified litter all users have uploaded
         */
        total_litter ()
        {
            return this.$store.state.locations.total_litter;
        },

        /**
         * % between currentLevel and nextLevel
         */
        progress ()
        {
            let range = this.nextXp - this.previousXp;

            let startVal = this.total_litter - this.previousXp;

            return ((startVal * 100) / range).toFixed(2); // percentage
        },

        /**
         * The total number of verified photos all users have uploaded
         */
        total_photos ()
        {
            return this.$store.state.locations.total_photos;
        }
    }
}
</script>

<style scoped>
    .progress-bar {
        max-width: 600px;
    }
</style>
