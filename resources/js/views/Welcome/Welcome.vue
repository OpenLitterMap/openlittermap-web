<template>
    <div class="px-4 md:px-0">
        <div class="max-w-[1344px] pt-10 md:pt-20 mx-auto">
            <Intro />

            <WhyHow />

            <Partners />
        </div>

        <Footer />
    </div>
</template>

<script setup>
import Intro from './components/Intro.vue';
import WhyHow from './components/WhyHow.vue';
import Partners from './components/Partners.vue';
import Footer from './components/Footer.vue';
</script>
