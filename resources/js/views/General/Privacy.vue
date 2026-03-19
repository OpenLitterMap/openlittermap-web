<template>
    <section class="max-w-4xl mx-auto px-6 py-8 bg-white">
        <header class="mb-12 pb-8 border-b-2 border-gray-200">
            <h1 class="text-3xl font-bold text-gray-900 mb-3">Privacy Policy</h1>
            <p class="text-gray-500 mb-2">Last Updated: {{ lastUpdated }}</p>
            <p class="text-gray-500 mb-6">
                <strong class="text-gray-700">Data Controller:</strong> {{ dataController }}
            </p>
            <div class="space-y-4 text-gray-700 leading-relaxed">
                <p>
                    This policy should be read alongside your rights under the EU General Data Protection Regulation
                    (GDPR &mdash; Regulation 2016/679) and the Irish Data Protection Act 2018.
                </p>
                <p>
                    OpenLitterMap is an open-source platform for mapping litter and plastic pollution. It relies on
                    public participation, geotagged images, and open environmental data. Because the platform is open
                    source, our approach to code, data, and privacy can be examined in public at
                    <a
                        :href="orgUrl"
                        target="_blank"
                        rel="noopener"
                        class="text-blue-600 hover:text-blue-800 underline"
                        >github.com/OpenLitterMap</a
                    >.
                </p>
            </div>
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
                        OpenLitterMap allows people to contribute geotagged photographs of litter and plastic pollution.
                        These contributions can support research, education, public participation, and local
                        environmental action.
                    </p>
                    <p>
                        At the same time, location data and timestamps can reveal where a person was at a particular
                        moment. That creates obvious privacy considerations. This policy sets out what data is collected,
                        what may become public, what remains private, and what choices users have.
                    </p>
                </div>
            </section>

            <!-- 2. Anonymous by Default -->
            <section :id="ids.anonymous" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    2. Anonymous by Default
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        By default, OpenLitterMap does not display your identity publicly. When an account is created,
                        name and username visibility settings are off, and public profile visibility is off. A person's
                        name is set to null at registration &mdash; it is optional and never required.
                    </p>
                    <p>
                        A person may contribute data to the map without making their identity public. Users can later
                        change these settings if they choose. These defaults are intended to reduce unnecessary exposure
                        of personal identity.
                    </p>
                </div>
            </section>

            <!-- 3. What We Collect -->
            <section :id="ids.whatWeCollect" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    3. What We Collect
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>To run the service, we need a small amount of account and contribution data:</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b-2 border-gray-300">
                                    <th class="py-3 pr-4 font-semibold text-gray-900">Data</th>
                                    <th class="py-3 pr-4 font-semibold text-gray-900">Required</th>
                                    <th class="py-3 font-semibold text-gray-900">Purpose</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr>
                                    <td class="py-3 pr-4">Email address</td>
                                    <td class="py-3 pr-4">Yes</td>
                                    <td class="py-3">Account creation, password recovery</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Password</td>
                                    <td class="py-3 pr-4">Yes</td>
                                    <td class="py-3">Authentication (stored encrypted, never plaintext)</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Username</td>
                                    <td class="py-3 pr-4">No</td>
                                    <td class="py-3">Auto-generated if not provided. Display is user-controlled</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Name</td>
                                    <td class="py-3 pr-4">No</td>
                                    <td class="py-3">Optional. Display is user-controlled</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Images</td>
                                    <td class="py-3 pr-4">Yes (to contribute)</td>
                                    <td class="py-3">Geotagged photographs of litter and pollution</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">EXIF metadata</td>
                                    <td class="py-3 pr-4">Automatic</td>
                                    <td class="py-3">GPS coordinates and timestamp extracted from photos</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Tags</td>
                                    <td class="py-3 pr-4">User-applied</td>
                                    <td class="py-3">
                                        Litter classifications (150+ pre-defined types, plus custom tags)
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        The platform does not require a phone number, date of birth, or home address. We do not see or
                        store payment card numbers &mdash; Stripe processes optional payments directly.
                    </p>
                </div>
            </section>

            <!-- 4. What Becomes Public -->
            <section :id="ids.whatBecomesPublic" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    4. What Becomes Public
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        OpenLitterMap works with location data. For that reason, it is important to be clear about what
                        may become public.
                    </p>
                    <p>
                        When a photo is both visible and verified, the following may be publicly displayed: the image,
                        its GPS coordinates, its timestamp, the tags applied to it, and whether the litter was picked
                        up.
                    </p>
                    <p>
                        Your email address and password are never public. Your name and username are only public if you
                        choose to display them.
                    </p>
                    <p>
                        Importantly, photo visibility and identity visibility are separate. A photo may appear on the
                        public map without identifying the contributor. Users can set all new photos to private by
                        default, or toggle any individual photo between public and private at any time.
                    </p>
                    <p>
                        School photos are always private until a teacher approves them, regardless of any other setting.
                    </p>
                </div>
            </section>

            <!-- 5. What We Do Not Use for Tracking -->
            <section :id="ids.noTracking" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    5. What We Do Not Use for Tracking
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b-2 border-gray-300">
                                    <th class="py-3 pr-4 font-semibold text-gray-900">What</th>
                                    <th class="py-3 font-semibold text-gray-900">Our practice</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr>
                                    <td class="py-3 pr-4">IP addresses</td>
                                    <td class="py-3">Not used for user tracking, profiling, or analytics</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Tracking cookies</td>
                                    <td class="py-3">
                                        Not used. Only essential session, CSRF, and authentication cookies
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Third-party analytics</td>
                                    <td class="py-3">
                                        None. No Google Analytics, Hotjar, Mixpanel, or equivalent
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Advertising pixels</td>
                                    <td class="py-3">None. No Facebook Pixel or advertising scripts</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Referral/destination URLs</td>
                                    <td class="py-3">Not logged</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Clickstream or behavioural data</td>
                                    <td class="py-3">Not collected</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        We load Stripe to process optional payments and Font Awesome for icons. We do not use these
                        services for advertising, analytics, or behavioural profiling.
                    </p>
                    <p>We use only essential cookies needed to operate the service.</p>
                </div>
            </section>

            <!-- 6. Privacy Controls -->
            <section :id="ids.privacyControls" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    6. Privacy Controls
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        Users have control over their visibility on the platform. All identity settings default to off.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b-2 border-gray-300">
                                    <th class="py-3 pr-4 font-semibold text-gray-900">Setting</th>
                                    <th class="py-3 pr-4 font-semibold text-gray-900">What it controls</th>
                                    <th class="py-3 font-semibold text-gray-900">Default</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr>
                                    <td class="py-3 pr-4">Show name on maps</td>
                                    <td class="py-3 pr-4">Your name next to your map points</td>
                                    <td class="py-3">Off</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Show username on maps</td>
                                    <td class="py-3 pr-4">Your username next to your map points</td>
                                    <td class="py-3">Off</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Show name on leaderboards</td>
                                    <td class="py-3 pr-4">Your name in rankings</td>
                                    <td class="py-3">Off</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Show username on leaderboards</td>
                                    <td class="py-3 pr-4">Your username in rankings</td>
                                    <td class="py-3">Off</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Public profile</td>
                                    <td class="py-3 pr-4">Whether others can view your profile page</td>
                                    <td class="py-3">Off</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Photos public by default</td>
                                    <td class="py-3 pr-4">Whether new uploads appear on the public map</td>
                                    <td class="py-3">On</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Per-photo visibility</td>
                                    <td class="py-3 pr-4">
                                        Toggle any individual photo between public and private
                                    </td>
                                    <td class="py-3">&mdash;</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-4">Prevent others tagging my photos</td>
                                    <td class="py-3 pr-4">
                                        Block other users from adding tags to your uploads
                                    </td>
                                    <td class="py-3">Off</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>
                        These are independent controls. A person can show their username on leaderboards without
                        revealing it on the map, or vice versa.
                    </p>
                    <p>
                        Settings can be managed at
                        <router-link to="/settings" class="text-blue-600 hover:text-blue-800 underline"
                            >/settings</router-link
                        >.
                    </p>
                </div>
            </section>

            <!-- 7. Your Rights -->
            <section :id="ids.rights" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    7. Your Rights
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>Under GDPR, users have a number of rights. The platform provides the following:</p>
                    <div class="space-y-4">
                        <p>
                            <strong class="text-gray-900">Right to erasure (Article 17).</strong> Users can delete their
                            account at any time from their settings page. This is a permanent deletion &mdash; personal
                            information (name, email, username, OAuth tokens, verification logs, roles, team
                            associations, leaderboard entries, statistics) is destroyed. Photos are preserved as
                            anonymous contributions to the open dataset with the user ID nullified. Password confirmation
                            is required.
                        </p>
                        <p>
                            <strong class="text-gray-900">Right to data portability (Article 20).</strong> Users can
                            export their data as CSV from their profile. The export includes photo metadata, tags, GPS
                            coordinates, timestamps, and classification data. No personal information is included in any
                            export.
                        </p>
                        <p>
                            <strong class="text-gray-900">Right to rectification (Article 16).</strong> Name, username,
                            and email can be updated at any time in settings.
                        </p>
                        <p>
                            <strong class="text-gray-900">Right to restrict processing (Article 18).</strong> Photos can
                            be set to private, visibility toggles can be disabled, and other users can be prevented from
                            tagging your photos.
                        </p>
                        <p>
                            <strong class="text-gray-900">Right to object (Article 21).</strong> Contact
                            <a
                                :href="`mailto:${contactEmail}`"
                                class="text-blue-600 hover:text-blue-800 underline"
                                >{{ contactEmail }}</a
                            >. We will action any request promptly.
                        </p>
                        <p>
                            <strong class="text-gray-900">Right to lodge a complaint.</strong> Users may contact the
                            Irish Data Protection Commission at
                            <a
                                href="https://www.dataprotection.ie"
                                target="_blank"
                                rel="noopener"
                                class="text-blue-600 hover:text-blue-800 underline"
                                >dataprotection.ie</a
                            >.
                        </p>
                    </div>
                </div>
            </section>

            <!-- 8. Young People & Schools -->
            <section :id="ids.youngPeople" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    8. Young People &amp; Schools
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        OpenLitterMap is not intended for children under 13. If we become aware that a child under 13
                        has provided personal data, we will remove it.
                    </p>
                    <p>
                        Users aged 13&ndash;17 may participate with parental or guardian supervision only. Supervisors
                        should ensure safe practices, particularly around hazardous litter.
                    </p>
                    <p>
                        School teams have additional safeguards implemented in the platform, not stated only as policy:
                    </p>
                    <ul class="space-y-2 ml-6 list-disc">
                        <li>
                            All school uploads are private by default and do not appear on the public map, regardless of
                            user settings.
                        </li>
                        <li>A teacher must approve each upload before it becomes publicly visible.</li>
                        <li>
                            Student identities are automatically masked in public-facing contexts &mdash; other users see
                            &ldquo;Student 1&rdquo;, &ldquo;Student 2&rdquo;, not real names.
                        </li>
                        <li>
                            Only teachers and team leaders can see real student names, for classroom management purposes.
                        </li>
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
                        provides guided digital skills training for collecting high-quality environmental data safely and
                        responsibly.
                    </p>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <p class="text-red-800 font-semibold">
                            If you or a young person suffers an injury, call 112, 999, or your local emergency number
                            immediately.
                        </p>
                    </div>
                </div>
            </section>

            <!-- 9. Verification and Visibility -->
            <section :id="ids.verification" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    9. Verification and Visibility
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        Contributions do not all move through the platform in the same way. Visibility depends on both
                        privacy settings and review status.
                    </p>
                    <p>
                        New users' tagged photos are stored but do not appear on the public map until reviewed by an
                        administrator. Once an account is upgraded to trusted status, based on consistent and
                        high-quality contributions, tagged uploads appear on the map without delay.
                    </p>
                    <p>
                        School uploads require teacher approval before becoming public, regardless of trust status.
                    </p>
                    <p>
                        In practice, the public map displays only photos that are both marked as public and have been
                        verified by an administrator, trusted user, or teacher.
                    </p>
                </div>
            </section>

            <!-- 10. Legal Basis for Processing -->
            <section :id="ids.legalBasis" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    10. Legal Basis for Processing
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-gray-700">
                        <thead>
                            <tr class="border-b-2 border-gray-300">
                                <th class="py-3 pr-4 font-semibold text-gray-900">Processing activity</th>
                                <th class="py-3 font-semibold text-gray-900">Legal basis (GDPR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr>
                                <td class="py-3 pr-4">Account creation and authentication</td>
                                <td class="py-3">Contract performance (Art. 6(1)(b))</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4">Publishing geotagged environmental data</td>
                                <td class="py-3">
                                    Legitimate interest (Art. 6(1)(f)) &mdash; open environmental science
                                </td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4">Optional display of name/username</td>
                                <td class="py-3">Consent (Art. 6(1)(a)) &mdash; user-controlled, off by default</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4">Service emails (password resets, account notices)</td>
                                <td class="py-3">Contract performance (Art. 6(1)(b))</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4">Optional updates or marketing</td>
                                <td class="py-3">Consent (Art. 6(1)(a))</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-4">Payment processing via Stripe</td>
                                <td class="py-3">Contract performance (Art. 6(1)(b))</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 11. Data Retention -->
            <section :id="ids.dataRetention" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    11. Data Retention
                </h2>
                <ul class="space-y-3 ml-6 list-disc text-gray-700 leading-relaxed">
                    <li>
                        <strong class="text-gray-900">Account data:</strong> Retained until the account is deleted.
                    </li>
                    <li>
                        <strong class="text-gray-900">Photos and tags:</strong> Retained as part of the open dataset. If
                        a photo is deleted, it is removed. If an account is deleted, photos are anonymised and retained
                        as open data.
                    </li>
                    <li>
                        <strong class="text-gray-900">Server logs:</strong> Essential logs only, rotated regularly.
                    </li>
                    <li>
                        <strong class="text-gray-900">Payment records:</strong> Retained as required by financial
                        regulation.
                    </li>
                </ul>
            </section>

            <!-- 12. Data Sharing -->
            <section :id="ids.dataSharing" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    12. Data Sharing
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>We do not sell, rent, or share personal data with any third party.</p>
                    <p>
                        The only external service that receives user data is Stripe, which processes optional payments.
                        Stripe's privacy policy applies to payment data.
                    </p>
                    <p>
                        The open dataset (geographic data, tags, timestamps) is shared with the world under ODbL. It
                        contains no personal information.
                    </p>
                </div>
            </section>

            <!-- 13. Security and AI-Assisted Development -->
            <section :id="ids.security" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">
                    13. Security and AI-Assisted Development
                </h2>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>
                        Version 5 of OpenLitterMap was developed with significant use of AI-assisted coding tools. We
                        are disclosing this because software produced quickly still requires careful review. The codebase
                        remains open to inspection, testing, and improvement by maintainers and contributors.
                    </p>
                    <p>
                        If you identify a security concern, please contact
                        <a
                            :href="`mailto:${contactEmail}`"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >{{ contactEmail }}</a
                        >.
                    </p>
                </div>
            </section>

            <!-- 14. Safety -->
            <section :id="ids.safety" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">14. Safety</h2>
                <ul class="space-y-3 ml-6 list-disc text-gray-700 leading-relaxed">
                    <li>
                        Do not touch hazardous litter (needles, glass, chemicals) without proper protective equipment.
                    </li>
                    <li>Do not collect data in unsafe locations or near busy roads.</li>
                    <li>
                        Avoid including identifiable people in photographs &mdash; faces, tattoos, licence plates, or
                        distinctive features.
                    </li>
                    <li>Do not upload from locations you wish to remain private.</li>
                    <li>Remain vigilant of theft and people who may take your device while it is unlocked.</li>
                    <li>
                        Report any safety concern to
                        <a
                            :href="`mailto:${contactEmail}`"
                            class="text-blue-600 hover:text-blue-800 underline"
                            >{{ contactEmail }}</a
                        >.
                    </li>
                </ul>
            </section>

            <!-- 15. Changes -->
            <section :id="ids.changes" class="scroll-mt-4">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-300">15. Changes</h2>
                <p class="text-gray-700 leading-relaxed">
                    We update this policy as the platform evolves. Material changes will be communicated via the
                    website. The date at the top of this page reflects the most recent revision.
                </p>
            </section>
        </div>

        <!-- Contact -->
        <footer class="mt-16 pt-8 border-t-2 border-gray-200" :id="ids.contact">
            <h2 class="text-2xl font-semibold text-gray-900 mb-4">Contact</h2>
            <div class="text-gray-700 leading-relaxed space-y-1">
                <p>For any privacy question, data request, or concern:</p>
                <p>
                    <strong class="text-gray-900">Data Controller:</strong> {{ dataController }}
                </p>
                <p>
                    <strong class="text-gray-900">Email:</strong>
                    <a
                        :href="`mailto:${contactEmail}`"
                        class="text-blue-600 hover:text-blue-800 underline"
                        >{{ contactEmail }}</a
                    >
                </p>
                <p>
                    <strong class="text-gray-900">Location:</strong> Cork, Ireland
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
                <p class="pt-2">
                    You may also contact the Irish Data Protection Commission at
                    <a
                        href="https://www.dataprotection.ie"
                        target="_blank"
                        rel="noopener"
                        class="text-blue-600 hover:text-blue-800 underline"
                        >dataprotection.ie</a
                    >.
                </p>
            </div>
        </footer>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';

defineProps({
    lastUpdated: { type: String, default: '17 March 2026' },
    dataController: { type: String, default: 'Se\u00e1n Lynch, Cork, Ireland' },
    orgUrl: { type: String, default: 'https://github.com/OpenLitterMap' },
    contactEmail: { type: String, default: 'info@openlittermap.com' },
    showToc: { type: Boolean, default: true },
});

const ids = {
    purpose: 'purpose',
    anonymous: 'anonymous-by-default',
    whatWeCollect: 'what-we-collect',
    whatBecomesPublic: 'what-becomes-public',
    noTracking: 'no-tracking',
    privacyControls: 'privacy-controls',
    rights: 'your-rights',
    youngPeople: 'young-people',
    verification: 'verification',
    legalBasis: 'legal-basis',
    dataRetention: 'data-retention',
    dataSharing: 'data-sharing',
    security: 'security',
    safety: 'safety',
    changes: 'changes',
    contact: 'contact',
};

const toc = computed(() => [
    { id: ids.purpose, label: '1. Purpose' },
    { id: ids.anonymous, label: '2. Anonymous by Default' },
    { id: ids.whatWeCollect, label: '3. What We Collect' },
    { id: ids.whatBecomesPublic, label: '4. What Becomes Public' },
    { id: ids.noTracking, label: '5. What We Do Not Use for Tracking' },
    { id: ids.privacyControls, label: '6. Privacy Controls' },
    { id: ids.rights, label: '7. Your Rights' },
    { id: ids.youngPeople, label: '8. Young People & Schools' },
    { id: ids.verification, label: '9. Verification and Visibility' },
    { id: ids.legalBasis, label: '10. Legal Basis for Processing' },
    { id: ids.dataRetention, label: '11. Data Retention' },
    { id: ids.dataSharing, label: '12. Data Sharing' },
    { id: ids.security, label: '13. Security and AI-Assisted Development' },
    { id: ids.safety, label: '14. Safety' },
    { id: ids.changes, label: '15. Changes' },
    { id: ids.contact, label: 'Contact' },
]);
</script>
