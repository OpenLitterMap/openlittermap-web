export const mutations = {
    /**
     * The user has started to create a cleanup
     *
     * We turn this on so they can find the location
     */
    creatingCleanup (state, payload)
    {
        state.creating = payload;
    },

    /**
     * The user has clicked on the map at Supercluster.vue
     */
    setCleanupLocation (state, payload)
    {
        state.lat = payload.lat;
        state.lon = payload.lng;
    },
}
