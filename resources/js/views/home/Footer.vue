<template>
    <footer class="foot">
        <div class="container">
            <!-- Top Section -->
            <div class="inner-footer-container has-text-centered">
                <p class="top-footer-title">Want us to email you occasionally with good news?</p>

                <div class="flex">
                    <input
                        class="input f-input"
                        placeholder="Enter your email address"
                        type="email"
                        v-model="email"
                    />

                    <button class="button is-medium" @click="subscribe">Subscribe</button>
                </div>
            </div>


            <!-- Bottom Section -->

        </div>
    </footer>
</template>

<script>
export default {
    name: 'Footer',
    data ()
    {
        return {
            email: ''
        };
    },
    methods: {

        /**
         *
         */
        async subscribe ()
        {
            await this.$store.dispatch('SUBSCRIBE', this.email)
        }
    }
}
</script>

<style scoped>

    .foot {
        padding: 5em;
        background-image: radial-gradient(circle at 1% 1%,#328bf2,#1644ad);
        height: 40em;
    }

    .f-input {
        height: 3em;
        border-radius: 1em;
    }

    .inner-footer-container {
        padding-left: 10em;
        padding-right: 10em;
    }

    .top-footer-title {
        color: white;
        font-size: 2.5em;
        margin-bottom: 1.25em;
    }
</style>
