import { defineStore } from "pinia";

export const useUploadingStore = defineStore('uploading', {

    state: () => ({
        isUploading: false,
    }),

    actions: {
        setIsUploading(val) {
            this.isUploading = val;
        },
    }

});
