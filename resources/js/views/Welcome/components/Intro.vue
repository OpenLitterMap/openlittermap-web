<template>
    <div class="md:flex mb-10 overflow-x-hidden">
        <div class="flex-1 min-w-0 md:pr-10 lg:pr-22 overflow-x-hidden">
            <transition name="slide-fade-left" mode="out-in">
                <div :key="activeHeadingIndex">
                    <h1 class="main-title" v-html="activeHeading.title"></h1>
                </div>
            </transition>

            <h2 class="main-subtitle text-gray-text">
                {{
                    $t("Help us create the world's most advanced open database on litter, brands & plastic pollution.")
                }}
            </h2>

            <div class="flex my-[-2em] md:my-0">
                <img :src="iosIcon" class="max-h-[10rem] mr-4 cursor-pointer" @click="ios" alt="ios logo" />
                <img :src="androidIcon" class="max-h-[10rem] cursor-pointer" @click="android" alt="android logo" />
            </div>
        </div>

        <div class="flex-1 min-w-0">
            <div class="top-image overflow-hidden">
                <transition name="slide-fade-right" mode="out-in">
                    <!-- Always exactly one child -->
                    <div :key="activeHeadingIndex" class="w-full h-full">
                        <img
                            :src="activeHeading.img"
                            :alt="activeHeading.title"
                            class="block w-full h-full object-cover"
                        />
                    </div>
                </transition>
            </div>
        </div>
    </div>
</template>

<script setup>
import iosIcon from '@/assets/icons/ios.png';
import androidIcon from '@/assets/icons/android.png';

import { useI18n } from 'vue-i18n';
const { t } = useI18n();
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';

const headings = ref([]);
const activeHeadingIndex = ref(0);

const activeHeading = computed(() => headings.value[activeHeadingIndex.value] || { title: '', img: '' });

const android = () => {
    window.open('https://play.google.com/store/apps/details?id=com.geotech.openlittermap', '_blank');
};

const ios = () => {
    window.open('https://apps.apple.com/us/app/openlittermap/id1475982147', '_blank');
};

let headingInterval = null;
let setAnimation = null;

const startHeadingsAnimation = () => {
    setAnimation = () => {
        if (document.hidden || headings.value.length === 0) {
            if (headingInterval) clearInterval(headingInterval);
            headingInterval = null;
            return;
        }

        headingInterval = setInterval(() => {
            activeHeadingIndex.value = (activeHeadingIndex.value + 1) % headings.value.length;
        }, 5000);
    };

    setAnimation();

    document.addEventListener('visibilitychange', setAnimation);
};

onMounted(() => {
    headings.value = [
        {
            title: t('Plastic pollution is out of control.'),
            img: '/assets/plastic_bottles.jpg',
        },
        {
            title: t('Cigarette butts can start fires.'),
            img: '/assets/forest_fire.jpg',
        },
        {
            title: t('Broken glass hurts animals.'),
            img: '/assets/dog.jpeg',
        },
    ];

    startHeadingsAnimation();
});

onBeforeUnmount(() => {
    if (setAnimation) {
        document.removeEventListener('visibilitychange', setAnimation);
    }
    if (headingInterval) {
        clearInterval(headingInterval);
    }
});
</script>

<style scoped>
/* Mobile view */
@media (max-width: 768px) {
    .top-image {
        height: 400px;
    }
}

/* Extra small */
@media (max-width: 576px) {
    .top-image {
        height: 260px;
    }
}

/** Text Animation */

.slide-fade-left-enter-from,
.slide-fade-left-leave-to {
    transform: translateX(-100px);
    opacity: 0;
}

.slide-fade-left-enter-active,
.slide-fade-left-leave-active {
    transition: all 0.5s ease; /* or ease-in if you prefer */
}

.slide-fade-left-enter-to,
.slide-fade-left-leave-from {
    transform: translateX(0);
    opacity: 1;
}

/** Image Animation */

.slide-fade-right-enter-from,
.slide-fade-right-leave-to {
    transform: translateX(100px);
    opacity: 0;
}

.slide-fade-right-enter-active,
.slide-fade-right-leave-active {
    transition: all 0.5s ease;
}

.slide-fade-right-enter-to,
.slide-fade-right-leave-from {
    transform: translateX(0);
    opacity: 1;
}
</style>
