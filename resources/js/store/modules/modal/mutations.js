export const mutations = {

    /**
     * Hide the modal
     */
    hideModal (state)
    {
        state.show = false;
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
