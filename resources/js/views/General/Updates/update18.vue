<template>
    <div class="update-content">
        <h2>Update #18 - We need your help!</h2>

        <p>Next week, we are going to launch a GoFundMe to try and bring OpenLitterMap to the next stage.</p>

        <ul>
            <li>
                As part of the campaign, we have made our first promotional video that I hope you will enjoy! A short
                trailer of the video has been released on Facebook, Twitter and Reddit. If you would like to help, this
                would be a really good time to interact with us on social media. We will only be able to increase our
                visibility with your help. Please considering joining the crowdfunding and sharing the full campaign
                video when it launches next week.
            </li>
            <li>In other news...</li>
            <li>
                We broke a record for WorldCleanupDay. One of our users uploaded more than 7,500 photos to
                OpenLitterMap. The user grouped the photos by litter type and uploaded them in batches. This way, we
                were able to programatically apply 1,000s of tags in seconds!
            </li>
            <li>
                OpenLitterMap.com v2.0 is nearly ready. This is a huge improvement on code that I wrote 3-4 years ago
                and it will make adding new features much easier. We have a lot more features to add.
            </li>
            <li>
                Next week, 4th of October is exactly 10 years since I first contacted a professor expressing my interest
                in mapping litter while studying Geography in University. I will reply to the email with the campaign
                when its online :-)
            </li>
            <li>
                Check out this fantastic podcast of Joe Rogan interviewing Frank von Hippel, an ecotoxicologist about
                chemical pollution.
            </li>
        </ul>

        <div class="milestone">
            <strong>10 Years of Dedication!</strong> GoFundMe campaign launching next week, record-breaking 7,500 photos
            uploaded on WorldCleanupDay, and v2.0 nearly ready with major code improvements.
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
