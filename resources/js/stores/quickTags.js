import { defineStore } from 'pinia';

let saveTimer = null;

export const useQuickTagsStore = defineStore('quickTags', {
    state: () => ({
        tags: [],
        loading: false,
        dirty: false,
    }),

    actions: {
        async FETCH_QUICK_TAGS() {
            this.loading = true;

            try {
                const { data } = await axios.get('/api/v3/user/quick-tags');
                this.tags = data.tags;
            } catch (e) {
                console.error('FETCH_QUICK_TAGS', e);
            } finally {
                this.loading = false;
            }
        },

        async SAVE_QUICK_TAGS() {
            try {
                const payload = this.tags.map((tag, index) => ({
                    clo_id: tag.clo_id,
                    type_id: tag.type_id ?? null,
                    quantity: tag.quantity,
                    picked_up: tag.picked_up ?? null,
                    materials: tag.materials || [],
                    brands: tag.brands || [],
                }));

                const { data } = await axios.put('/api/v3/user/quick-tags', { tags: payload });
                this.tags = data.tags;
                this.dirty = false;
            } catch (e) {
                console.error('SAVE_QUICK_TAGS', e);
            }
        },

        debouncedSave() {
            this.dirty = true;
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => this.SAVE_QUICK_TAGS(), 2000);
        },

        moveTag(fromIndex, toIndex) {
            if (toIndex < 0 || toIndex >= this.tags.length) return;
            const tag = this.tags.splice(fromIndex, 1)[0];
            this.tags.splice(toIndex, 0, tag);
            this.debouncedSave();
        },

        updateTag(index, updates) {
            Object.assign(this.tags[index], updates);
            this.debouncedSave();
        },

        deleteTag(index) {
            this.tags.splice(index, 1);
            this.debouncedSave();
        },
    },
});
