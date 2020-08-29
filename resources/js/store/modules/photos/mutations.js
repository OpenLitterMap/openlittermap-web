export const mutations = {

    /**
     * Get unverified photos for tagging
     */
    photosForTagging (state, payload)
    {
        state.photos = payload.photos;
        state.remaining = payload.remaining;
        state.total = payload.total;
    }
}
