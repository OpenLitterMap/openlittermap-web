import Vue from 'vue'
import Vuex from 'vuex'
import createPersistedState from 'vuex-persistedstate'

import { admin } from './modules/admin'
import { bbox } from './modules/bbox'
import { donate } from './modules/donate'
import { citymap } from './modules/citymap'
import { globalmap } from './modules/globalmap'
import { locations } from './modules/locations'
import { litter } from './modules/litter'
import { modal } from './modules/modal'
import { payments } from './modules/payments'
import { photos } from './modules/photos'
import { plans } from './modules/plans'
import { subscriber } from './modules/subscriber'
import { teams } from './modules/teams'
import { user } from './modules/user'
import { community } from './modules/community';

Vue.use(Vuex)

export default new Vuex.Store({
    plugins: [
        createPersistedState({
            paths: ['user', 'litter.recentTags']
        })
    ],
    modules: {
        admin,
        bbox,
        donate,
        citymap,
        globalmap,
        locations,
        litter,
        modal,
        payments,
        photos,
        plans,
        subscriber,
        teams,
        user,
        community
    }
});
