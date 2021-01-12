export const init = {
    category: {}, // currently selected category.
    hasAddedNewTag: false, // Has the admin added a new tag yet? If FALSE, disable "Update With New Tags button"
    presence: null, // true = remaining
    item: {}, // currently selected item
    items: [], // items for the currently selected category
    loading: false,
    photos: {}, // paginated photos object
    tags: {}, // added tags go here -> { smoking: { butts: q, lighters: q }, alcohol: { beer_cans: q } ... };
    submitting: false,
    recentlyTags: []
};
