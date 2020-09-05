export const actions = {

    /**
     * Check a users subscription
     */
    async CHECK_CURRENT_SUBSCRIPTION (context)
    {
        // We make the request on the backend as this uses stripe secret key
        await axios.get('/stripe/check')
            .then(response => {
                console.log('check_current_subscription', response);

                // There is more data here that we are not yet using
                context.commit('current_subscription', response.data.customer.subscriptions.data[0]);
            })
            .catch(error => {
                console.log('error.check_current_subscription', error);
            });
    },

    /**
     * The user wants to cancel their current subscription.
     * We must also delete any pending invoices.
     */
    async DELETE_ACTIVE_SUBSCRIPTION (context)
    {
        await axios.post('/stripe/delete')
            .then(response => {
                console.log('delete_active_subscription', response);
            })
            .catch(error => {
                console.log('error.delete_active_subscription');
            });
    },

    /**
     * A new subscriber wants to receive emails
     */
    async SUBSCRIBE (context, payload)
    {
        await axios.post('/subscribe', {
            email: payload
        })
        .then(response => {
            console.log('subscribe', response)

            // show notification
            context.commit('has_subscribed', true);

            // hide notification
            setTimeout(() => {
                context.commit('has_subscribed', false);
            }, 5000)

            // do something else
        })
        .catch(error => {
            console.log('error.subscribe', error.response.data.errors);

            context.commit('subscribeErrors', error.response.data.errors);
        });
    }

};
