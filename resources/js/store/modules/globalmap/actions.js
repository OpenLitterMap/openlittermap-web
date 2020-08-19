import moment from 'moment';

export const actions = {

    /**
     * Get data for the global map
     */
    async GLOBAL_MAP_DATA (context, payload)
    {
        context.commit('globalLoading', true);

        await axios.get(window.location.origin + '/global-data', {
            params: {
                date: payload
            }
        })
        .then(resp => {
            console.log('global_map_data', resp);

            let locations = [];
            resp.data.geojson.features.map(i => {
                locations.push({
                    id: i.properties.photo_id,
                    filename: i.properties.filename,
                    latlng: [i.geometry.coordinates[1], i.geometry.coordinates[0]],
                    text: '<p style="margin-bottom: 5px;">' + i.properties.result_string + ' </p><img src= "' + i.properties.filename + '" style="max-width: 100%;" /><p>Taken on ' + moment(i.properties.datetime).format('LLL') +'</p>'
                });
            });

            context.commit('updateGlobalData', locations);
            context.commit('globalLoading', false);
        })
        .catch(err => {
            console.log(err);
        });
    }
}