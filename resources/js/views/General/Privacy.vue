<template>
    <section class="olm-privacy max-w-4xl mx-auto px-6 py-8 bg-white">
        <header class="mb-12 pb-8 border-b-2 border-gray-200">
            <h1 class="text-3xl font-bold text-gray-900 mb-3">Privacy Policy</h1>
            <p class="text-gray-600 mb-6">
                <em>Last Updated: {{ lastUpdated }}</em>
            </p>
            <nav v-if="showToc" aria-label="Table of contents" class="bg-gray-50 p-6 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Table of Contents</h3>
                <ol class="space-y-2">
                    <li v-for="item in toc" :key="item.id" class="hover:text-blue-700 transition-colors">
                        <a :href="`#${item.id}`" class="text-blue-600 no-underline hover:underline">{{ item.label }}</a>
                    </li>
                </ol>
            </nav>
        </header>

        <div class="space-y-4 text-gray-700 leading-relaxed mb-10">
            <p>
                Please read this Privacy Policy carefully before using OpenLitterMap.com ("OLM", "we", "our", "us"). By
                using our services, you agree to the terms described here. We may update this policy from time to time
                and will notify you via the website or our social media channels.
            </p>
        </div>

        <div class="space-y-10">
            <section :id="ids.purpose" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    1. Purpose of Our Services
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        OpenLitterMap provides a platform for volunteers ("Citizen Scientists") to share geotagged
                        images of litter and plastic pollution. Geotagged images include:
                    </p>
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>Exact GPS location (often centimetre-level accuracy)</li>
                        <li>Timestamp (Year–Month–Day HH:MM:SS)</li>
                    </ul>
                    <p>
                        This data can reveal where and when you and certain geographic observations was found. It can
                        also highlight hotspots for hazardous waste such as drug-related litter which has potentially
                        contentious social implications. (We operate a separate non-open druglitter.info platform
                        specifically for this reason but it remains significantly underdeveloped).
                    </p>
                    <p>
                        When an image is uploaded its coordinates and timestamp are made available in real-time. Every
                        upload generates a unique link that can be shared with others, effectively creating a real-time
                        reporting service.
                    </p>
                    <p>
                        New users are anonymous by default and the image contents of their contributes are not
                        immediately available. We are developing a verification process that can determine the quality
                        and content of each upload and new user.
                    </p>
                    <p>
                        Once a user has their account upgraded to verified, the image contents + tags of their uploads
                        are made immediately available with every upload.
                    </p>
                    <p>
                        As well as the pre-defined 150+ tags of litter, you may also enter Custom Tags. Simply type in
                        any text and press enter to add any tag you want. This way you can extend OpenLitterMap to map
                        anything (eg donation tracking, landslides, coastal erosion, biodiversity, etc). Litter and
                        plastic mapping is just the first application of this technology.
                    </p>
                </div>
            </section>

            <section :id="ids.ageRestrictions" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    2. Age Restrictions
                </h2>
                <div class="space-y-6 text-gray-700 leading-relaxed">
                    <div>
                        <h3 class="text-xl font-medium text-gray-900 mb-3">Under 13</h3>
                        <p>
                            We do not allow users under the age of 13. If we discover personal data from a child under
                            13, we will delete it immediately.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-xl font-medium text-gray-900 mb-3">Ages 13–17</h3>
                        <p>
                            Users aged 13–17 may only participate with parental or guardian supervision. Supervisors
                            should ensure safe practices, particularly when encountering hazardous litter such as
                            needles, broken glass, or other dangerous items.
                        </p>
                    </div>

                    <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded">
                        <h3 class="text-xl font-bold text-red-800 mb-3">URGENT MEDICAL ATTENTION</h3>
                        <p class="text-red-700 font-semibold">
                            If you suffer an injury, call 112, 999, or your local emergency number immediately.
                        </p>
                    </div>
                </div>
            </section>

            <section :id="ids.dataWeCollect" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    3. Data We Collect
                </h2>
                <ul class="space-y-3 ml-6 list-disc text-gray-700 leading-relaxed">
                    <li>Account details (name, username, email, password)</li>
                    <li>Images you submit</li>
                    <li>EXIF metadata from images (e.g., GPS location, timestamp)</li>
                    <li>Tags and attributes associated with each image</li>
                    <li>Aggregated statistics and geospatial analysis derived from all contributions</li>
                </ul>
            </section>

            <section :id="ids.dataNotCollected" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    4. Data We Do Not Collect
                </h2>
                <ul class="space-y-3 ml-6 list-disc text-gray-700 leading-relaxed">
                    <li>Cookies (beyond essential session cookies)</li>
                    <li>IP addresses</li>
                    <li>Referring or destination websites</li>
                    <li>Browser type or clickstream data</li>
                    <li>Detailed usage tracking outside of essential server logs</li>
                </ul>
            </section>

            <section :id="ids.publicData" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    5. Public Availability of Data
                </h2>
                <p class="text-gray-700 leading-relaxed">
                    By submitting data, you agree that images, their contents, location, and time of capture may be made
                    public. By default, contributions are anonymous, but you may choose to display your full name,
                    username, or organisation.
                </p>
            </section>

            <section :id="ids.safety" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    6. Safety Guidance
                </h2>
                <ul class="space-y-3 ml-6 list-disc text-gray-700 leading-relaxed">
                    <li>Do not touch hazardous litter without proper protective equipment.</li>
                    <li>Do not collect data in unsafe locations.</li>
                    <li>Avoid including identifiable people in images.</li>
                    <li>Do not upload from locations you wish to remain private (e.g., your home address).</li>
                    <li>Please be careful when collecting data in a busy street or along a roadside.</li>
                    <li>Remain vigilant of theives and people who may snatch your device when its unlocked.</li>
                </ul>
            </section>

            <section :id="ids.feedback" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">8. Feedback</h2>
                <p class="text-gray-700 leading-relaxed">
                    We welcome feedback on this policy and our services. Email
                    <a :href="`mailto:${contactEmail}`" class="text-blue-600 hover:text-blue-800 underline">{{
                        contactEmail
                    }}</a
                    >.
                </p>
            </section>
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed, defineProps } from 'vue';

const props = defineProps({
    lastUpdated: { type: String, default: 'Sunday 10th August 2025, 14:53' },
    profileUrl: { type: String, default: 'https://openlittermap.com/profile' },
    contactEmail: { type: String, default: 'info@openlittermap.com' },
    showToc: { type: Boolean, default: true },
});

const ids = {
    purpose: 'purpose',
    ageRestrictions: 'age-restrictions',
    dataWeCollect: 'data-we-collect',
    dataNotCollected: 'data-not-collected',
    publicData: 'public-data',
    safety: 'safety',
    feedback: 'feedback',
};

const toc = computed(() => [
    { id: ids.purpose, label: '1. Purpose of Our Services' },
    { id: ids.ageRestrictions, label: '2. Age Restrictions' },
    { id: ids.dataWeCollect, label: '3. Data We Collect' },
    { id: ids.dataNotCollected, label: '4. Data We Do Not Collect' },
    { id: ids.publicData, label: '5. Public Availability of Data' },
    { id: ids.safety, label: '7. Safety Guidance' },
    { id: ids.feedback, label: '8. Feedback' },
]);
</script>
