export const init = {
    filters: {
        id: '',
        calendarData: {
            dateRange: {
                start: {
                    date: null
                },
                end: {
                    date: null
                }
            }
        },
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
