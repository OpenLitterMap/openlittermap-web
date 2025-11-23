<template>
    <div class="update-content">
        <h2>You are invited to attend our inaugural online conference STOP! [1] The State of Plastic Pollution.</h2>

        <ul>
            <li>
                <a href="https://www.eventbrite.ie/e/the-state-of-plastic-pollution-1-tickets-48910940941"
                    >STOP! [1] The State of Plastic Pollution</a
                >
                is our inaugural online conference where we will introduce OpenLitterMap and call on your input to guide
                our plans for the future. <em>STOP! [1]</em> will take place from the comfort of your home or wherever
                you happen to be on Saturday, September 1st via YouTube at 3pm Irish time (GMT); 8am California; 11pm
                Sydney. I hope you can find the time to join us for this <strong>1 hour</strong> event. You will be sent
                a link to the conference shortly before it begins.
            </li>
            <li>OpenLitterMap is now available in Turkish! Several other languages are currently being translated.</li>
            <li>
                Removed the "Drugs" category. Might integrate some items into sanitary/other. Drug litter mapping
                remains continuous on my other site <a href="druglitter.info">druglitter.info</a> however I have left
                the decision of openness on this type of litter up to Local Authorities. However, no one really cares
                and both DrugLitter and OpenLitterMap remain completey underdeveloped leaving the hazards posed to
                innocent children in playgrounds and society & the environment at large largely unabated.
            </li>
            <li>
                Fixed some bugs in the Brands category that I introduced accidentally after pushing some random keys at
                3 or 4am some night.
            </li>
            <li>Started learning unit & feature testing to avoid pushing broken code.</li>
            <li>
                You might have noticed the delete button is not working on Profile. I haven't fixed this yet, but I did
                change the error from "Sorry this page could not be found" to an alert box with a new message.
            </li>
            <li>Added Littercoin Issued (2,950) and mined not-yet issued (18,575) to openlittermap.com/maps</li>
            <li>
                I was rejected by more 2 grant holders incl LW and D-S., bringing the total grant rejections in a row to
                6 which has to be some kind of record.
            </li>
            <li>
                I am now sharing updates on <a href="https://reddit.com/r/openlittermap">reddit.com/r/openlittermap.</a>
                Please subscribe and upvote so more people can see.
            </li>
        </ul>

        <div class="milestone">
            <strong>STOP! Conference Announcement!</strong> Join us September 1st at 3pm Irish time for our inaugural
            online conference about the state of plastic pollution. Plus: Turkish language now available!
        </div>

        <p>
            All Data @ <a href="https://openlittermap.com">openlittermap.com</a> is Verified, Free and Open for anyone
            to download. We want anyone to be able to access our data to solve problems.
        </p>

        <h3>What can you do?</h3>

        <p>Upload and process a few images a day.</p>

        <p>Organize a Litter Mapping Marathon, any time, any-where! Maybe this Saturday, 2-4pm?</p>

        <p>Like, Share and Retweet <a href="https://twitter.com/openlittermap">@OpenLitterMap</a>!</p>

        <p>
            Please support the development of OpenLitterMap with €5+ a month, sit back and watch these developments
            unfold.
        </p>

        <h3>Finally, here are some tips for using OpenLitterMap</h3>

        <ul>
            <li>
                You can map an entire beach or street clean in 1 photo if you want, but it depends what kind of story
                you want to tell.
            </li>
            <li>
                If you can't count what's in the photo, you can use Large/Random Dump in the Other category and rank it
                from 1-100.
            </li>
            <li>
                You are not just mapping litter, you are creating open data. Feel the Knowledge!! You can download your
                data too. Anyone can!
            </li>
            <li>
                Please don't include children in the photos, but feel welcome to take a picture of your dog and join our
                community of Trash Dog owners! Feature your cute pup on the
                <a href="https://openlittermap.com/global">global map</a> under the trash dog category!
            </li>
            <li>Sit back and expect more updates!</li>
        </ul>

        <p class="signature">Happy Mapping-<br />Seán Lynch @ OpenLitterMap.com</p>
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
