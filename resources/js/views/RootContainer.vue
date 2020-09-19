<template>
    <div class="root-container">
        <Nav />

        <Modal v-show="modal" />

        <router-view />
    </div>
</template>

<script>
import Nav from '../components/General/Nav'
import Modal from '../components/Modal/Modal'

export default {
    name: 'RootContainer',
    props: ['auth', 'user'],
    components: {
        Nav,
        Modal
    },
    created ()
    {
        if (this.auth)
        {
            this.$store.commit('login');

            // user object is passed when the page is refreshed
            if (this.user)
            {
                const u = JSON.parse(this.user);
                this.$store.commit('initUser', u);
                this.$store.commit('set_default_litter_presence', u.items_remaining);
            }
        }
    },
    computed: {

        /**
         * Boolean to show or hide the modal
         */
        modal ()
        {
            return this.$store.state.modal.show;
        }
    },
}
</script>

<style scoped>

    .root-container {
        height: calc(100vh - 10px);
    }

</style>
