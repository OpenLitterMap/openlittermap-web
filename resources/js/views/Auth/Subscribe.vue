<template>
    <div>
        <section class="hero is-info is-medium">
            <div class="hero-body">
                <div class="container">
                    <h1 class="title">
                        Are you ready?
                    </h1>
                    <h2 class="subtitle">
                        Sign up to become an expert litter mapper and help us defeat plastic pollution.
                    </h2>
                </div>
            </div>
        </section>

        <section>
            <checkout-form :plan="plan" />
        </section>
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import CheckoutForm from '../../components/CheckoutForm'

export default {
    name: 'Subscribe',
    components: {
        CheckoutForm,
        Loading
    },
    created ()
    {
        // Get query string from url if it exists
        if (window.location.href.includes('?'))
        {
            this.plan = window.location.href.split('?')[1].split('=')[1];
        }

        this.$store.dispatch('GET_PLANS');
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