import { init } from './init'

export const mutations = {

    /**
     * Reset the donate state
     */
    resetState (state)
    {
        Object.assign(state, init);
    },

    /**
     * Set the amount options for donations
     */
    setDonateAmounts (state, payload)
    {
        state.amounts = payload;
    }
}
