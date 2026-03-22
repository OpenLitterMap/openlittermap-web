<template>
    <div class="update-content">
        <h2>Update 26: OpenLitterMap v5 is released!</h2>

        <p>OpenLitterMap has just gone through the biggest transformation in its 17+ year history.</p>

        <p>With more than 2,300 files changed, this represents our biggest upgrade, and the biggest software improvement I have ever seen in my 10+ year career history working as a software engineer.</p>

        <p class="meta">This email (Update 26) is our 1st update to all ~10,000 subscribers since May 2022. You can now view all previous email updates here: <router-link to="/changelog">openlittermap.com/changelog</router-link></p>

        <h3>✨ What's New</h3>

        <ul class="features">
            <li>
                <strong>🏷️ New tagging system</strong> — Previously there was no relationship between tags. Now you can pick an object like "Can", then add a type like "Beer", attach a brand, set the material(s) — which are all related to each other. 15 old category tables replaced with one fast, searchable system. There is still a lot of improvements to make, but the new architecture is a huge improvement over the original system that I scrambled together during a dissertation in 2015. The new system also makes it much easier to add new tags. If a tag does not exist you can create new suggestions with Custom Tags by adding text like "brand:newbrand" or "object:newobject" which will get reviewed and integrated into the tagging in a future update.
            </li>
            <li>
                <strong>🗺️ Improved map & new clustering algorithm</strong> — When you zoom in and load the points data you will load a new Stats panel that summarises the point data. The points data no longer crashes when loading big data as we now paginate by loading 1,000 points at a time. Clustering has also been completely rewritten from scratch. Previously we had to delete all clusters and re-run the algorithm every 24 hours. The new system updates clusters automatically and it's way faster. In fact it's so good I'm planning to release it as a standalone open source package — <strong>Clusterize</strong>.
            </li>
            <li>
                <strong>🏫 <a href="https://litterweek.org" target="_blank">LitterWeek.org</a></strong> — We've launched LitterWeek as a Digital Skills Training Programme for schools. Want to learn about the data collection purpose of technology and why citizen science is better use of technology than social media? We just signed up our first 4 schools and we're looking for teachers who want to take the challenge. Can you help us by sharing LitterWeek with any teachers you know?
            </li>
            <li>
                <strong>📱 New mobile apps</strong> — The original apps are currently broken. Our new mobile app — v7 — will be available for iOS and Android in the coming days. It took more than 6 years to build 6 mobile app versions. Version 7 was built almost entirely <strong>in a single day</strong> — our entire workflow has been transformed by AI. Critical GPS bugs on Android should be fixed.
            </li>
            <li>
                <strong>✏️ Edit & Delete your data</strong> — Every user can now review all of their uploads and edit/delete whatever tags/images they want.
            </li>
            <li>
                <strong>👤 Make your profile public</strong> — New experimental system that you can use to make your profile clickable in the Leaderboards to share your impact with the world.
            </li>
            <li>
                <strong>🔒 New Public toggle for each image</strong> — If you want to upload data but keep it private, you can now use openlittermap independently and control access to each of your data points.
            </li>
            <li>
                <strong>📜 New Terms & Privacy</strong> — <router-link to="/terms">openlittermap.com/terms</router-link> and <router-link to="/privacy">openlittermap.com/privacy</router-link>
            </li>
            <li>
                <strong>🤖 Twitter Bot Updates</strong> — <a href="https://x.com/OLM_bot" target="_blank">x.com/OLM_bot</a> automatically produces daily, weekly, monthly & annual impact reports. We now include a new daily thread about all software improvements. The automated impact reporting has also been improved.
            </li>
        </ul>

        <h3>📦 All Tags Migrated</h3>

        <p>All original tags have been updated to use the new Tagging system. This took <strong>months</strong> of work. 89% of brands were included in the migration. A new time-series metrics database powers every stat on the platform.</p>

        <h3>🏫 LitterWeek</h3>

        <p><a href="https://litterweek.org" target="_blank"><strong>LitterWeek.org</strong></a> is a 5-day digital skills programme for schools. One hour a day. Any week. Students learn about data, privacy, and citizen science — then go outside, document litter in their community, and put their findings on the global map.</p>

        <p><strong>4 schools have signed up.</strong> First pilots are running this spring in Cork, Ireland.</p>

        <p><strong>We're looking for more.</strong> If you know a teacher, a TY coordinator, or a Green Schools contact — send them to <a href="https://litterweek.org" target="_blank"><strong>litterweek.org</strong></a> or <a href="mailto:sean@litterweek.org"><strong>sean@litterweek.org</strong></a>.</p>

        <h3>🇪🇺 Ireland's EU Presidency</h3>

        <p>Ireland holds the EU Council Presidency from July to December 2026. This happens roughly once every 14 years. It's a window where Ireland is expected to demonstrate leadership — and it matters for citizen science.</p>

        <p>During the Presidency Ireland will try to bring in Digital IDs for social media. The internet has problems, and children are at risk. This is a conversation worth having, but it is not clear if Ireland will do anything to support the development of citizen science as a better use of technology than social media and help people develop skills to overcome the harms of the internet with digital skills training instead of using this as an excuse to introduce orwellian surveillance programmes. Some of the architects of this policy have never built anything.</p>

        <p>When Germany held the Presidency in 2020, they put €2 million behind <strong>Plastic Pirates Go Europe</strong> — a citizen science project for schools. Plastic Pirates collected 93,718 items of litter. That's €21.34 per item.</p>

        <p>OpenLitterMap has collected 850,000+ items with €100K of my personal investment. That's <strong>€0.12 per item. 178× more cost-efficient.</strong> but has not qualified for any research or innovation pathway.</p>

        <p>Ireland has no pathway for citizen science, open source, or independent founders like me. Your contributions are the evidence that this works. The question is whether Irish institutions will recognise what one of their own citizens has built - which is more than the rest of our institutions combined.</p>

        <p>To celebrate Ireland's EU Presidency, I have prepared the following documents:</p>

        <ul>
            <li>
                <strong>📄 <a href="https://litterweek.org/tdg" target="_blank">The Democracy Gap</a></strong> — On January 1st 2026, to mark the beginning of the year that Ireland will host the EU Presidency, I personally submitted this policy dossier to OECD & UNESCO headquarters in Paris to document the structural gap between institutional practice and public participation.
            </li>
            <li>
                <strong>🔍 <a href="https://drive.google.com/file/d/1RZ3gowZrt4cNLWozY0iNMBwlZq037zh1/view?usp=sharing" target="_blank">MAPS: Mapping Audit of Public Science</a></strong> — MAPS is a Freedom of Information request I have submitted to Irish government departments to audit what they have achieved for public scientific inclusion since the launch of the iPhone.
            </li>
        </ul>

        <h3>🐛 Help Us Find Bugs</h3>

        <p>We need your help to find bugs on OpenLitterMap and suggest improvements to the platform!</p>

        <ul>
            <li><strong>📸 Upload a photo</strong> — Does it work? Where do you get stuck?</li>
            <li><strong>🏷️ Try the new tagging</strong> — Available on desktop at openlittermap.com for now - new mobile apps soon!</li>
            <li><strong>🗺️ Check the map</strong> — Zoom around. Click clusters. What new features would you like to see?</li>
            <li><strong>🖱️ Click around</strong> — What pages are slow to load? What is not working?</li>
            <li><strong>📈 Check your stats</strong> — Profile, XP. Do the numbers look right?</li>
            <li><strong>🐛 Found something?</strong> → <a href="mailto:info@openlittermap.com">info@openlittermap.com</a>, <a href="https://github.com/OpenLitterMap/openlittermap-web" target="_blank">GitHub</a>, <a href="https://discord.gg/NAEUBwtAhT" target="_blank">Discord</a>, or <a href="https://join.slack.com/t/openlittermap/shared_invite/zt-29p12603a-MlzeSdMK~0mc1tCLV_bxUA" target="_blank">Slack</a></li>
        </ul>

        <h3>🔮 Coming Soon</h3>

        <ul>
            <li><strong>📱 This week</strong> — Mobile apps back online</li>
            <li><strong>🏫 This spring</strong> — First LitterWeek pilots</li>
            <li><strong>🤖 This summer?</strong> — AI-assisted tagging</li>
        </ul>

        <div class="milestone">
            <strong>Thank you for taking an interest in OpenLitterMap!</strong><br />
            🌍 500,000+ geotagged observations · 110+ countries · 101+ citations · UN Digital Public Good
        </div>

        <p class="signature">
            — Seán
        </p>
    </div>
