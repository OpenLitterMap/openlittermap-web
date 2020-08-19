<template>
    <div style="height: 100vh;">
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
    props: ['auth'],
    components: {
        Nav,
        Modal
    },
    created ()
    {
        if (this.auth) this.$store.commit('login');
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

</style>
