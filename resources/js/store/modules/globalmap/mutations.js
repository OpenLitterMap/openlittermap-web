export const mutations = {

	closeDatesButton (state) {
		state.datesOpen = false;
	},

	closeLangsButton (state) {
		state.langsOpen = false;
	},

	globalLoading (state, payload) {
		state.loading = payload;
	},

	toggleLangsButton (state) {
		state.langsOpen = ! state.langsOpen;
	},

	toggleGlobalDates (state) {
		state.datesOpen = ! state.datesOpen;
	},

	// updateCurrentGlobalDate (state, payload) {
	// 	state.currentDate = payload;
	// },

	updateGlobalData (state, payload) {
		state.globalMapData = payload;
	}

};