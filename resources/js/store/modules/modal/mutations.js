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
        console.log(payload);
        state.show = true;
        state.type = payload.type;
        state.title = payload.title;
        state.action = payload.action;
    },

};
