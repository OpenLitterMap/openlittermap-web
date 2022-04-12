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
