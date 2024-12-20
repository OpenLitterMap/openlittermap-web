export const actions = {
    /**
     * Someone wants to create a new account!
     */
    async CREATE_ACCOUNT (context, payload)
    {
        await axios.post('/register', {
            name: payload.name,
            username: payload.username,
            email: payload.email,
            password: payload.password,
            password_confirmation: payload.password_confirmation,
            "g-recaptcha-response": payload.g_recaptcha_response
        })
        .then(response => {
            console.log('create_account', response); // user_id, email

            // check response

            // Free account
            if (payload.plan === 1)
            {
                // translate
                alert('Congratulations! Your free account has been created. Please verify your email to activate login');
            }

            // Load stripe for a subscription
            else if (payload.plan > 1)
            {
                // Todo - Our own custom stripe modal
                // this.$store.commit('showModal', {
                //     modalType: 'StripeCheckout'
                // });

                // For now - stripes checkout page
                const stripe = Stripe(process.env.MIX_STRIPE_PUBLIC_KEY);

                let successUrl = window.location.href + '&status=success';
                let cancelUrl = window.location.href + '&status=error';

                stripe.redirectToCheckout({
                    lineItems: [{
                        price: payload.plan_id, // the price is defined by plan_id
                        quantity: 1
                    }],
                    mode: 'subscription',
                    successUrl,
                    cancelUrl
                });
            }

            // Clear errors
            context.commit('createAccountErrors', []);

            // log the user in?
        })
        .catch(error => {
            console.log('error.create_account', error);

            // populate errors
            context.commit('createAccountErrors', error.response.data.errors);
        });
    },

    /**
     * Get all of the available plans
     */
    async GET_PLANS (context)
    {
        await axios.get('/plans')
            .then(response => {
                console.log('get_plans', response);

                context.commit('setPlans', response.data);
            })
            .catch(error => {
                console.log('error.get_plans', error);
            });
    }

};
