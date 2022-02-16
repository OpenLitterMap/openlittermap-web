<template>
    <div>
        <HeroSection/>
        <StatsSection/>
        <SocialSection/>
        <ZoomSection/>
    </div>
</template>

<script>

import SocialSection from './SocialSection';
import HeroSection from './HeroSection';
import StatsSection from './StatsSection';
import ZoomSection from './ZoomSection';

export default {
    name: 'Index',
    components: {
        ZoomSection,
        StatsSection,
        HeroSection,
        SocialSection,
    },
};
</script>

