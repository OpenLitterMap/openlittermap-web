import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    geojson: {},
    merchant: {
        lat: 0,
        lon: 0
    }
};

export const merchants = {
    state,
    actions,
    mutations
};
