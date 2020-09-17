import { init } from './init'

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
     * Reset state, when the user logs out
     */
    resetState (state)
    {
        Object.assign(state, init);
    },

    /**
     * When a user has cancelled their subscription, we can reset the object
     */
    reset_subscriber (state, payload)
    {
        state.subscription = {};
    },

    /**
     * If user.stripe_id exists, we put the users subscriptions here
     */
    subscription (state, payload)
    {
        state.subscription = payload;
    },

    /**
     * Any errors from subscribe action
     */
    subscribeErrors (state, payload)
    {
        state.errors = payload;
    }

};
