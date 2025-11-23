<template>
    <div class="update-content">
        <h2>Update #20 - OpenLitterMap is now Open Source</h2>

        <p>Yesterday, we released the OpenLitterMap source code under the GPL-v3.0 license.</p>

        <ul>
            <li>
                Here is a link to our GitHub repository https://github.com/OpenLitterMap/openlittermap-web Anyone can
                now inspect and contribute to the OpenLitterMap code, or use it for their own projects in line with the
                terms of the GPL-v3.0 license.
            </li>
            <li>
                We are actively looking for contributors. OpenLitterMap-web is built with Laravel (PHP), Vue.js and
                Bulma.io. In 2018 I published a paper explaining the full software stack
                https://opengeospatialdata.springeropen.com/articles/10.1186/s40965-018-0050-y
            </li>
            <li>
                If you would like to help shape the future direction of OpenLitterMap, we would love to have you on our
                Slack channel!
            </li>
            <li>
                This is not just for developers! We are looking for people with ideas, experience and enthusiasm about
                citizen science, graphic design, UI/UX, science, GIS, science communication, marketing, blogging, grant
                writing, networking, local government, social media, capacity building, and a lot more.
            </li>
            <li>
                Reminder that every Thursday, 6pm Irish time, we run a community zoom call for an hour. On the next
                call, we will show participants how to set up your local development environment and how to start
                contributing to OpenLitterMap. These free coding tutorials will likely continue for a while. We might to
                find time for a second community call!
            </li>
            <li>
                Moving forward, we will split into several working groups. (Software, Community, Research and Management
                Committee). If you would like to be a part of any of them, we would love to see you on Slack! Right now
                anyone can join but in the future, I hope that everything will be voted on by our community. That's you!
            </li>
            <li>Stay tuned for lots more exiting updates as we democratise science on pollution.</li>
        </ul>

        <div class="milestone">
            <strong>We're Open Source!</strong> Code released under GPL-v3.0 on GitHub, actively seeking contributors
            for software, community, research, and management. Join us in democratising science on pollution!
        </div>

        <p class="signature">Thanks again,<br />Seán @ OpenLitterMap</p>
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

.update-content ul {
    margin: 1.5rem 0;
    padding-left: 0;
    list-style: none;
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
    color: #4299e1;
    font-weight: bold;
    font-size: 1.2rem;
}

.update-content strong {
    color: #2d3748;
    font-weight: 600;
}

.milestone {
    background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
    border-left: 4px solid #667eea;
    padding: 1.5rem 2rem;
    margin: 2rem 0;
    border-radius: 0 8px 8px 0;
}

.milestone strong {
    color: #5a67d8;
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
