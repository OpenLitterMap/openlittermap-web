import { init } from './init'

export const mutations = {

    /**
     * Hide the modal
     */
    hideModal (state)
    {
        state.show = false;
    },

    /**
     * Reset state, when the user logs out
     */
    resetState (state)
    {
        Object.assign(state, init);
    },

    /**
     * Show the modal
     */
    showModal (state, payload)
    {
        state.type = payload.type;
        state.title = payload.title;
        state.action = payload.action;
        state.show = true;
    },

};
