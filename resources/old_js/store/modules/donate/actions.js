export const actions = {

    /**
     * Get the amounts to show for donation options
     */
    async GET_DONATION_AMOUNTS (context)
    {
        await axios.get(window.location.origin + '/donate/amounts')
        .then(response => {
            console.log('get_donation_options', response.data);

            context.commit('setDonateAmounts', response.data);
        })
        .catch(error => {
            console.log('error.get_donation_options', error);
        });
    },

};