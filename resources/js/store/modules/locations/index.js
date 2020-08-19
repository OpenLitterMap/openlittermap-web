import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    country: '',
    globalLeaders: [],
    level: {
        previousXp: 0,
        nextXp: 0
    },
    locations: [], // counties, states, cities
    littercoinPaid: 0,
    previousLevelInt: 0,
    progressPercent: 0,
    state: '',
    states: [],
    totalLitterInt: 0,
    total_litter: 0,
    total_photos: 0
};

export const locations = {
    actions,
    mutations,
    state
};