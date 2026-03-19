<template>
    <div class="update-content">
        <h2>Update #21 - Say Hello to Teams & Open Source Mobile App</h2>

        <p>We are back with another exciting update!</p>

        <p>
            Today, we released the source code for our Mobile App (iOS and Android) under the GPL-v3.0 license. A small
            gift from us to the planet.
        </p>

        <ul>
            <li>
                Here is a link to the mobile app GitHub repository where the code lives:
                https://github.com/OpenLitterMap/react-native Anyone can now view, inspect and contribute to the
                OpenLitterMap Web + Mobile app code, or use our code for their own open source projects.
            </li>
            <li>
                We also released a new feature called OpenLitterMap Teams. You can now create or join a team and log
                your impact as a group!
            </li>
            <li>
                You can join any number of teams, but you can only have 1 active team. When you upload, you make a
                contribution to your active team. Keep the Global Map open to see a live update of what teams are
                contributing!
            </li>
            <li>
                The Teams feature was used by our first corporate sponsor! Engine Lease Finance is an aircraft engine
                leasing company Headquartered in Ireland who gave their global workforce a half-day to pick up litter
                and record their positive environmental impact with their families. Huge win!
            </li>
            <li>
                We made a presentation a high level Geneva Forum citizen science conference at the United Nations You
                can watch it here.
            </li>
            <li>
                Since we released the source code for our web-app last update, we have had several people fix bugs and
                add new features. These include:
                <ul style="margin-top: 0.5rem">
                    <li>Smooth zooming on the global map</li>
                    <li>Click on global map cluster to zoom in</li>
                    <li>Progress on the tool that will help us develop the OpenLitterAI</li>
                    <li>Missing items in the download</li>
                    <li>Fixed missing email verification notification for new users</li>
                    <li>and more coding stuff!</li>
                </ul>
            </li>
            <li>
                We have started using a new feature on github called Discussions. We would love if you could sign up and
                chat with us about the future of OpenLitterMap.
            </li>
            <li>
                As a reminder, we have an active Slack channel where we discuss the future of OpenLitterMap. We would
                love if you could join us? Click here to join the chat.
            </li>
            <li>
                Stay tuned for lots more exiting updates as we empower more people with the tools to share data on
                pollution.
            </li>
            <li>P.S. Our GoFundMe is stuck at 1%. We would really appreciate it if you could share it?</li>
        </ul>

        <div class="milestone">
            <strong>Mobile App Goes Open Source & Teams Launch!</strong> React Native app released under GPL-v3.0, first
            corporate sponsor (Engine Lease Finance), and presentation at UN Geneva Forum citizen science conference!
        </div>

        <p class="signature">
            Looking forward to sharing more updates with you again soon.<br /><br />Seán @ OpenLitterMap
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
