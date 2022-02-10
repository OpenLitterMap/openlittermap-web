export const mutations = {

    /**
     * Sets the stats for the community page
     */
	setStats (state, payload)
    {
		state.photosPerDay = payload.photosPerDay;
		state.usersPerWeek = payload.usersPerWeek;
		state.littercoinPerMonth = payload.littercoinPerMonth;
		state.statsByMonth = payload.statsByMonth;
	},
};
