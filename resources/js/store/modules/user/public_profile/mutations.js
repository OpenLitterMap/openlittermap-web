export const mutations = {
    /**
     * Initialise the public profile and related data of a user when viewing /username
     */
    userByUsername (state, payload)
    {
        state.publicProfile = payload.publicProfile;
        state.userData = payload.userData;
    }
}
