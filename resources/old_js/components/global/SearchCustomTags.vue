<template>
    <div
        class="outer-container"
        v-click-outside="closeContainer"
    >
        <div class="inner-container">
            <input
                id="search-custom-tags"
                class="input"
                v-model="search"
                placeholder="Search custom tags"
                @click="open = true"
                @input="debouncedInputHandler"
            />

            <div
                v-if="open"
                class="tags-container"
            >
                <p v-if="!customTags.length">
                    No results found. Try again later!
                </p>

                <div
                    v-else
                    v-for="tag in this.customTags"
                    class="tag-selection"
                    @click="loadCustomTag(tag.tag)"
                >
                    <p>{{ tag.tag }} ({{ tag.total }})</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import ClickOutside from 'vue-click-outside';

export default {
    name: "SearchCustomTags",
    created () {
        this.debouncedInputHandler = this.debounce(this.searchForTags);
    },
    directives: {
        ClickOutside
    },
    data () {
        return {
            search: "",
            open: false,
            processing: false,
            selectedTags: []
        };
    },
    computed: {
        customTags () {
            return this.$store.state.globalmap.customTagsFound;
        }
    },
    methods: {
        closeContainer () {
            this.open = false;
        },

        debounce (func)
        {
            let timeoutId;

            return function(...args)
            {
                clearTimeout(timeoutId);

                timeoutId = setTimeout(() => {
                    func.apply(this, args);
                }, 1000);
            };
        },

        // Temp fix until next upgrade
        // todo
        // 1. remove clusters/points
        // 2. fetch custom tag geojson
        // 3. add to map
        loadCustomTag (tag)
        {
            window.open('https://openlittermap.com/tags?custom_tags=' + tag);
        },

        async searchForTags ()
        {
            this.processing = true;

            await this.$store.dispatch('SEARCH_CUSTOM_TAGS', this.search);

            this.processing = false;
        }
    }
}
</script>

<style scoped>

    .outer-container {
        position: absolute;
        width: 30em;
        top: 1em;
        left: 4em;
        border-radius: 10px;
        z-index: 9999;
    }

    .tags-container {
        background-color: white;
        max-height: 20em;
        overflow-y: auto;
    }

    .tag-selection {
        padding: 5px 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
    }
    .tag-selection:hover {
        background-color: #e7e7e7;
    }

    @media screen and (max-width: 687px)
    {
        #search-custom-tags {
            width: max-content !important;
        }

        .tags-container {
            width: 12.5em !important;
        }


    }

</style>
