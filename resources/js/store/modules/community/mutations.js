export const mutations = {

    /**
     * Sets the stats for the community page
     */
	setStats (state, payload)
    {
		state.photosPerMonth = payload.photosPerMonth;
		state.usersPerMonth = payload.usersPerMonth;
		state.litterTagsPerMonth = payload.litterTagsPerMonth;
		state.statsByMonth = payload.statsByMonth;
	},
};
