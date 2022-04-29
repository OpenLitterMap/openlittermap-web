import { actions } from "./actions";
import { mutations } from "./mutations";

const state = {
    paginatedLeaderboard: null
};

export const leaderboard = {
    state,
    actions,
    mutations
}
