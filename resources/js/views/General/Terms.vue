<template>
    <section class="olm-terms max-w-4xl mx-auto px-6 py-8 bg-white">
        <header class="mb-12 pb-8 border-b-2 border-gray-200">
            <h1 class="text-3xl font-bold text-gray-900 mb-3">OpenLitterMap Terms &amp; Conditions</h1>
            <p class="text-gray-600 mb-6"><strong>Last Updated:</strong> {{ lastUpdated }}</p>
            <nav v-if="showToc" aria-label="Table of contents" class="bg-gray-50 p-6 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Table of Contents</h3>
                <ol class="space-y-2">
                    <li v-for="item in toc" :key="item.id" class="hover:text-blue-700 transition-colors">
                        <a :href="`#${item.id}`" class="text-blue-600 no-underline hover:underline">{{ item.label }}</a>
                    </li>
                </ol>
            </nav>
        </header>

        <div class="space-y-10">
            <section :id="ids.purpose" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">1. Purpose</h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        OpenLitterMap ("OLM", "we", "our", "us") is an
                        <strong class="text-gray-900"
                            >open‑source, real‑time geographic data collection platform</strong
                        >
                        for the internet.
                    </p>
                    <p>
                        Inspired by
                        <a
                            :href="osmUrl"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >OpenStreetMap</a
                        >
                        — the most powerful collaborative map of the world, built by over 10&nbsp;million contributors —
                        our mission is to make
                        <strong class="text-gray-900"
                            >real‑time geographic data collection quick, fun, impactful, and accessible.</strong
                        >.
                    </p>
                    <p>
                        We began mapping litter and plastic pollution in 2008. Since then, OpenLitterMap has grown into
                        one of the
                        <strong class="text-gray-900">largest, longest‑running, and most open platforms</strong>
                        dedicated to mapping litter and plastic pollution. Our vision is an
                        <strong class="text-gray-900">accurate, dynamic, and open dataset</strong> that empowers
                        communities, researchers, and decision‑makers to tackle pollution at every scale.
                    </p>
                </div>
            </section>

            <section :id="ids.code" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    2. Our Code — GPL v3
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        Our platform's code is licensed under the
                        <a
                            :href="gplUrl"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >GNU General Public License v3 (GPLv3)</a
                        >. This means you can view, download, learn from, and fork our code from
                        <a
                            :href="repoUrl"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >our repository</a
                        >.
                    </p>
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>
                            You are free to modify and distribute the code,
                            <strong class="text-gray-900">your only requirement</strong> is that you also release your
                            code changes under the terms of the same open source licence.
                        </li>
                        <li>The license guarantees the code remains open and free for the public good.</li>
                    </ul>
                    <p class="italic bg-blue-50 p-4 rounded border-l-4 border-blue-400">
                        We believe open source transcends pre‑digital economic philosophies — combining the
                        <em>collective equity</em> of Karl Marx with the <em>invisible hand</em> of Adam Smith.
                    </p>
                    <p>
                        <strong class="text-gray-900">Development status:</strong> OLM is an evolving project. Unlike
                        platforms that have received billions in funding, OLM operates on a community‑first, grassroots
                        model. There may be bugs, incomplete features, and limitations; however, development is
                        accelerating with AI and community contributions.
                    </p>
                </div>
            </section>

            <section :id="ids.data" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    3. Our Data — ODbL
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        Our data is licensed under the
                        <a
                            :href="odblUrl"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >Open Database License (ODbL)</a
                        >. This means:
                    </p>
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>
                            All geographic data you contribute — including
                            <strong class="text-gray-900">GPS locations, litter classifications, and counts</strong> —
                            is available for public download and reuse under ODbL.
                        </li>
                        <li>
                            You can use our data <strong class="text-gray-900">for any purpose</strong> (research,
                            education, commercial projects, advocacy, art) without asking permission, provided you
                            attribute OpenLitterMap and share any derivative databases under the same license.
                        </li>
                    </ul>
                    <h3 class="text-xl font-medium text-gray-900 mt-6 mb-3">Geotagging &amp; location accuracy</h3>
                    <p>
                        If geolocation services are enabled on your device, your photo's GPS coordinates (often with
                        high accuracy) will be captured and published
                        <strong class="text-gray-900">in real time</strong>. This enables live global environmental
                        monitoring — but also requires care near sensitive locations.
                    </p>
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>
                            <strong class="text-gray-900">Do not</strong> upload photos from your home, workplace, or
                            private locations unless you're comfortable with the location becoming public.
                        </li>
                        <li>
                            Focus your contributions on
                            <strong class="text-gray-900">non‑human natural environments</strong> to protect privacy and
                            resist normalization of personal surveillance online.
                        </li>
                    </ul>
                </div>
            </section>

            <section :id="ids.privacy" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">4. Your Privacy</h2>
                <ul class="space-y-3 ml-6 list-disc text-gray-700 leading-relaxed">
                    <li>By default, contributions are <strong class="text-gray-900">anonymous</strong>.</li>
                    <li>
                        You may optionally display your <strong class="text-gray-900">name</strong> (real or pseudonym)
                        or username on the map, in leaderboards, and in "created by" credits for first uploads at a
                        location.
                    </li>
                    <li>
                        Please avoid including identifiable features of people in photos — faces, tattoos, licence
                        plates, personal shadows, or footwear.
                    </li>
                    <li>
                        Think before you map: if you don't want data shown from a place (e.g., outside your house),
                        <strong class="text-gray-900">don't upload from there</strong>.
                    </li>
                </ul>
            </section>

            <section :id="ids.dataWeCollect" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    5. Data We Collect
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>We collect only what's needed to operate and improve the platform:</p>
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>
                            <strong class="text-gray-900">Account information:</strong> name, username, email, password
                            (encrypted)
                        </li>
                        <li>
                            <strong class="text-gray-900">Content:</strong> images you upload, including EXIF metadata
                            if available
                        </li>
                        <li>
                            <strong class="text-gray-900">Tags:</strong> pre‑defined and custom litter classifications
                            you add
                        </li>
                        <li><strong class="text-gray-900">Teams:</strong> names and details of teams you create</li>
                    </ul>
                </div>
            </section>

            <section :id="ids.dataNotCollected" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    6. Data We Do Not Collect
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>We respect your privacy. We do <strong class="text-gray-900">not</strong> collect:</p>
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>IP addresses</li>
                        <li>Browser cookies for tracking</li>
                        <li>Detailed site‑usage analytics beyond essential server logs</li>
                        <li>Third‑party tracking pixels including emails.</li>
                    </ul>
                </div>
            </section>

            <section :id="ids.acceptableUse" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    7. Acceptable Use
                </h2>
                <ul class="space-y-3 ml-6 list-disc text-gray-700 leading-relaxed">
                    <li>Only upload content you are comfortable sharing with the world.</li>
                    <li>Do not submit false, misleading, or spam data.</li>
                    <li>Only upload data of litter and plastic pollution.</li>
                    <li>
                        Please try to collect good quality data (eg. individual objects). Litter strewn over a street is
                        hard and boring to verify.
                    </li>
                    <li>
                        Please do not misuse our APIs, attempt to disrupt the service, or scrape in violation of our
                        licenses. Sometimes OLM has cost me €250 per/month just to host the images on AWS. Thankfully
                        AWS have been very generous with Earth credits to support open source.
                    </li>
                    <li>
                        Bulk image download is not yet available. When we release the OpenLitterMap AI we will make it
                        more available.
                    </li>
                </ul>
            </section>

            <section :id="ids.changes" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    8. Changes to OpenLitterMap & these Terms.
                </h2>
                <p class="text-gray-700 leading-relaxed">OLM is evolving rapidly. Expect updates.</p>
            </section>
        </div>

        <footer class="mt-16 pt-8 border-t-2 border-gray-200" :id="ids.contact">
            <h2 class="text-2xl font-semibold text-gray-900 mb-4">Contact</h2>
            <p class="text-gray-700 leading-relaxed">
                Questions? Email
                <a :href="`mailto:${contactEmail}`" class="text-blue-600 hover:text-blue-800 underline">{{
                    contactEmail
                }}</a>
            </p>
        </footer>
    </section>
