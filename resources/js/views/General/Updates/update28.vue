<template>
    <div class="update-content">
        <h2>Update 28: 1st TidyTowns Webinar, 1 Million Hours &amp; EU Presidency Citizen Science Deliverable</h2>

        <p>A huge thank you to everyone who joined our first TidyTowns webinar — and an invitation to help shape what comes next.</p>

        <h3>🎥 1st TidyTowns Webinar</h3>

        <p>The response has been overwhelmingly positive. More than <strong>130 people signed up</strong> and we had <strong>nearly 100 people from dozens of TidyTowns groups</strong> in attendance. Many people have reached out to me for more information, who want this story to be told.</p>

        <p>I believe TidyTowns groups deserve a way to collect data &amp; communicate positive impact. This is not just a competition between groups, but a display of our collective action to inspire love and positivity in society. That said, the data also provides a more balanced way to determine what groups are making the biggest impact.</p>

        <p>The webinar's reach is growing, too. <strong>Meath County Council</strong> became the first Irish local authority to publicly endorse OpenLitterMap, committing to share the webinar with <strong>950+ TidyTowns groups</strong>. It was also shared by the <strong>Department of Rural and Community Development &amp; the Gaeltacht</strong>.</p>

        <h3>⏱️ 1 Million Hours</h3>

        <p>Here's a goal worth rallying around: <strong>one million hours</strong> of community-collected environmental data. Every photo, every tag, every clean-up logged is time invested in understanding and protecting our shared environment — and a way to show the collective impact of TidyTowns groups, schools and citizen scientists across the country. We're not there yet, but the momentum is real. In the last 30 days alone:</p>

        <div class="stats">
            <div class="stat"><span class="stat-num">309</span><span class="stat-label">new people</span></div>
            <div class="stat"><span class="stat-num">4,398</span><span class="stat-label">photos uploaded</span></div>
            <div class="stat"><span class="stat-num">5,960</span><span class="stat-label">tags</span></div>
        </div>
        <p class="stats-caption">In the last 30 days — <strong>OpenLitterMap is growing!</strong></p>

        <h3>🇪🇺 EU Presidency Citizen Science Deliverable</h3>

        <p>I am looking for your help to guide the system and shape an EU Presidency Citizen Science Deliverable. That's why I'm inviting you to a Zoom meeting:</p>

        <p class="meeting"><strong>Thursday 11th June, 7–8pm</strong><br />TidyTowns | EU Presidency Planning Meetup</p>

        <p>I am looking for <strong>Founding Members</strong> who want to help shape what an EU Presidency Citizen Science Deliverable could look like. We have less than one month to prepare. The technology exists in abundance. The app works. We need your help to get involved, understand how the system works, and encourage members to participate.</p>

        <p>We will dive deeper into the OpenLitterMap platform — which can be used to map anything, not just litter. I am moving towards a more generic platform in the future, but for now OpenLitterMap is the best there is. I will show you how to create teams, manage uploads, download your data, create reports and more. I want to hear from you: what parts are difficult, how can we make this faster and easier, and what new features would help you communicate your work?</p>

        <p class="app-links">
            Places are not publicised — if you'd like to join, email me at <a href="mailto:info@openlittermap.com?subject=Founding%20Circle%20%E2%80%94%20EU%20Presidency%20Delivery">info@openlittermap.com</a> to request access. I'm looking for <strong>TidyTowns groups for a Founding Circle</strong> interested in an EU Presidency delivery, and I look forward to talking with the first members on Thursday.
        </p>

        <h3>📄 "The Democracy Gap"</h3>

        <p>Alongside the platform work, we've completed and published <strong>"The Democracy Gap"</strong> — a 71-page policy dossier, accompanied by a Minister's Brief and a short version, now available at <a href="https://litterweek.org/tdg" target="_blank">LitterWeek.org/tdg</a>. Its core asymmetry argument has been validated as analytically strong.</p>

        <p class="meta">Full changelog: <router-link to="/changelog">openlittermap.com/changelog</router-link></p>

        <h3>🐛 Help Us Improve</h3>

        <p>Found a bug or have a suggestion? Let us know:</p>
        <ul>
            <li>📩 <a href="mailto:info@openlittermap.com">info@openlittermap.com</a></li>
            <li>💻 <a href="https://github.com/OpenLitterMap/openlittermap-web" target="_blank">GitHub</a> · <a href="https://discord.gg/NAEUBwtAhT" target="_blank">Discord</a> · <a href="https://join.slack.com/t/openlittermap/shared_invite/zt-29p12603a-MlzeSdMK~0mc1tCLV_bxUA" target="_blank">Slack</a></li>
        </ul>

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
