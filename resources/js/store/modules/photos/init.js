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
    remaining: 0,
    total: 0,
    verified: 0 // level of verification
};
