<template>
    <div class="root-container">
        <Nav />

        <WelcomeBanner :showEmailConfirmed="showEmailConfirmed" />
        <Unsubscribed :showUnsubscribed="showUnsubscribed" />

        <Modal v-show="modal" />

        <router-view />
    </div>
</template>

<script>
import Nav from '../components/General/Nav.vue'
import Modal from '../components/Modal/Modal.vue'
import WelcomeBanner from '../components/WelcomeBanner.vue'
import Unsubscribed from '../components/Notifications/Unsubscribed.vue'

export default {
    name: 'RootContainer',
    props: ['auth', 'user', 'verified', 'unsub'],
    components: {
        Nav,
        Modal,
        WelcomeBanner,
        Unsubscribed
    },
    data ()
    {
        return {
            showEmailConfirmed: false,
            showUnsubscribed: false
        }
    },
    created ()
    {
        if (this.$localStorage.get('lang'))
        {
            this.$i18n.locale = this.$localStorage.get('lang');
        }

        if (this.auth)
        {
            this.$store.commit('login');

            // user object is passed when the page is refreshed
            if (this.user)
            {
                const u = JSON.parse(this.user);
                this.$store.commit('initUser', u);
                this.$store.commit('set_default_litter_picked_up', u.picked_up);
            }
        }

        // This is needed to invalidate user.auth = true
        // which is persisted and not updated if the authenticated user forgets to manually log out
        else this.$store.commit('resetState');

        // If Account Verified
        if (this.verified) this.showEmailConfirmed = true;
        if (this.unsub) this.showUnsubscribed = true;
    },
    computed: {

        /**
         * Boolean to show or hide the modal
         */
        modal ()
        {
            return this.$store.state.modal.show;
        }
    }
}
</script>

<style scoped>

    .root-container {
        height: calc(100vh - 10px);
    }

</style>
