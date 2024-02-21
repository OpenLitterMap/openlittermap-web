<template>
    <div class="uploads-container">
        <h3 class="uploads-title">My Uploads</h3>

        <div class="filters">
            <div class="filter-item">
                <label for="filterTag">
                    Tag
                </label>

                <input
                    id="filterTag"
                    name="filterTag"
                    class="input"
                    v-model="filterTag"
                    placeholder="Enter a tag"
                />
            </div>

            <div class="filter-item">
                <label for="filterCustomTag">
                    Custom Tag
                </label>

                <input
                    id="filterCustomTag"
                    name="filterCustomTag"
                    class="input"
                    v-model="filterCustomTag"
                    placeholder="Enter a custom tag"
                />
            </div>

            <div class="filter-item">
                <label for="uploadedFrom">
                    Uploaded From
                </label>
                <input
                    id="uploadedFrom"
                    name="uploadedFrom"
                    class="input"
                    type="date"
                    v-model="filterDateFrom"
                    placeholder="From"
                />
            </div>

            <div class="filter-item">
                <label for="uploadedTo">
                    Uploaded To
                </label>
                <input
                    id="uploadedTo"
                    name="uploadedTo"
                    class="input"
                    type="date"
                    v-model="filterDateTo"
                    placeholder="To"
                />
            </div>

            <div class="filter-item">
                <label for="uploadedTo">
                    Amount
                </label>
                <select
                    class="input"
                    v-model="paginationAmount"
                >
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>

            <button
                class="button is-small is-primary"
                @click="getData"
                style="margin-top: 25px;"
            >Apply Filters</button>
        </div>

        <div class="table-wrapper">
            <table class="uploads-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tags</th>
                        <th>Custom Tags</th>
                        <th>Taken at</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="photo in photos"
                        :key="photo.id"
                    >
                        <td>
                            {{ photo.id }}
                        </td>

                        <td>
                            {{ photo.result_string ? photo.result_string : "No tags" }}
                        </td>

                        <td>
                            {{ photo.custom_tags.length ? getCustomTags(photo.custom_tags) : "No tags" }}
                        </td>

                        <td>
                            {{ photo.datetime }}
                        </td>

                        <td>
                            {{ photo.display_name }}
                        </td>

                        <td>
                            <!-- Copy Link Button -->
                            <button @click="copyLinkToClipboard(photo)">
                                Copy Link
                            </button>

                            <button @click="openLinkNewTab(photo)">
                                Open
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination">
<!--            <button @click="changePage(1)" :disabled="currentPage <= 1">-->
<!--                First-->
<!--            </button>-->
            <button
                @click="changePage(currentPage - 1)"
                :disabled="currentPage <= 1"
            >
                Previous
            </button>

            <!-- Page Numbers -->
            <button
                v-for="page in pages"
                :key="page"
                :class="{'active': page === currentPage}"
                @click="changePage(page)"
            >
                {{ page }}
            </button>

            <button
                @click="changePage(currentPage + 1)"
                :disabled="currentPage >= lastPage"
            >
                Next
            </button>
<!--            <button @click="changePage(lastPage)" :disabled="currentPage >= lastPage">-->
<!--                Last-->
<!--            </button>-->
        </div>
    </div>
</template>

