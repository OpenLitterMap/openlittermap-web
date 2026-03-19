<template>
    <!-- Why -->
    <div class="mb-10">
        <h1 class="main-title">{{ $t('Why should we collect data?') }}?</h1>
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
        <h1 class="main-title">{{ $t('How does it work') }}?</h1>
        <div v-for="(step, index) in howItWorksItems" :key="index" class="md:flex mb-10 md:mb-20">
            <div class="flex-4 pr-20 pl-20 mb-10 md:mb-0">
                <img :src="step.icon" class="h-[10em]" alt="icon" />
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
        title: `1. ${t('Visibility')}`,
        subtitle: t(
            'For many people, litter has become normal and invisible. Maps are powerful because they communicate what we cannot usually see.'
        ),
    },
    {
        icon: '../../assets/icons/home/microscope.png',
        title: `2. ${t('Problem solving')}`,
        subtitle: t(
            'Our code & data is open and accessible. Everyone has equal, open and unlimited rights to use it for any purpose because open science is the real science.'
        ),
    },
    {
        icon: '../../assets/icons/home/tree.png',
        title: `3. ${t('Community')}`,
        subtitle: t('We need your help to create a paradigm shift in how we understand and respond to pollution.\n'),
    },
];

const howItWorksItems = [
    {
        icon: '../../assets/icons/home/camera.png',
        title: `1. ${t('Take a photo')}`,
        subtitle: t(
            'Your device can capture valuable information about the location, time, object, material and brand.'
        ),
    },
    {
        icon: '../../assets/icons/home/phone.png',
        title: `2. ${t('Tag what you see')}`,
        subtitle: `${t("Just tag what litter you see in the photo. You can tag if the litter has been picked up or if it's still there. You can upload your photos anytime!")}!`,
    },
    {
        icon: '../../assets/icons/twitter2.png',
        title: `3. ${t('Share your results')}`,
        subtitle: `${t("Share the maps or download our data. Let's show everyone how badly polluted the world really is!")}`,
    },
];
</script>
