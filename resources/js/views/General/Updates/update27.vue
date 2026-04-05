<template>
    <div class="update-content">
        <h2>Update 27: New Mobile Apps & More!</h2>

        <p>Since we launched v5, we've been shipping improvements nearly every day. The mobile apps have been completely rebuilt and the web platform has had 60+ updates. Here's what's new.</p>

        <h3>📱 Updated Mobile Apps</h3>

        <p>Updated apps for <strong>iOS</strong> and <strong>Android</strong> are now available.</p>

        <ul class="features">
            <li>
                <strong>🏠 A completely new mobile app experience</strong> — A live dashboard showing real-time impact alongside the global community. Total tags, photos, and people with animated counters that update on pull-to-refresh. Personal stats, XP, level progress, and streak are all visible at a glance.
            </li>
            <li>
                <strong>📸 Camera & faster photo-to-tag workflow</strong> — The in-app camera is back — take a photo directly from the app. Recent geotagged photos also appear right on the dashboard. The entire flow is now 3 steps: open photo, tag it, submit.
            </li>
            <li>
                <strong>⭐ Quick Tags — (v7.6.0) save your favourites</strong> — Tap the star on any tag to save it for faster tagging. Starred tags appear as suggested tags right above the search bar so you don't even have to search. You can give each one a custom name, set a default quantity, and even pre-define materials and brands. Available to edit in settings.
            </li>
            <li>
                <strong>🏷️ Smarter tagging</strong> — Previously there was no relationship between tags. Since OLM v5 we have added a new object-based tagging system. On mobile a new tag detail sheet makes it easy to add materials, brands, and custom tags to any object. Many more improvements still to make.
            </li>
            <li>
                <strong>🎓 New onboarding tutorial</strong> — A new tutorial on both web and mobile walks first-time users through submitting their first data points: take a photo, tag it, upload it, and see it on the map. GPS camera setup instructions are built in.
            </li>
            <li>
                <strong>📍 GPS fixes for Android</strong> — Critical GPS bugs resolved which was blocking some Android users from uploading.
            </li>
            <li>
                <strong>📷 Photo permissions</strong> — Easier to control which photos the app has permission to import.
            </li>
            <li>
                <strong>🛠 And more</strong> — New tab bar with cleaner navigation. iPad layout support. Camera preview with GPS coordinates and delete option. Improved update prompt when a new version is available & significant code improvements everywhere.
            </li>
        </ul>

        <p class="app-links">
            Update now on the <a href="https://apps.apple.com/ie/app/openlittermap/id1475982147" target="_blank">App Store</a> or <a href="https://play.google.com/store/apps/details?id=com.geotech.openlittermap" target="_blank">Google Play</a>.
        </p>

        <h3>✨ Web Platform Improvements</h3>

        <p>60+ updates since the last email announcing the v5 launch. Highlights include:</p>

        <ul class="features">
            <li>
                <strong>🎓 New onboarding tutorial</strong> — A guided tutorial on both web and mobile walks new users through their first data submission. After tagging, see the upload on the global map with a shareable link.
            </li>
            <li>
                <strong>🗺️ Improved map</strong> — Faster cluster rendering, fixed map drift on refresh, simplified cluster markers, live event count in the browser tab.
            </li>
            <li>
                <strong>📈 Impact reports restored</strong> — Weekly, monthly, and annual impact report tweets are back online at <a href="https://x.com/OLM_bot" target="_blank">x.com/OLM_bot</a>.
            </li>
            <li>
                <strong>⚡ Performance</strong> — Profile loads dropped from 40s to under 1s. Login is now a single API call.
            </li>
            <li>
                <strong>🔧 Upload fix</strong> — Fixed "Page Expired" errors that some users hit when uploading after login.
            </li>
            <li>
                <strong>📧 Email fixes</strong> — Improved email deliverability and fixed forgot-password not working for some users.
            </li>
        </ul>

        <p class="meta">Full changelog: <router-link to="/changelog">openlittermap.com/changelog</router-link></p>

        <h3>🐛 Help Us Improve</h3>

        <p>Found a bug or have a suggestion? Let us know:</p>
        <ul>
            <li>📩 <a href="mailto:info@openlittermap.com">info@openlittermap.com</a></li>
            <li>💻 <a href="https://github.com/OpenLitterMap/openlittermap-web" target="_blank">GitHub</a> · <a href="https://discord.gg/NAEUBwtAhT" target="_blank">Discord</a> · <a href="https://join.slack.com/t/openlittermap/shared_invite/zt-29p12603a-MlzeSdMK~0mc1tCLV_bxUA" target="_blank">Slack</a></li>
        </ul>

        <h3>🏫 LitterWeek.org</h3>

        <p><a href="https://litterweek.org" target="_blank"><strong>LitterWeek.org</strong></a> is also in development — a digital skills programme for schools. We're looking for our first partners to help run the programme at schools and generate valuable feedback. If you know a teacher or school that might be interested, please get in touch at <a href="mailto:sean@litterweek.org"><strong>sean@litterweek.org</strong></a>.</p>

        <p class="closing"><strong>Thank you for mapping litter with us!</strong></p>

        <p>— Sean</p>
    </div>
</template>

<style scoped>
.update-content h2 {
    font-size: 1.75rem;
    color: #1a202c;
    margin-bottom: 1rem;
}

.update-content h3 {
    font-size: 1.35rem;
    color: #1a1a2e;
    margin: 2rem 0 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #2ecc71;
}

.update-content p {
    margin-bottom: 1rem;
    line-height: 1.6;
    color: #333;
}

.update-content .meta {
    font-size: 0.875rem;
    color: #888;
}

.update-content .features {
    list-style: none;
    padding: 0;
    margin: 1rem 0;
}

.update-content .features li {
    padding: 0.875rem 0;
    border-bottom: 1px solid #eee;
    line-height: 1.6;
}

.update-content .features li:last-child {
    border-bottom: none;
}

.update-content .app-links {
    text-align: center;
    font-size: 1.05rem;
    margin: 1.5rem 0;
}

.update-content a {
    color: #2ecc71;
}

.update-content .closing {
    text-align: center;
    font-size: 1.1rem;
    margin: 2rem 0 1rem;
    padding: 1.25rem;
    background: #f9fafb;
    border-radius: 8px;
}
</style>
