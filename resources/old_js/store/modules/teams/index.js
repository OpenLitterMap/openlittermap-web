import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    allTeams: {
        photos_count: 0,
        litter_count: 0,
        members_count: 0
    },
    component_type: 'TeamsDashboard',
    errors: {},
    geojson: [],
    leaderboard: [],
    members: {}, // paginated response of a teams members
    teams: [], // the user has joined
    types: [] // the user can create
};

export const teams = {
    state,
    actions,
    mutations
}
