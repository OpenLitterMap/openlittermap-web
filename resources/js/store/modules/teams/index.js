import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    allTeams: {
        photos_count: 0,
        litter_count: 0,
        members_count: 0
    },
    component_type: 'TeamsDashboard',
    error: '',
    errors: {},
    types: []
};

export const teams = {
    state,
    actions,
    mutations
}
