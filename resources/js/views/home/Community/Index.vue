<template>
    <div>
        <HeroSection/>
        <FacebookSection/>
        <SocialSection/>
        <StatsSection/>
        <section class="hero section-stats" style="background-color: #1986B9">
            <div class="hero-body is-flex">
                <div class="py-2 stats" style="flex: 1">
                    <div class="is-size-1 has-text-centered has-text-light my-6">
                        A chart or two here
                    </div>
                </div>
            </div>
        </section>
        <section class="hero section-stats" style="background-color: #328BF2;height: 500px;">
            <div class="hero-body is-flex">
                <div class="py-2 stats" style="flex: 1">
                    <div class="is-size-1 has-text-centered has-text-light my-6">
                        Zoom calls, Gofundme etc.
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script>

import FacebookSection from './FacebookSection';
import SocialSection from './SocialSection';
import HeroSection from './HeroSection';
import StatsSection from './StatsSection';

export default {
    name: 'Index',
    components: {
        StatsSection,
        HeroSection,
        SocialSection,
        FacebookSection
    },
};
</script>

