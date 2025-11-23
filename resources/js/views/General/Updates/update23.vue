<template>
    <div class="update-content">
        <h2>Update #23 - Huge Update to Global Map</h2>

        <p>Hi everyone. Just a small update but a big one!</p>

        <ul>
            <li>
                Did you notice our global map was really slow when you zoomed in and loaded too many points? This has
                been fixed! We integrated webGL to change how the points are being rendered. Now even a mobile device
                can load huge amounts of point data. See for yourself by zooming right in on the Global Map. What does
                litter look like in your community? We need your help to find out!
            </li>
            <li>
                OpenLitterMap is growing! We are now getting about 500 images uploaded per day. On Good Friday, over 30
                people signed up and created an account to share data and in March nearly 300 people signed up!
            </li>
            <li>This also means the costs are rising. We would really appreciate it if you could help our GoFundMe?</li>
            <li>
                We also need more volunteers that can help us add boxes to images to train the OpenLitterAi. All you
                need is some time and a keyboard!
            </li>
            <li>
                Our Slack channel has never been more active! If you are ready to be a part of the OpenLitterMap
                movement, Click here to join our chat and be a part of our open-source community. We especially want to
                hear from new users. What was your first experience like? How could we do better? What would you like to
                change or see improved?
            </li>
            <li>
                We are currently in touch with 2 Universities who want to help with OLM research. This is so new and
                unexplored that currently, not even 1 university campus in the world has had their litter mapped. Who
                will be the first...?
            </li>
            <li>Several bug fixes to the iOS app made recently and more on the way.</li>
            <li>
                Seaspiracy was also released on Netflix. You should check it out, but citizen science is so new and
                unexplored it doesn't even mention it yet even though it does a good job at highlighting some of our
                environmental problems.
            </li>
        </ul>

        <div class="milestone">
            <strong>WebGL Performance Revolution!</strong> Global map now handles massive datasets smoothly on mobile,
            500 images uploaded daily, and 2 universities joining OLM research efforts.
        </div>

        <p class="signature">
            Looking forward to sharing more updates with you again soon.<br /><br />Your personal open source litter
            mapping developer,<br />Seán @ OpenLitterMap
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
