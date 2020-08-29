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
    // Accept users db value. Is their litter usually picked up (0), or still there (1)?
    props: ['itemsr'], // itemsremaining
    mounted() {
        // only do this once. seems to be logging twice and itemsr is `null` on second iteration
        let x = null;
        if (this.itemsr == "0") {
            x = true;
        } else {
            x = false;
        }

        if (this.$store.state.litter.presence == null) {
            this.$store.commit('initPresence', x);
        }
    },
    methods: {
        togglePresence() {
            this.$store.commit('togglePresence');
        }
    },
    computed: {
        remaining() {
            return this.$store.state.litter.presence;
        },
        remainingText() {
            return this.$store.state.litter.presence ? "It's gone." : "The litter is still there!";
        }
    }
}
</script>
