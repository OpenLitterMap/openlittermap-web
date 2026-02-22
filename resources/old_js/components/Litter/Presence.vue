<template>
    <div class="switch-container">
        <p class="mr-2"><strong>{{ $t('tags.picked-up-title') }}</strong></p>
        <label class="switch">
            <input type="checkbox" :checked="pickedUp" @change="toggle">
            <span class="slider round"></span>
        </label>
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

<style lang="scss" scoped></style>
