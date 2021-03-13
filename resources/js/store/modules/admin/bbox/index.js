import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    boxes: [
        { id: 1, top: 0, left: 0, height: 100, width: 100, tags: null, active: false }
    ]
};

export const bbox = {
    state,
    actions,
    mutations
};
