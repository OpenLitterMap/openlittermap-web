<template>
    <!-- Why -->
    <div class="mb-10">
        <h1 class="main-title">{{ $t('home.welcome.why-collect-data') }}?</h1>
        <div v-for="(why, index) in whyItems" :key="index" class="md:flex mb-10 md:mb-20">
            <div class="flex-4 pr-20 pl-20 mb-10 md:mb-0">
                <img :src="why.icon" class="h-[10em]" alt="why icon" />
            </div>
            <div class="flex-1 ma">
                <h2 class="why-how-title">{{ why.title }}</h2>
                <h3 class="why-how-text">{{ why.subtitle }}</h3>
            </div>
        </div>
    </div>

    <!-- How -->
    <div>
        <h1 class="main-title">{{ $t('home.welcome.how-does-it-work') }}?</h1>
        <div v-for="(step, index) in howItWorksItems" :key="index" class="md:flex mb-10 md:mb-20">
            <div class="flex-4 pr-20 pl-20 mb-10 md:mb-0">
                <img :src="step.icon" class="h-[10em]" alt="icon"/>
            </div>
            <div class="flex-1 ma">
                <h2 class="why-how-title">{{ step.title }}</h2>
                <h3 class="why-how-text">{{ step.subtitle }}</h3>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

const whyItems = [
    {
        icon: '../../assets/icons/home/world.png',
        title: `1. ${t('home.welcome.visibility')}`,
        subtitle: t('home.welcome.our-maps-reveal-litter-normality'),
    },
    {
        icon: '../../assets/icons/home/microscope.png',
        title: `2. ${t('home.welcome.science')}`,
        subtitle: t('home.welcome.our-data-open-source'),
    },
    {
        icon: '../../assets/icons/home/tree.png',
        title: `3. ${t('home.welcome.community')}`,
        subtitle: t('home.welcome.must-work-together'),
    },
];

const howItWorksItems = [
    {
        icon: '../../assets/icons/home/camera.png',
        title: `1. ${t('home.welcome.take-a-photo')}`,
        subtitle: t('home.welcome.device-captures-info'),
    },
    {
        icon: '../../assets/icons/home/phone.png',
        title: `2. ${t('home.welcome.tag-the-litter')}`,
        subtitle: `${t('home.welcome.tag-litter-you-see')}!`,
    },
    {
        icon: '../../assets/icons/twitter2.png',
        title: `3. ${t('home.welcome.share-results')}`,
        subtitle: `${t('home.welcome.share')}!`,
    },
];
</script>

<style scoped>

</style>
