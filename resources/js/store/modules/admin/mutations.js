import { init } from './init'

export const mutations = {

    /**
     * Update filename for an image
     */
    adminImage (state, payload)
    {
        state.id = payload.id;
        state.filename = payload.filename;
    },

    /**
     * Change the state of the loading spinner
     */
    adminLoading (state, payload)
    {
        state.loading = payload
    },

    /**
     *
     */
    initAdminMetadata (state, payload)
    {
        state.not_processed = payload.not_processed;
        state.awaiting_verification = payload.awaiting_verification;
    },

    /**
     * A new admin photo has been received for verification
     */
    initAdminPhoto (state, payload)
    {
        state.photo = payload;
    },

    /**
     * Reset the user object (when we logout)
     */
    resetState (state)
    {
        Object.assign(state, init);
    },

    /**
     * Set countries that contain unverified photos
     */
    setCountriesWithPhotos (state, payload)
    {
        state.countriesWithPhotos = payload;
    },

    /**
     * Sets the country to filter the photos
     * @param state
     * @param payload
     */
    setFilterByCountry(state, payload)
    {
        state.filterByCountry = payload;
    },

    /**
     * Sets the number of photos to skip verification
     * @param state
     * @param payload
     */
    setSkippedPhotos(state, payload)
    {
        state.skippedPhotos = payload;
    }

    // adminCreated(state, payload) {
  //   Vue.set(state.items, payload.item, payload.quantity);
  //   Vue.set(state.stuff[payload.category], payload.item, payload.quantity);
  // },

  // setLang(state, payload) {
  // 	state.categoryNames = payload.categoryNames;
  // 	state.currentCategory = payload.currentCategory;
  // 	state.currentItem = payload.currentItem;
  // 	state.litterlang = payload.litterlang;
  // },

}
