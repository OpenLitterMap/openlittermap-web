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
     * Reset the user object (when we logout)
     */
    resetState (state)
    {
        Object.assign(state, init);
    },

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
