import Vue from 'vue'
import Vuex from 'vuex'
import createPersistedState from 'vuex-persistedstate'

import { admin } from './modules/admin'
import { bbox } from './modules/bbox'
import { citymap } from './modules/citymap'
import { cleanups } from './modules/cleanups'
import { community } from './modules/community'
import { donate } from './modules/donate'
import { errors } from './modules/errors'
import { globalmap } from './modules/globalmap'
import { leaderboard } from "./modules/leaderboard"
import { locations } from './modules/locations'
import { litter } from './modules/litter'
import { merchants } from './modules/littercoin/merchants'
import { modal } from './modules/modal'
import { payments } from './modules/payments'
import { photos } from './modules/photos'
import { plans } from './modules/plans'
import { subscriber } from './modules/subscriber'
import { teams } from './modules/teams'
import { user } from './modules/user'

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
        cleanups,
        community,
        errors,
        globalmap,
        leaderboard,
        locations,
        litter,
        merchants,
        modal,
        payments,
        photos,
        plans,
        subscriber,
        teams,
        user
    }
});
