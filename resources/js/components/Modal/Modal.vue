<template>
    <div
        v-if="isOpen"
        @click="close"
        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
    >
        <transition name="modal" appear>
            <div
                v-if="isOpen"
                @click.stop
                class="bg-white rounded-lg shadow-lg relative w-full max-w-lg mx-auto transform"
            >
                <header class="bg-gray-100 flex items-center justify-between p-4 border-b relative">
                    <p class="text-lg font-semibold mx-auto absolute left-1/2 transform -translate-x-1/2">
                        {{ title }}
                    </p>

                    <button
                        @click="close"
                        class="text-gray-500 hover:text-gray-700 focus:outline-none ml-auto px-4 py-2"
                    >
                        <span class="font-bold">&times;</span>
                    </button>
                </header>

                <component
                    :is="type"
                    :class="innerContainer"
                    class="p-4"
                />
            </div>
        </transition>
    </div>
</template>


<script>
import { defineComponent, computed, onMounted } from "vue";
import { useModalStore } from '@/stores/modal';

/* Auth */
import Login from './Auth/Login.vue'

// /* Profile */
// import AddManyTagsToManyPhotos from './Photos/AddManyTagsToManyPhotos.vue';
// import ConfirmDeleteManyPhotos from './Photos/ConfirmDeleteManyPhotos.vue';

export default defineComponent({
    name: "Modal",
    components: {
        Login,
        // AddManyTagsToManyPhotos,
        // ConfirmDeleteManyPhotos,
    },
    setup() {
        const modalStore = useModalStore();

        const close = () => modalStore.hideModal();

        const isOpen = computed(() => modalStore.show);
        const title = computed(() => modalStore.title);
        const type = computed(() => modalStore.type);
        const innerContainer = computed(() => type.value === "Login" ? "p-4" : "p-6");

        onMounted(() => {
            const handleEsc = (e) => {
                if (e.key === "Escape") close();
            };

            document.addEventListener("keydown", handleEsc);

            return () => {
                document.removeEventListener("keydown", handleEsc);
            };
        });

        return {
            isOpen,
            close,
            title,
            type,
            innerContainer,
        };
    },
});
</script>

<style>
.modal-enter-active,
.modal-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.modal-enter-from {
    transform: scale(0.9);
    opacity: 0;
}

.modal-enter-to {
    transform: scale(1);
    opacity: 1;
}

.modal-leave-from {
    transform: scale(0.9);
    opacity: 1;
}

.modal-leave-to {
    transform: scale(1);
    opacity: 0;
}
</style>
