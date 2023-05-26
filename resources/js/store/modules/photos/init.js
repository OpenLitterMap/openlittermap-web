export const init = {
    filters: {
        id: '',
        dateRange: {
            start: null,
            end: null
        },
        period: 'created_at',
        verified: null
    },
    paginate: {
        prev_page_url: null,
        next_page_url: null,
        data: []
    },
    bulkPaginate: {
        prev_page_url: null,
        next_page_url: null,
        data: []
    },
    remaining: 0,
    selectedCount: 0,
    selectAll: false,
    inclIds: [], // when selectAll is false
    exclIds: [], // when selectAll is true
    total: 0, // number of photos available
    verified: 0, // level of verification
    previousCustomTags: [],
    showDetailsPhotoId: null
};
