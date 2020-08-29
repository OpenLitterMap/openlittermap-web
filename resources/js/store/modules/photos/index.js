import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    photos: {},
    remaining: 0,
    total: 0
};

export const photos = {
    state,
    actions,
    mutations
};
