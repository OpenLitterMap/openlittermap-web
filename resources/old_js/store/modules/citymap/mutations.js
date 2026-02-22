export const mutations = {

    /**
     * A city data has been loaded from DB
     */
    init_city_data (state, payload)
    {
        state.center = payload.center_map;
        state.data = payload.litterGeojson;
        state.hex = payload.hex;
        state.zoom = payload.map_zoom;
    },
}
