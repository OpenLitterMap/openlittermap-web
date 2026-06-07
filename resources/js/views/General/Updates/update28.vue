<template>
    <div class="update-content">
        <h2>Update 28: 1st TidyTowns Webinar, 1 Million Hours &amp; EU Presidency Citizen Science Deliverable</h2>

        <p>On Monday 2nd June 2026, we hosted the first OpenLitterMap demo for TidyTowns groups. This year, Ireland holds the EU Presidency for six months. We're calling on TidyTowns groups — Ireland's leading volunteer network — to document their impact with our app and help co-create a citizen-led deliverable for that Presidency.</p>

        <p>The idea is simple. TidyTowns groups give over a million hours a year to their communities — cleaning, planting, painting, repairing, reporting, maintaining. But most of that work disappears. It lives in WhatsApp groups, adjudication PDFs, and committee records. It's seen by neighbours but never counted nationally.</p>

        <p>From July to December, Europe will be looking at Ireland. TidyTowns can help tell a better story — and OpenLitterMap can give the network something it's never had: an open, mappable record of its own impact.</p>

        <h3>🎥 1st TidyTowns Webinar</h3>

        <p>The response was overwhelming. More than <strong>130 signed up</strong> and <strong>nearly 100 attended</strong>, from dozens of groups. Many have reached out since, wanting this story told.</p>

        <p>This isn't just a competition between groups — it's a display of collective action. Every photo becomes a record, every tag becomes evidence, and the data gives a fairer way to see which groups are making the biggest difference.</p>

        <p>The reach is growing. <strong>Meath County Council</strong> became the first Irish local authority to publicly endorse OpenLitterMap, committing to share the webinar with <strong>950+ TidyTowns groups</strong>. It was also shared by the <strong>Department of Rural and Community Development and the Gaeltacht</strong>.</p>

        <h3>⏱️ 1 Million Hours</h3>

        <p>"1 Million Hours" isn't a slogan — it's a challenge. If TidyTowns volunteers already give over a million hours a year, Ireland should be able to show it. Not vaguely, not buried in PDFs — mapped.</p>

        <p>In the last 30 days:</p>

        <div class="stats">
            <div class="stat"><span class="stat-num">309</span><span class="stat-label">new people</span></div>
            <div class="stat"><span class="stat-num">4,398</span><span class="stat-label">photos uploaded</span></div>
            <div class="stat"><span class="stat-num">5,960</span><span class="stat-label">tags</span></div>
        </div>

        <p>Early, but it proves the point: the technology works and the appetite is there. The next step is organisation.</p>

        <h3>🇪🇺 EU Presidency Citizen Science Deliverable</h3>

        <p>We have less than a month before the Presidency begins. I'm looking for <strong>Founding Members</strong> to help shape what this deliverable becomes.</p>

        <p class="meeting"><strong>TidyTowns | EU Presidency Planning Meetup</strong><br />Thursday 11th June, 7–8pm (Zoom)</p>

        <p>This goes deeper than the first demo. I'll show you how to create teams, manage uploads, tag photos, download your data, and generate reports — and how the platform can map more than just litter. And I want to hear from you: what's too slow, what's confusing, and what would help you bring your members along.</p>

        <p class="app-links">Places aren't publicised. To join, email <a href="mailto:info@openlittermap.com?subject=Founding%20Circle%20%E2%80%94%20EU%20Presidency%20Delivery">info@openlittermap.com</a> to request access.</p>

        <h3>🔧 What's improved</h3>

        <p>We've fixed a batch of real upload and tagging bugs, several reported directly by users:</p>

        <ul class="features">
            <li><strong>iPhone photos now upload.</strong> Apple's HEIC format was being silently rejected — it's now accepted and converted automatically.</li>
            <li><strong>The mobile "Your Photos" screen is rebuilt.</strong> You now see all your location-tagged photos, newest first, with infinite scroll, plus a "Select More" button to pull extra photos from your gallery.</li>
            <li><strong>Failed uploads no longer get stuck in loops</strong> — including for users on older app versions.</li>
            <li><strong>Map "picked up" status is now accurate,</strong> and tag names with special characters (like "Black &amp; Mild") no longer break a save.</li>
        </ul>

        <p>Every fix ships with test coverage so it stays fixed.</p>

        <h3>📄 The Democracy Gap</h3>

        <p>I've also published <strong>The Democracy Gap</strong> — a 71-page policy dossier with a Minister's Brief and a short version. It sets out the wider argument: public money, public evidence, open data, and the democratic infrastructure Ireland needs.</p>

        <p>Read it at <a href="https://litterweek.org/tdg" target="_blank">LitterWeek.org/tdg</a>.</p>

        <p class="meta">Full changelog: <router-link to="/changelog">openlittermap.com/changelog</router-link></p>

        <h3>🐛 Help Us Improve</h3>

        <p>Found a bug or have a suggestion? Let us know:</p>
        <ul>
            <li>📩 <a href="mailto:info@openlittermap.com">info@openlittermap.com</a></li>
            <li>💻 <a href="https://github.com/OpenLitterMap/openlittermap-web" target="_blank">GitHub</a> · <a href="https://discord.gg/NAEUBwtAhT" target="_blank">Discord</a> · <a href="https://join.slack.com/t/openlittermap/shared_invite/zt-29p12603a-MlzeSdMK~0mc1tCLV_bxUA" target="_blank">Slack</a></li>
        </ul>
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

.update-content .meeting {
    text-align: center;
    font-size: 1.15rem;
    line-height: 1.5;
    margin: 1.5rem 0;
    padding: 1.25rem;
    background: #ecfdf3;
    border: 1px solid #2ecc71;
    border-radius: 8px;
    color: #1a1a2e;
}

.update-content .stats {
    display: flex;
    gap: 1rem;
    margin: 1.5rem 0 0.75rem;
}

.update-content .stat {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.25rem 0.5rem;
    background: #ecfdf3;
    border: 1px solid #2ecc71;
    border-radius: 8px;
    text-align: center;
}

.update-content .stat-num {
    font-size: 1.85rem;
    font-weight: 700;
    color: #1a1a2e;
    line-height: 1.1;
}

.update-content .stat-label {
    margin-top: 0.35rem;
    font-size: 0.9rem;
    color: #4a5568;
}

.update-content .stats-caption {
    text-align: center;
    color: #555;
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
