import { actions } from './actions'
import { mutations } from './mutations'

const state = {
    filterDateFrom: '',
    filterDateTo: '',
    filterResultString: '',
    filterTag: '',
    filterCustomTag: '',
    filterCountry: 'all',
    paginationAmount: 25
};

export const filterPhotos = {
    state,
    actions,
    mutations
};
