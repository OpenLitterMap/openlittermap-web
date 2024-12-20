export const mutations = {

    /**
     * Update the settings for all Teams
     */
    allTeamSettings (state, payload)
    {
        let teams = [...state.teams];

        const team = teams.find(t => t.id === payload);

        teams.forEach(t => {
            t.pivot.show_name_maps = team.pivot.show_name_maps;
            t.pivot.show_username_maps = team.pivot.show_username_maps;
            t.pivot.show_name_leaderboards = team.pivot.show_name_leaderboards;
            t.pivot.show_username_leaderboards = team.pivot.show_username_leaderboards;
        });

        state.teams = teams;
    },

    /**
     * Delete an error with payload key
     */
    clearTeamsError (state, payload)
    {
        delete state.errors[payload];
    },

    /**
     * Data from combined teams request by time_period
     */
    teamDashboardData (state, payload)
    {
        state.allTeams.photos_count = payload.photos_count;
        state.allTeams.litter_count = payload.litter_count;
        state.allTeams.members_count = payload.members_count;
        state.geojson = payload.geojson;
    },

    /**
     * Paginated array of a teams members
     */
    paginatedTeamMembers (state, payload)
    {
        state.members = payload;
    },

    /**
     * Change what team component the user is viewing
     */
    teamComponent (state, payload)
    {
        state.component_type = payload;
    },

    /**
     * There was a problem creating a new team
     */
    teamErrors (state, payload)
    {
        state.errors = payload;
    },

    /**
     * All Teams Leaderboard
     */
    teamsLeaderboard (state, payload)
    {
        state.leaderboard = payload;
    },

    /**
     * Init data from get teams map data request
     */
    teamMap (state, payload)
    {
        state.geojson = payload;
    },

    /**
     * Update the members on a paginated team object
     */
    teamMembers (state, payload)
    {
        state.members.data = payload;
    },

    /**
     * Update the value of a users settings on 1 team
     */
    team_settings (state, payload)
    {
        let teams = [...state.teams];

        let team = teams.find(t => t.id === payload.team_id);

        team.pivot[payload.key] = payload.v;

        state.teams = teams;
    },

    /**
     * Init team.types from database
     */
    teamTypes (state, payload)
    {
        state.types = payload;
    },

    /**
     * Change the visibility of a team
     */
    toggleTeamLeaderboardVis (state, payload)
    {
        let teams = [...state.teams];

        let team = teams.find(team => team.id === payload);

        team.leaderboards = ! team.leaderboards;

        state.teams = teams;
    },

    /**
     * Any teams the user has joined
     */
    usersTeams (state, payload)
    {
        state.teams = payload;
    }
}
