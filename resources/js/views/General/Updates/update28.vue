<template>
    <div class="update-content">
        <h2>Update 28: 1st TidyTowns Webinar, 1 Million Hours &amp; EU Presidency Citizen Science Deliverable</h2>

        <p>
            On Tuesday 2nd June 2026, we hosted the first OpenLitterMap demo for TidyTowns groups. This year, Ireland
            holds the EU Presidency for six months. We're calling on TidyTowns groups — Ireland's leading volunteer
            network — to start documenting their impact with our app and help co-create a citizen-led deliverable for
            the Presidency.
        </p>

        <p>
            Last year, a record 949 TidyTowns groups achieved over 1 Million Hours of volunteer action — cleaning,
            planting, painting, repairing, reporting, maintaining. Most of it went undocumented. Some data exists on
            members devices and was uploaded to Facebook. But social media does not collect data on impact. Social media
            collects data about people. We are asking members to Change The Platform and upload to a citizen science
            platform like OpenLitterMap instead of social media to start mapping and quantifying our impact.
        </p>

        <p>
            An EU Presidency comes around every 13 years and only lasts 6 months. From July to December, Europe will be
            looking to Ireland for direction. The EU President gets to chair meetings, set the agenda, and lead with its
            values. Ireland's government will try to bring in social media IDs to access the internet. There is no plan
            for citizen science, digital skills training, or impact mapping. TidyTowns can help tell a better story —
            and OpenLitterMap can give humanity something better: an open, mappable record of peoples real-world impact
            as a better narrative and use of technology than social media.
        </p>

        <h3>🎥 1st TidyTowns Webinar</h3>

        <p>
            The response was overwhelming. More than <strong>130 signed up</strong> and
            <strong>nearly 100 attended</strong>, representing dozens of TidyTowns groups. Many have reached out wanting
            this story told.
        </p>

        <p>
            Collecting data on the health of our towns and environment is not just a competition between groups — it's a
            display of collective action. That being said, every data point becomes a record, every tag becomes
            evidence, and the data provides a more balanced way of determining which groups are making the biggest
            impact.
        </p>

        <p>
            <strong>Meath County Council</strong> became the first Irish local authority to publicly endorse our work,
            committing to share the webinar. An invite was also shared to all 950 TidyTowns groups via the
            <strong>Department of Rural and Community Development and the Gaeltacht</strong> who run the TidyTowns unit.
        </p>

        <p>In the last 30 days:</p>

        <div class="stats">
            <div class="stat"><span class="stat-num">309</span><span class="stat-label">new people</span></div>
            <div class="stat"><span class="stat-num">4,398</span><span class="stat-label">photos uploaded</span></div>
            <div class="stat"><span class="stat-num">5,960</span><span class="stat-label">tags</span></div>
        </div>

        <h3>🇪🇺 EU Presidency Citizen Science Deliverable</h3>

        <p>
            We have less than a month before the Presidency begins. I'm looking for <strong>Founding Members</strong> to
            help shape a citizen-led deliverable to help 30,000 TidyTowns members map their impact and tell their story.
            Our next meeting is Thursday 11th June at 7pm. Email info@openlittermap.com if you are interested in
            joining.
        </p>

        <h3>🔧 What's improved</h3>

        <p>We've fixed a bunch of real upload and tagging bugs, several reported directly by users:</p>

        <ul class="features">
            <li>
                <strong>iPhone photos now upload.</strong> Apple's HEIC format was being silently rejected — it's now
                accepted and converted automatically.
            </li>
            <li>
                <strong>We re-built the "Your Photos" screen on mobile.</strong> You now see all your location-tagged
                photos, newest first, with infinite scroll, plus a "Select More" button to pull extra photos from your
                gallery.
            </li>
            <li>
                <strong>Failed uploads no longer get stuck in loops</strong> — including for users on older app
                versions.
            </li>
            <li>
                <strong>Map "picked up" status is now fixed,</strong> and we fixed upload for custom tags with special
                characters (like "Black &amp; Mild") which was failing.
            </li>
        </ul>

        <h3>📄 The Democracy Gap</h3>

        <p>
            I've also published <strong>The Democracy Gap</strong> — a policy dossier that evaluates Ireland's
            structural barriers to research and innovation in citizen science ahead of our EU Presidency.
        </p>

        <p>Read it at <a href="https://litterweek.org/tdg" target="_blank">LitterWeek.org/tdg</a>.</p>

        <p class="meta">Full changelog: <router-link to="/changelog">openlittermap.com/changelog</router-link></p>

        <h3>🐛 Help Us Improve</h3>

        <p>Found a bug or have a suggestion? Let us know:</p>
        <ul>
            <li>📩 <a href="mailto:info@openlittermap.com">info@openlittermap.com</a></li>
            <li>
                💻 <a href="https://github.com/OpenLitterMap/openlittermap-web" target="_blank">GitHub</a> ·
                <a href="https://discord.gg/NAEUBwtAhT" target="_blank">Discord</a> ·
                <a
                    href="https://join.slack.com/t/openlittermap/shared_invite/zt-29p12603a-MlzeSdMK~0mc1tCLV_bxUA"
                    target="_blank"
                    >Slack</a
                >
            </li>
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
