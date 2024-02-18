<template>
    <div class="uploads-container">
        <h3 class="uploads-title">My Uploads</h3>

        <transition name="show-notification">
            <div v-if="showCopyNotification" class="notification">
                test
            </div>
        </transition>

        <table class="uploads-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tags</th>
                    <th>Taken at</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="upload in uploads"
                    :key="upload.id"
                >
                    <td>
                        {{ upload.id }}
                    </td>

                    <td>
                        {{ upload.result_string ? upload.result_string : "No tags" }}
                    </td>

<!--                    <td>-->
<!--                        <a :href="`>-->
<!--                            View Photo (Lat: {{ upload.lat }}, Lon: {{ upload.lon }})-->
<!--                        </a>-->
<!--                    </td>-->

                    <td>
                        {{ upload.datetime }}
                    </td>

                    <td>
                        {{ upload.display_name }}
                    </td>

                    <td>
                        <!-- Copy Link Button -->
                        <button @click="copyLinkToClipboard(upload)">
                            Copy Link
                        </button>

                        <button @click="openLinkNewTab(upload)">
                            Open
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>


<script>
export default {
    name: "MyUploads",
    data() {
        return {
            showCopyNotification: false,
            notificationMessage: '', // Added for dynamic messages
        };
    },
    async created ()
    {
        await this.$store.dispatch('GET_MY_PHOTOS');
    },
    computed: {
        uploads ()
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

                    this.triggerCopyNotification(); // Use the custom notification

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
         *
         * @param text
         */
        copyToFallbackClipboard(text) {
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
            const url = `https://openlittermap.com/global?lat=${photo.lat}&lon=${photo.lon}&zoom=17&photo=${photo.id}`;

            window.open(url, '_blank');
        },

        /**
         *
         * @param message
         */
        triggerCopyNotification(message = 'Link copied to clipboard!') {
            console.log("Notification triggered"); // Debugging line
            this.notificationMessage = message;
            this.showCopyNotification = true;
            setTimeout(() => {
                this.showCopyNotification = false;
            }, 3000); // Hide the notification after 3 seconds
        }
    }
}
</script>

<style scoped>
    .uploads-container {
        padding: 1em 5em;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border: 1px solid #eaeaea;
        height: 100%;
    }

    .uploads-title {
        text-align: center;
        color: #333;
        font-size: 24px;
        margin-top: 30px;
        margin-bottom: 30px;
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

    .link, .button {
        display: block;
        text-decoration: none;
        color: #007bff;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    .link:hover, .button:hover {
        color: #0056b3;
    }

    .button {
        margin-left: 10px;
        padding: 6px 12px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .notification {
        position: fixed;
        left: 50%;
        bottom: 20px;
        transform: translateX(-50%);
        background-color: #4CAF50;
        color: white;
        padding: 12px 24px;
        border-radius: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        z-index: 1050;
        transition: opacity 0.5s ease, bottom 0.5s ease;
        opacity: 0.9;
    }

    .show-notification-enter-active, .show-notification-leave-active {
        transition: opacity 0.5s, bottom 0.5s;
    }

    .show-notification-enter, .show-notification-leave-to {
        opacity: 0;
        bottom: 10px;
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



