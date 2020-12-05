export const mutations = {

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
    combinedTeamEffort (state, payload)
    {
        state.allTeams.photos_count = payload.photos_count;
        state.allTeams.litter_count = payload.litter_count;
        state.allTeams.members_count = payload.members_count;
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
     * Update the members on a paginated team object
     */
    teamMembers (state, payload)
    {
        state.members.data = payload;
    },

    /**
     * Init team.types from database
     */
    teamTypes (state, payload)
    {
        state.types = payload;
    },

    /**
     * Any teams the user has joined
     */
    usersTeams (state, payload)
    {
        state.teams = payload;
    }
}
