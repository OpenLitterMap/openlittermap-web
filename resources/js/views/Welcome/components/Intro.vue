<template>
    <!-- Title, Subtitle, App Icons -->
    <div class="md:flex mb-10">
        <div class="flex-1 md:pr-10 lg:pr-22">
            <transition v-if="headings.length > 0" name="slide-fade-left" mode="out-in">
                <h1
                    v-if="activeHeading.title"
                    class="main-title"
                    :key="activeHeading.title"
                    v-html="activeHeading.title"
                ></h1>
            </transition>

            <h2 class="main-subtitle text-gray-text">
                {{
                    $t("Help us create the world's most advanced open database on litter, brands & plastic pollution.")
                }}
            </h2>

            <!-- Download app icons -->
            <div class="flex my-[-2em] md:my-0">
                <img :src="iosIcon" class="max-h-[10rem] mr-4 cursor-pointer" @click="ios" alt="ios logo" />
                <img :src="androidIcon" class="max-h-[10rem] cursor-pointer" @click="android" alt="android logo" />
            </div>
        </div>

        <div class="flex-1">
            <div class="top-image">
                <transition name="slide-fade-right" mode="out-in">
                    <img :key="activeHeading.title" :src="activeHeading.img" :alt="activeHeading.title" />
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
import { ref, onMounted, computed } from 'vue';

const headings = ref([]);
const activeHeadingIndex = ref(0);

const activeHeading = computed(() => headings.value[activeHeadingIndex.value] || { title: '', img: '' });

const android = () => {
    window.open('https://play.google.com/store/apps/details?id=com.geotech.openlittermap', '_blank');
};

const ios = () => {
    window.open('https://apps.apple.com/us/app/openlittermap/id1475982147', '_blank');
};

const startHeadingsAnimation = () => {
    let interval = null;

    function setAnimation() {
        if (document.hidden || headings.value.length === 0) {
            if (interval) clearInterval(interval);
            return;
        }

        interval = setInterval(() => {
            activeHeadingIndex.value = (activeHeadingIndex.value + 1) % headings.value.length;
        }, 5000);
    }

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
