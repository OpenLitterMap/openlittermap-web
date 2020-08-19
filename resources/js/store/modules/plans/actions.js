export const actions = {

    /**
     * Get all of the available plans
     */
    async GET_PLANS (context)
    {
        await axios.get(window.location.origin + '/plans')
        .then(response => {
            console.log('get_plans', response);

            context.commit('setPlans', response.data);
        })
        .catch(error => {
            console.log('error.get_plans', error);
        });
    }
}