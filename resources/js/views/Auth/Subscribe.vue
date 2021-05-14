<template>
    <div>
        <section class="hero is-info is-medium">
            <div class="hero-body">
                <div class="container">
                    <h1 class="title">
                        {{ $t('auth.subscribe.title') }}
                    </h1>
                    <h2 class="subtitle">
                        {{ $t('auth.subscribe.subtitle') }}
                    </h2>
                </div>
            </div>
        </section>

        <section>
            <create-account :plan="plan" />
        </section>
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import CreateAccount from '../../components/CreateAccount'

export default {
    name: 'Subscribe',
    components: {
        CreateAccount,
        Loading
    },
    async created ()
    {
        // Get query string from url if it exists
        if (window.location.href.includes('?'))
        {
            this.plan = window.location.href.split('?')[1].split('=')[1];
        }

        // Success or Fail response from Stripe Checkout
        if (window.location.href.includes('&'))
        {
            // 'success', or 'error'
            let status = window.location.href.split('&')[1].split('=')[1];

            let title    = this.$t('signup.' + status + '-title');
            let subtitle = this.$t('signup.' + status + '-subtitle');

            this.$swal(title, subtitle, status);
        }

        await this.$store.dispatch('GET_PLANS');
    },
    data ()
    {
        return {
            loading: true,
            plan: ''
        };
    }
}
</script>

<style scoped>

</style>
