<template>
    <section class="max-w-4xl mx-auto px-6 py-8 bg-white">
        <header class="mb-12 pb-8 border-b-2 border-gray-200">
            <h1 class="text-3xl font-bold text-gray-900 mb-3">Terms &amp; Conditions</h1>
            <p class="text-gray-500 mb-6">Last Updated: {{ lastUpdated }}</p>
            <p class="text-gray-700 leading-relaxed">
                OpenLitterMap is an open-source platform for mapping litter and plastic pollution. It relies on public
                participation, geotagged images, and open environmental data. Because the platform is open source, our
                approach to code, data, and privacy can be examined in public at
                <a :href="orgUrl" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 underline"
                    >github.com/OpenLitterMap</a
                >.
            </p>
            <nav v-if="showToc" aria-label="Table of contents" class="mt-8 bg-gray-50 p-6 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Contents</h3>
                <ol class="space-y-2">
                    <li v-for="item in toc" :key="item.id">
                        <a
                            :href="`#${item.id}`"
                            class="text-blue-600 no-underline hover:underline hover:text-blue-800 transition-colors"
                            >{{ item.label }}</a
                        >
                    </li>
                </ol>
            </nav>
        </header>

        <div class="space-y-12">
            <!-- 1. Purpose -->
            <section :id="ids.purpose" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">1. Purpose</h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        OpenLitterMap is a real-time geographic data collection platform built in Cork, Ireland and has
                        been used so far in over 110 countries. After starting the research in 2008, OpenLitterMap
                        launched in 2017 empowering people around the world with the production of geographic
                        information who have contributed more than 500,000 geotagged observations with over 850,000
                        classified tags. The platform has been cited in
                        <router-link to="/references" class="text-blue-600 hover:text-blue-800 underline"
                            >98+ peer-reviewed publications</router-link
                        >
                        including Nature and the World Bank, and has previously been identified as a Digital Public Good
                        by the Digital Public Goods Alliance.
                    </p>
                    <p>
                        Litter and plastic pollution is the first application of this real-time data collection
                        technology. Custom tags allow contributors to extend the platform to other observations extending
                        our pre-defined list of litter tags to start collecting data about civic issues, biodiversity,
                        etc.
                    </p>
                </div>
            </section>

            <!-- 2. Open Source Code -->
            <section :id="ids.code" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    2. Open Source Code &mdash; GPL v3
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        All platform code is licensed under the
                        <a
                            :href="gplUrl"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >GNU General Public License v3</a
                        >. You can view, download, learn from, fork, and modify the code. The only obligation is to
                        release modifications under the same licence.
                    </p>
                    <p>
                        OpenLitterMap is an evolving project. There may be bugs and incomplete features. Development is
                        now largely AI-driven. Not all code has been human-audited yet. Open source reviews and
                        contribution is encouraged.
                    </p>

                    <h3 class="text-lg font-medium text-gray-900 pt-2">AI-assisted development</h3>
                    <p>
                        Version 5 of OpenLitterMap was developed with significant use of AI-assisted coding tools. The
                        codebase remains open to inspection, testing, and improvement by maintainers and contributors.
                    </p>
                    <p>
                        If you identify an issue, submit it at
                        <a
                            :href="orgUrl"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >github.com/OpenLitterMap</a
                        >.
                    </p>
                </div>
            </section>

            <!-- 3. Open Data -->
            <section :id="ids.data" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    3. Open Data &mdash; ODbL
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        All geographic data contributed to OpenLitterMap is licensed under the
                        <a
                            :href="odblUrl"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >Open Database License (ODbL)</a
                        >. You can use the data for any purpose &mdash; research, education, commercial projects,
                        advocacy, journalism &mdash; provided you attribute OpenLitterMap and share derivative databases
                        under the same licence.
                    </p>

                    <h3 class="text-lg font-medium text-gray-900 pt-2">What the data includes</h3>
                    <p>
                        GPS coordinates (often centimetre-level accuracy), timestamps, pre-defined litter
                        classifications (150+ types), custom tags, brand identifications (1,500+), and whether litter
                        was picked up.
                    </p>

                    <h3 class="text-lg font-medium text-gray-900 pt-2">What the data does not include</h3>
                    <p>
                        No usernames, names, email addresses, or other personal information. Data downloads are PII-free
                        by design.
                    </p>

                    <h3 class="text-lg font-medium text-gray-900 pt-2">Location data</h3>
                    <p>
                        If a photo is public and visible on the map, its GPS coordinates and timestamp may be publicly
                        displayed. This supports open environmental monitoring, but it also means contributors should
                        exercise care.
                    </p>
                    <p>
                        Do not upload from your home, workplace, or private locations unless you are comfortable with
                        that location becoming public. Focus contributions on public and natural environments like towns
                        and beaches.
                    </p>
                </div>
            </section>

            <!-- 4. Privacy and Visibility -->
            <section :id="ids.privacy" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    4. Privacy and Visibility
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        By default, OpenLitterMap does not display your identity publicly. When an account is created,
                        name and username visibility settings are off, and public profile visibility is off. Your
                        uploads will appear on the global map, but the image will not be visible until it has been
                        reviewed, or until you become a trusted user. Your information will not be visible on any Map,
                        Popup, Leaderboard, Location etc unless you enable them.
                    </p>
                    <p>Users can manage these settings independently:</p>
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>Whether your name or username appears on the map</li>
                        <li>Whether your name or username appears on leaderboards</li>
                        <li>Whether your profile is publicly visible</li>
                        <li>Whether your photos are public by default</li>
                        <li>Visibility of each individual photo</li>
                    </ul>
                    <p>
                        See our
                        <router-link to="/privacy" class="text-blue-600 hover:text-blue-800 underline"
                            >Privacy Policy</router-link
                        >
                        for full details.
                    </p>
                </div>
            </section>

            <!-- 5. Data We Collect -->
            <section :id="ids.dataWeCollect" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    5. Data We Collect
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>To run the service, we need a small amount of account and contribution data:</p>
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>
                            <strong class="text-gray-900">Account:</strong> Email address and password (required).
                            Username (auto-generated if not provided). Name (optional).
                        </li>
                        <li>
                            <strong class="text-gray-900">Content:</strong> Images you upload, including EXIF metadata
                            (GPS location, timestamp) if available.
                        </li>
                        <li>
                            <strong class="text-gray-900">Tags:</strong> Pre-defined and custom litter classifications
                            you apply to each image.
                        </li>
                        <li>
                            <strong class="text-gray-900">Teams:</strong> Names and details of teams you create or join.
                        </li>
                    </ul>
                    <p>The platform does not require a phone number, date of birth, or home address.</p>
                </div>
            </section>

            <!-- 6. Data We Do Not Use for Tracking -->
            <section :id="ids.dataNotTracked" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    6. Data We Do Not Use for Tracking
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>We do not use IP addresses for user tracking, profiling, or analytics</li>
                        <li>We do not use third-party analytics or advertising trackers</li>
                        <li>
                            We do not use tracking cookies &mdash; only essential session, CSRF, and authentication
                            cookies
                        </li>
                        <li>We do not use advertising pixels or third-party tracking scripts</li>
                        <li>We do not log referral or destination URLs</li>
                        <li>We do not collect clickstream or behavioural data</li>
                    </ul>
                    <p>
                        We load Stripe to process optional payments and Font Awesome for icons. We do not use these
                        services for advertising, analytics, or behavioural profiling.
                    </p>
                </div>
            </section>

            <!-- 7. Your Rights -->
            <section :id="ids.rights" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    7. Your Rights
                </h2>
                <ul class="space-y-3 ml-6 list-disc text-gray-700 leading-relaxed">
                    <li>Delete any photo you have uploaded.</li>
                    <li>
                        Delete your account entirely. This is a permanent deletion &mdash; personal information is
                        removed, photos are preserved as anonymous contributions to the open dataset, and leaderboard
                        entries are cleared.
                    </li>
                    <li>Not yet working but soon: Export your data.</li>
                    <li>
                        Control your visibility through privacy settings &mdash; maps, leaderboards, profile, photo
                        defaults, and individual photos.
                    </li>
                    <li>
                        Contact
                        <a
                            :href="`mailto:${contactEmail}`"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >{{ contactEmail }}</a
                        >
                        for any data request not covered above.
                    </li>
                </ul>
            </section>

            <!-- 8. Young People, Schools & Guided Programmes -->
            <section :id="ids.youngPeople" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    8. Young People, Schools &amp; Guided Programmes
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        OpenLitterMap is not intended for children under 13. If we become aware that a child under 13
                        has provided personal data, we will remove it.
                    </p>
                    <p>
                        Users aged 13&ndash;17 may participate with parental or guardian supervision only.
                    </p>
                    <p>
                        School teams have additional safeguards implemented in the platform, not stated only as policy:
                    </p>
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>
                            All school uploads are private by default and do not appear on the public map, regardless of
                            user settings.
                        </li>
                        <li>A teacher must approve each upload before it becomes visible.</li>
                        <li>
                            Student identities are automatically masked in public-facing contexts &mdash; other users
                            see &ldquo;Student 1&rdquo;, &ldquo;Student 2&rdquo;, not real names.
                        </li>
                        <li>Teachers and team leaders can see real names for classroom management.</li>
                    </ul>
                    <p>
                        If you are a school, educator, or youth organisation,
                        <a
                            href="https://litterweek.org"
                            target="_blank"
                            rel="noopener"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >LitterWeek.org</a
                        >
                        provides guided digital skills training to understand the high-quality real-time data collection
                        purpose of technology.
                    </p>
                </div>
            </section>

            <!-- 9. Acceptable Use -->
            <section :id="ids.acceptableUse" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    9. Acceptable Use
                </h2>
                <ul class="space-y-3 ml-6 list-disc text-gray-700 leading-relaxed">
                    <li>
                        Only upload photos of litter or other civic issues. Potholes, graffiti, blocked drains etc are
                        also acceptable.
                    </li>
                    <li>Please don't include your feet or shadows in your photos.</li>
                    <li>Do collect good quality data &mdash; individual items are preferred.</li>
                    <li>
                        Please don't collect poor quality data that makes it difficult to verify the contents of the
                        image.
                    </li>
                    <li>
                        Do not misuse our APIs, attempt to disrupt the service, or scrape data in violation of our
                        licences.
                    </li>
                </ul>
            </section>

            <!-- 10. Changes -->
            <section :id="ids.changes" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">10. Changes</h2>
                <p class="text-gray-700 leading-relaxed">
                    OpenLitterMap is a rapidly evolving project. Expect changes.
                </p>
            </section>
        </div>

        <!-- Contact -->
        <footer class="mt-16 pt-8 border-t-2 border-gray-200" :id="ids.contact">
            <h2 class="text-2xl font-semibold text-gray-900 mb-4">Contact</h2>
            <div class="text-gray-700 leading-relaxed space-y-1">
                <p>
                    <strong class="text-gray-900">Email:</strong>
                    <a
                        :href="`mailto:${contactEmail}`"
                        class="text-blue-600 hover:text-blue-800 underline"
                        >{{ contactEmail }}</a
                    >
                </p>
                <p>
                    <strong class="text-gray-900">Code:</strong>
                    <a
                        :href="orgUrl"
                        target="_blank"
                        rel="noopener"
                        class="text-blue-600 hover:text-blue-800 underline"
                        >github.com/OpenLitterMap</a
                    >
                </p>
                <p>
                    <strong class="text-gray-900">Training:</strong>
                    <a
                        href="https://litterweek.org"
                        target="_blank"
                        rel="noopener"
                        class="text-blue-600 hover:text-blue-800 underline"
                        >LitterWeek.org</a
                    >
                </p>
            </div>
        </footer>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';

