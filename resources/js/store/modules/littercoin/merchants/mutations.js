export const mutations = {
    /**
     * The user has clicked on the map at Supercluster.vue
     */
    setMerchantLocation (state, payload)
    {
        state.merchant.lat = payload.lat;
        state.merchant.lon = payload.lng;
    },

    /**
     * Geojson object of merchants has been received from the database
     */
    setMerchantsGeojson (state, payload)
    {
        state.geojson = payload;
    },

    /**
     *
     */
    setMerchant (state, payload)
    {
        state.merchant = payload;
    }
}
