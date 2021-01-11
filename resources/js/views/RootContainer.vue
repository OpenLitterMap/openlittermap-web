<template>
    <div class="root-container">
        <Nav />

        <WelcomeBanner :show-email-confirmed="showEmailConfirmed" />
        <Unsubscribed :show-unsubscribed="showUnsubscribed" />

        <Modal v-show="modal" />

        <router-view />
    </div>
</template>

<script>
import Nav from '../components/General/Nav';
import Modal from '../components/Modal/Modal';
import WelcomeBanner from '../components/WelcomeBanner';
import Unsubscribed from '../components/Notifications/Unsubscribed';

export default {
    name: 'RootContainer',
    components: {
        Nav,
        Modal,
        WelcomeBanner,
        Unsubscribed
    },
    props: ['auth', 'user', 'verified', 'unsub'],
    data ()
    {
        return {
            showEmailConfirmed: false,
            showUnsubscribed: false
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
                this.$store.commit('set_default_litter_presence', u.items_remaining);
            }
        }

        // This is needed to invalidate user.auth = true
        // which is persisted and not updated if the authenticated user forgets to manually log out
        else this.$store.commit('resetState');

        // If Account Verified
        if (this.verified) this.showEmailConfirmed = true;
        if (this.unsub) this.showUnsubscribed = true;
    }
};
</script>

<style scoped>

</style>
