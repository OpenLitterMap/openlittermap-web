import i18n from "../../i18n.js";
import { useToast } from "vue-toastification";
const toast = useToast();

const t = i18n.global.t;

const title = t('notifications.success');
const body  = t('notifications.subscription-cancelled');

export const actions = {
    // /**
    //  * The user wants to cancel their current subscription.
    //  * We must also delete any pending invoices.
    //  */
    // async DELETE_ACTIVE_SUBSCRIPTION (context)
    // {

    //
    //     await axios.post('/stripe/delete')
    //         .then(response => {
    //             console.log('delete_active_subscription', response);
    //
    //             toast.success({
    //                 title,
    //                 body,
    //             });
    //
    //             // update user/subscriber data
    //             this.reset_subscriber();
    //         })
    //         .catch(error => {
    //             console.log('error.delete_active_subscription');
    //         });
    // },

    // /**
    //  * Check a users subscription
    //  */
    // async GET_USERS_SUBSCRIPTIONS (context)
    // {
    //     // Get user.subscriptions
    //     await axios.get('/stripe/subscriptions')
    //         .then(response => {
    //             console.log('check_current_subscription', response);
    //
    //             // There is more data here that we are not yet using
    //             // context.commit('subscription', response.data.sub);
    //             this.subscription(response.data.sub);
    //         })
    //         .catch(error => {
    //             console.log('error.check_current_subscription', error);
    //         });
    // },

    // /**
    //  * The user cancelled and wants to sign up again
    //  *
    //  * https://stripe.com/docs/api/subscriptions/create
    //  */
    // async RESUBSCRIBE (context, payload)
    // {
    //     await axios.post('/stripe/resubscribe', {
    //         plan: payload
    //     })
    //     .then(response => {
    //         console.log('resubscribe', response);
    //     })
    //     .catch(error => {
    //         console.log('error.resubscribe', error);
    //     });
    // },

    /**
     * A new subscriber wants to receive emails
     */
    async CREATE_EMAIL_SUBSCRIPTION (payload)
    {
        await axios.post('/subscribe', {
            email: payload
        })
        .then(response => {
            console.log('subscribe', response)

            toast.success("Subscription created!");

            // show notification
            this.updatedJustSubscribed(true);

            // hide notification
            setTimeout(() => {
                this.updatedJustSubscribed(false);
            }, 5000)
        })
        .catch(error => {
            console.log('error.subscribe', error.response.data.errors);

            this.subscribeErrors(error.response.data.errors);
        });
    }

};
