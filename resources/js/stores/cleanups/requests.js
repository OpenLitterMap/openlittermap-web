export const requests = {
    /**
     * Get GeoJson cleanups object
     */
    async GET_CLEANUPS ()
    {
        await axios.get('/cleanups/get-cleanups')
            .then(response => {
                console.log('get_cleanups', response);

                if (response.data.success)
                {
                    this.geojson = response.data.geojson;
                }
            })
            .catch(error => {
                console.error('get_cleanups', error);
            });
    },
}
