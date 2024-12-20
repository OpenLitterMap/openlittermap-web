<template>
    <div class="uploads-container">
        <h3 class="uploads-title">
            {{ this.title }}
            <span> ({{ this.paginatedPhotos.total }})</span>
        </h3>

        <FilterPhotos
            :action="this.action"
            parent="global"
        />

        <div class="table-wrapper">
            <table class="uploads-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tags</th>
                        <th>Custom Tags</th>
                        <th>Taken at</th>
                        <th style="max-width: 15em;">Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="photo in paginatedPhotos.data"
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

                        <td style="max-width: 15em;">
                            {{ photo.display_name }}
                        </td>

                        <td class="centre-table-buttons">
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
            <button
                @click="loadPreviousPage"
                :disabled="this.paginatedPhotos.current_page === 1"
            >
                Previous
            </button>

            <!-- Page Numbers -->
            <p>{{ this.paginatedPhotos.current_page }}</p>

            <button
                @click="loadNextPage"
                :disabled="this.paginatedPhotos.next_page_url === null"
            >
                Next
            </button>
        </div>
    </div>
</template>

<script>
import FilterPhotos from "../User/Photos/FilterPhotos.vue";

export default {
    name: "LitterTable",
    components: {
        FilterPhotos
    },
    props: [
        'title',
        'action',
        'paginatedPhotos'
    ],
    data () {
        return {
            showCopyNotification: false,
            currentPage: 1,
            lastPage: 1
        };
    },
    methods: {
        getCustomTags (customTags)
        {
            return customTags.map(tag => tag.tag).join(', ');
        },

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

        /**
         * Create a link to the photo
         * and upload it in a new tab
         */
        openLinkNewTab (photo)
        {
            const url = `${window.location.origin}/global?lat=${photo.lat}&lon=${photo.lon}&zoom=17&photo=${photo.id}`;

            window.open(url, '_blank');
        },

        async loadPreviousPage ()
        {
            const previousPage = this.paginatedPhotos.current_page - 1;

            await this.$store.dispatch(this.action, previousPage);
        },

        async loadNextPage ()
        {
            const nextPage = this.paginatedPhotos.current_page +1;

            await this.$store.dispatch(this.action, nextPage);
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

    .centre-table-buttons {
        text-align: center;
        vertical-align: middle;
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
