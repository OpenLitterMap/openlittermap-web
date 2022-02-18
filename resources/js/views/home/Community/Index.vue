<template>
    <div>
        <HeroSection/>
        <StatsSection/>
        <SocialSection/>
        <ZoomSection/>
        <SlackSection/>
    </div>
</template>

<script>

import SocialSection from './SocialSection';
import HeroSection from './HeroSection';
import StatsSection from './StatsSection';
import ZoomSection from './ZoomSection';
import SlackSection from './SlackSection';

export default {
    name: 'Index',
    components: {
        ZoomSection,
        StatsSection,
        HeroSection,
        SocialSection,
        SlackSection
    },
};
</script>