<script>
export default {
    name: "MyUploads",
    data() {
        return {
            showCopyNotification: false,
            currentPage: 1,
            lastPage: 1,
            filterDateFrom: '',
            filterDateTo: '',
            filterResultString: '',
            filterTag: '',
            filterCustomTag: '',
            paginationAmount: 25
        };
    },
    async created ()
    {
        await this.getData();

        // await this.fetchDataForPagination(this.currentPage);
    },
    computed: {

        pages ()
        {
            let pages = [];

            for (let i = 1; i <= this.lastPage; i++) {
                pages.push(i);
            }

            return pages;
        },

        photos ()
        {
            return this.$store.state.photos.myUploadsPaginate;
        },
    },
    methods: {
        /**
         * Create a link to the photo and copy the url to the clipboard
         */
        async copyLinkToClipboard (photo)
        {
            const url = `https://openlittermap.com/global?lat=${photo.lat}&lon=${photo.lon}&zoom=17&photo=${photo.id}`;

            if (navigator.clipboard)
            {
                try
                {
                    await navigator.clipboard.writeText(url);

                    return;
                }
                catch (err)
                {
                    console.error('Failed to copy with Clipboard API: ', err);
                }
            }

            // Call for the fallback mechanism as well
            this.copyToFallbackClipboard(url);
        },

        /**
         * Copy image's link to clipboard
         * @param text
         */
        copyToFallbackClipboard (text)
        {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                // alert('Link copied!');
            } catch (err) {
                console.error('Fallback copy to clipboard failed: ', err);
            } finally {
                document.body.removeChild(textArea);
            }
        },

        getCustomTags (customTags)
        {
            return customTags.map(tag => tag.tag).join(', ');
        },

        /**
         * Get the data with the filters applied
         */
        async getData ()
        {
            await this.$store.dispatch('GET_MY_PHOTOS', {
                filterTag: this.filterTag,
                filterCustomTag: this.filterCustomTag,
                filterDateFrom: this.filterDateFrom,
                filterDateTo: this.filterDateTo,
                currentPage: this.currentPage,
                paginationAmount: this.paginationAmount
            });
        },

        /**
         * Create a link to the photo
         * and upload it in a new tab
         */
        openLinkNewTab (photo)
        {
            const url = `https://openlittermap.com/global?lat=${photo.lat}&lon=${photo.lon}&zoom=17&photo=${photo.id}`;

            window.open(url, '_blank');
        },

        // /**
        //  *
        //  */
        // async fetchDataForPagination(page) {
        //     let query = `/photos/get-my-photos?page=${page}`;
        //     if (this.filterTags) query += `&tags=${encodeURIComponent(this.filterTags)}`;
        //     if (this.filterResultString) query += `&resultString=${encodeURIComponent(this.filterResultString)}`;
        //     if (this.filterDateFrom) query += `&dateFrom=${this.filterDateFrom}`;
        //     if (this.filterDateTo) query += `&dateTo=${this.filterDateTo}`;
        //
        //     const response = await axios.get(query);
        //     this.$store.commit('setUsersUploads', response.data.photos.data);
        //     this.currentPage = response.data.photos.current_page;
        //     this.lastPage = response.data.photos.last_page;
        // },

        /**
         * Change page
         */
        changePage(page) {
            const targetPage = Number(page); // Ensure the page is a number
            if (targetPage < 1 || targetPage > this.lastPage || targetPage === this.currentPage) return;
            this.currentPage = targetPage;
            // this.fetchDataForPagination(targetPage);
        },

    }
}
</script>

<style scoped>
    .filters {
        display: flex;
        gap: 20px;
        margin-bottom: 1em;
        align-items: center;
    }

    .filter-item {
        display: flex;
        flex-direction: column; /* Stacks the label over the input */
    }

    .uploads-container {
        display: flex;
        flex-direction: column;
        justify-content: center; /* Vertically center the content */
        align-items: center; /* Horizontally center the content */
        padding: 1em;
        background: #ffffff;
        border-radius: 12px;
        max-height: 100vh; /* Adjust to max-height to prevent overflow */
        overflow: hidden; /* Hide overflow */
        margin: 0 auto; /* Center the container itself */
    }

    .uploads-title {
        text-align: center;
        color: #333;
        font-size: 24px;
        margin-top: 30px;
        margin-bottom: 30px;
    }

    .table-wrapper {
        overflow-y: auto; /* Enable vertical scrolling */
        width: 100%; /* Ensure it takes up the full width */
        max-height: 75vh; /* Limit the height to ensure it fits in the viewport */
    }

    .uploads-table {
        width: 100%;
        border-collapse: collapse;
    }

    .uploads-table th, .uploads-table td {
        border: 1px solid #ddd;
        padding: 8px;
    }

    .uploads-table tr:nth-child(even) {background-color: #f2f2f2;}

    .uploads-table tr:hover {background-color: #ddd;}

    .uploads-table th {
        padding-top: 12px;
        padding-bottom: 12px;
        text-align: left;
        background-color: #007bff;
        color: white;
    }

    .pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px; /* Adds space between buttons */
        margin-top: 20px;
    }

    .pagination button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination button.active {
        background-color: #0056b3; /* Active page button background */
        font-weight: bold;
    }

    .pagination button {
        padding: 5px 10px;
        border: 1px solid #007bff;
        background-color: #007bff;
        color: white;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.2s;
    }

    .pagination button:hover:not(:disabled) {
        background-color: #0056b3;
        transform: scale(1.05);
    }

    .pagination span {
        color: #333;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .uploads-container {
            max-width: 95%;
            margin: 20px auto;
            padding: 15px;
        }

        .uploads-title {
            font-size: 20px;
        }
    }
</style>



