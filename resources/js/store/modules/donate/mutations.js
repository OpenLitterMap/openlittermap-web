export const mutations = {

    /**
     * Set the amount options for donations
     */
    setDonateAmounts (state, payload)
    {
        state.amounts = payload;
    }
}