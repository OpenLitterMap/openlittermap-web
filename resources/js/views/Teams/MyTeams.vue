<template>
    <section>
        <div>
            <p>My Teams</p>

            <div v-if="user.team" class="mb2" :key="user.team.id">
                <p>You are currently joined team {{ user.team.name }}</p>
            </div>

            <div v-else class="mb2">
                <p>You are not currently joined a team</p>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'MyTeams',

    computed: {

        /**
         * Current user
         */
        user ()
        {
            return this.$store.state.user.user;
        }
    }
}
</script>

<style scoped>

</style>
