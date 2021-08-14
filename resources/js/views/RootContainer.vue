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
import Nav from '../components/General/Nav'
import Modal from '../components/Modal/Modal'
import WelcomeBanner from '../components/WelcomeBanner'
import Unsubscribed from '../components/Notifications/Unsubscribed'

export default {
    name: 'RootContainer',
    props: [
        'auth',
        'user',
        'verified',
        'unsub'
    ],
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
                const user = JSON.parse(this.user);

                console.log('RootContainer.user', user);

                this.$store.commit('initUser', user);
                this.$store.commit('set_default_litter_presence', user.items_remaining);
            }
        }

        // This is needed to invalidate user.auth = true
        // which is persisted and not updated if the authenticated user forgets to manually log out
        else
        {
            console.log('guest');
            this.$store.commit('resetState');
        }

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
