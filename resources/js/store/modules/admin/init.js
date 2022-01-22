export const init = {
    id: '',
    filename: '',
    not_processed: 0, // metadata. Uploaded + not tagged
    awaiting_verification: 0, // metadata. Uploaded + tagged. Not yet verified.
    items: {},
    photo: {}, // the current photo we are verifying
    loading: true, // spinner
    countriesWithPhotos: [],
    filterByCountry: '',
    skippedPhotos: 0,
};