defineProps({
    lastUpdated: { type: String, default: '17 March 2026' },
    orgUrl: { type: String, default: 'https://github.com/OpenLitterMap' },
    gplUrl: { type: String, default: 'https://www.gnu.org/licenses/gpl-3.0.en.html' },
    odblUrl: { type: String, default: 'https://opendatacommons.org/licenses/odbl/' },
    contactEmail: { type: String, default: 'info@openlittermap.com' },
    showToc: { type: Boolean, default: true },
});

const ids = {
    purpose: 'purpose',
    code: 'code',
    data: 'data',
    privacy: 'privacy',
    dataWeCollect: 'data-we-collect',
    dataNotTracked: 'data-not-tracked',
    rights: 'rights',
    youngPeople: 'young-people',
    acceptableUse: 'acceptable-use',
    changes: 'changes',
    contact: 'contact',
};

const toc = computed(() => [
    { id: ids.purpose, label: '1. Purpose' },
    { id: ids.code, label: '2. Open Source Code \u2014 GPL v3' },
    { id: ids.data, label: '3. Open Data \u2014 ODbL' },
    { id: ids.privacy, label: '4. Privacy and Visibility' },
    { id: ids.dataWeCollect, label: '5. Data We Collect' },
    { id: ids.dataNotTracked, label: '6. Data We Do Not Use for Tracking' },
    { id: ids.rights, label: '7. Your Rights' },
    { id: ids.youngPeople, label: '8. Young People, Schools & Guided Programmes' },
    { id: ids.acceptableUse, label: '9. Acceptable Use' },
    { id: ids.changes, label: '10. Changes' },
    { id: ids.contact, label: 'Contact' },
]);
</script>
