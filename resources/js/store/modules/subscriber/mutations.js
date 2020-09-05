export const mutations = {

    /**
     * Reset errors object
     */
    clearSubscriberErrors (state)
    {
        state.errors = {};
    },

    /**
     * If user.stripe_id exists, we put the users subscription here
     */
    current_subscription (state, payload)
    {
        state.current_subscription = payload;
    },


    /**
     * Toggle the success notification when a user has subscribed
     */
    has_subscribed (state, payload)
    {
        state.just_subscribed = payload;
    },

    /**
     * Any errors from subscribe action
     */
    subscribeErrors (state, payload)
    {
        state.errors = payload;
    }

};
