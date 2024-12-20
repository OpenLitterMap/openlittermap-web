import Vue from "vue";
import i18n from "../../../i18n";

export const actions = {

    /**
     * The user wants to cancel their current subscription.
     * We must also delete any pending invoices.
     */
    async DELETE_ACTIVE_SUBSCRIPTION (context)
    {
        let title = i18n.t('notifications.success');
        let body  = i18n.t('notifications.subscription-cancelled');

        await axios.post('/stripe/delete')
            .then(response => {
                console.log('delete_active_subscription', response);

                /* improve css */
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                // update user/subscriber data
                context.commit('reset_subscriber');
            })
            .catch(error => {
                console.log('error.delete_active_subscription');
            });
    },

    /**
     * Check a users subscription
     */
    async GET_USERS_SUBSCRIPTIONS (context)
    {
        // Get user.subscriptions
        await axios.get('/stripe/subscriptions')
            .then(response => {
                console.log('check_current_subscription', response);

                // There is more data here that we are not yet using
                context.commit('subscription', response.data.sub);
            })
            .catch(error => {
                console.log('error.check_current_subscription', error);
            });
    },

    /**
     * The user cancelled and wants to sign up again
     *
     * https://stripe.com/docs/api/subscriptions/create
     */
    async RESUBSCRIBE (context, payload)
    {
        await axios.post('/stripe/resubscribe', {
            plan: payload
        })
        .then(response => {
            console.log('resubscribe', response);
        })
        .catch(error => {
            console.log('error.resubscribe', error);
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