</template>

<script setup lang="ts">
import { computed, defineProps } from 'vue';

const props = defineProps({
    lastUpdated: { type: String, default: 'Sunday 10th August, 2:07pm' },
    repoUrl: { type: String, default: '#' },
    gplUrl: { type: String, default: 'https://www.gnu.org/licenses/gpl-3.0.en.html' },
    odblUrl: { type: String, default: 'https://opendatacommons.org/licenses/odbl/' },
    osmUrl: { type: String, default: 'https://www.openstreetmap.org' },
    contactEmail: { type: String, default: 'info@openlittermap.com' },
    showToc: { type: Boolean, default: true },
});

const ids = {
    purpose: 'purpose',
    code: 'code',
    data: 'data',
    privacy: 'privacy',
    dataWeCollect: 'data-we-collect',
    dataNotCollected: 'data-not-collected',
    acceptableUse: 'acceptable-use',
    changes: 'changes',
    contact: 'contact',
};

const toc = computed(() => [
    { id: ids.purpose, label: '1. Purpose' },
    { id: ids.code, label: '2. Our Code — GPL v3' },
    { id: ids.data, label: '3. Our Data — ODbL' },
    { id: ids.privacy, label: '4. Your Privacy' },
    { id: ids.dataWeCollect, label: '5. Data We Collect' },
    { id: ids.dataNotCollected, label: '6. Data We Do Not Collect' },
    { id: ids.acceptableUse, label: '7. Acceptable Use' },
    { id: ids.changes, label: '8. Changes to OLM & Terms' },
    { id: ids.contact, label: 'Contact' },
]);
</script>
