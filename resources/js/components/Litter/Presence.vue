<template>
    <div class="expand-mobile">
        <strong :style="{ color: pickedUp ? 'green' : 'red' }"><slot>{{ pickedUpText }}</slot></strong>
        <br>
        <button :class="toggle_class" @click="toggle">
            <slot v-if="pickedUp">{{ $t('litter.presence.still-there') }}</slot>
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
        pickedUp ()
        {
            return this.$store.state.litter.pickedUp;
        },

        /**
         *
         */
        pickedUpText ()
        {
            return this.pickedUp ? this.$t('litter.presence.picked-up-text') : this.$t('litter.presence.still-there-text');
        },

        /**
         * Class to show if litter is still there, or picked up
         */
        toggle_class ()
        {
            return this.pickedUp ? 'button is-danger' : 'button is-success';
        }
    },
    methods: {

        /**
         * Toggle the presence of the litter
         */
        toggle ()
        {
            this.$store.commit('togglePickedUp');
        }
    }
}
</script>
