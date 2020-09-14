<template>
    <div class="expand-mobile">
        <strong :style="{ color: remaining ? 'green' : 'red' }"><slot>{{ remainingText }}</slot></strong>
        <br>
        <button :class="toggle_class" @click="toggle">
            <slot v-if="remaining">{{ $t('litter.presence.still-there') }}</slot>

            <slot v-else>{{ $t('litter.presence.picked-up') }}</slot>
        </button>
    </div>
</template>

<script>
export default {
    name: 'Presence',
    computed: {

        /**
         * Change setting name to "picked_up"
         */
        remaining ()
        {
            return this.$store.state.litter.presence;
        },

        /**
         *
         */
        remainingText ()
        {
            return this.$store.state.litter.presence ? "It's gone." : "The litter is still there!";
        },

        /**
         * Class to show if litter is still there, or picked up
         */
        toggle_class ()
        {
            return this.remaining ? 'button is-danger' : 'button is-success';
        }
    },
    methods: {

        /**
         * Toggle the presense of the litter
         */
        toggle ()
        {
            this.$store.commit('togglePresence');
        }
    }
}
</script>
