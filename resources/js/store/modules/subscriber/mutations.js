export const mutations = {

    /**
     * Reset errors object
     */
    clearSubscriberErrors (state)
    {
        state.errors = {};
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
