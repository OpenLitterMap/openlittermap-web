<template>
    <div class="expand-mobile">
        <strong :style="{ color: remaining ? 'green' : 'red' }"><slot>{{ remainingText }}</slot></strong>
        <br>
        <button class="button" :class="{ 'is-danger': remaining, 'is-success': ! remaining }" @click="togglePresence">
            <slot v-if="remaining">It's still there.</slot>
            <slot v-else>I picked it up!</slot>
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
        }
    },
    methods: {

        /**
         *
         */
        toggle ()
        {
            this.$store.commit('togglePresence');
        }
    }
}
</script>
