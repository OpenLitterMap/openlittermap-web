<template>
    <div
        v-if="isOpen"
        @click="close"
        class="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm z-50"
    >
        <transition name="modal" appear>
            <div
                v-if="isOpen"
                @click.stop
                class="bg-slate-900/95 border border-white/10 rounded-xl shadow-2xl relative w-full max-w-lg mx-4 transform"
            >
                <header class="flex items-center justify-between p-4 border-b border-white/10">
                    <p class="text-lg font-semibold text-white mx-auto">
                        {{ title }}
                    </p>

                    <button
                        @click="close"
                        class="text-white/40 hover:text-white/70 focus:outline-none absolute right-4 top-4 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <component
                    :is="type"
                    :class="innerContainer"
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

export default defineComponent({
    name: "Modal",
    components: {
        Login,
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
