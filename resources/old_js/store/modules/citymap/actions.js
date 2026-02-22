export const actions = {

    /**
     * Return the data for a city map
     */
    async GET_CITY_DATA (context, payload)
    {
        await axios.get('/city', {
            params: {
                city: payload.city,
                min: payload.min,
                max: payload.max,
                hex: payload.hex
            }
        })
        .then(response => {
            console.log('get_city_data', response);

            context.commit('init_city_data', response.data);
        })
        .catch(error => {
            console.log('error.get_city_data', error);
        });
    },
}