</template>

<script setup>
// Update metadata is defined in Changelog.vue
</script>

<style scoped>
.update-content h2 {
    color: #2d3748;
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0 0 1.5rem 0;
}

.update-content h3 {
    color: #4a5568;
    font-size: 1.35rem;
    font-weight: 600;
    margin: 2rem 0 1rem 0;
}

.update-content p {
    margin-bottom: 1.25rem;
    font-size: 1.05rem;
    color: #2d3748;
    line-height: 1.8;
}

.update-content p.meta {
    font-size: 0.95rem;
    color: #888888;
}

.update-content a {
    color: #2ecc71;
    text-decoration: underline;
}

.update-content a:hover {
    color: #27ae60;
}

.update-content ul {
    margin: 1.5rem 0;
    padding-left: 0;
    list-style: none;
}

.update-content ul.features li {
    border-bottom: 1px solid #eeeeee;
}

.update-content ul.features li:last-child {
    border-bottom: none;
}

.update-content li {
    padding: 0.75rem 0 0.75rem 2rem;
    position: relative;
    margin-bottom: 0.5rem;
}

.update-content li::before {
    content: '→';
    position: absolute;
    left: 0;
    color: #2ecc71;
    font-weight: bold;
    font-size: 1.2rem;
}

.update-content strong {
    color: #2d3748;
    font-weight: 600;
}

.milestone {
    background: linear-gradient(135deg, #2ecc7115 0%, #27ae6015 100%);
    border-left: 4px solid #2ecc71;
    padding: 1.5rem 2rem;
    margin: 2rem 0;
    border-radius: 0 8px 8px 0;
    font-size: 1.05rem;
    line-height: 1.8;
}

.milestone strong {
    color: #27ae60;
    font-size: 1.1rem;
}

.signature {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 2px solid #e2e8f0;
    font-style: italic;
    color: #718096;
    font-size: 1.05rem;
}
</style>
