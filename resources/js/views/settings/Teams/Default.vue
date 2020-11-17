<template>
    <div>
        <p class="mb1">I want to...</p>

        <div class="flex">
            <button
                class="button is-medium is-info mr1"
                @click="goto('CreateTeam')"
                v-show="remaining > 0"
            >Create a new team</button>

            <button class="button is-medium is-primary" @click="goto('JoinTeam')">Join an existing team</button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Default',
    props: ['remaining'],
    methods: {
        /**
         * Emit method to parent
         */
        goto (type)
        {
            this.$emit('goto', type);
        }
    }
}
</script>

<style scoped>

</style>
