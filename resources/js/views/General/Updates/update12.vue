<template>
    <div class="update-content">
        <h2>STOP! Conference had to be re-scheduled.</h2>
        <ul>
            <li>
                Unfortuantely our inaugural online conference STOP wasn't able to go ahead. As a 1 man team I have
                limits.
            </li>
            <li>
                A few guest speakers had to cancel and there is way too much going on for me personally doing all of
                this by myself. I will try to arrange another one for mid-term (around Halloween) or xmas.
            </li>
            <li>Redesigned the Homepage.</li>
            <li>Launched the Litter World Cup.</li>
            <li>
                You can now sort countries Alphabetically, or by what country has created the most Open Data, or what
                country has created the most Open Data per person!
            </li>
            <li>
                Ireland and Pakistan are in the lead however the French dominated August Litter 2018 climbing to 6th
                place and continue to gain momentum. September Litter has begun in Australia, Canada, USA and Ireland.
            </li>
            <li>Fixed time-series charts on mobile.</li>
            <li>
                Added 1 new alcohol (Pint Glass), 2 new soft drinks (Pull-ring and Straw Packaging), 5 new other (Pet
                Surprise in a bag, Balloons, Traffic Cone, Life Buoy, Clothing and Automobile), 1 new sanitary (Wet
                Wipes) and 38 new Brands (Asahi, Aldi, Ballygowan, Bulmers, Burger King, Cadburys, Carlsberg, Coles,
                CircleK, Dunnes, Doritos, Dr Pepper, Duracell, Durex, Evian, Fosters, Gatorade, Guinness, Haribo, KFC,
                Lidl, Linden Village, Lucozade, Nero, Mars, Powerade, Ribena, Sainsburys, Spar, Stella, Supervalu,
                Tesco, Thins, Volvic, Waitrose, Walkers, Woolwoths & Wrigleys).
            </li>
            <li>
                I appreciate not all of these Brands are for everyone and I have plans to arrange them by location as
                well as 1000+ other things.
            </li>
            <li>Emailed all City & County Councillors in Ireland. Got a few interested replies! Progress!!</li>
            <li>
                Got new business cards and a standup banner. Keep an eye on my story on Instagram to see what I'm up to!
            </li>
            <li>
                Heading to Berlin for a 3-day Blockchain Discovery Tour to share information about Littercoin - the
                first token rewarded for the production of geographic information.
            </li>
            <li>
                LitterCat has joined forces with TrashDog to educate society about plastic pollution on the Global Map.
            </li>
            <li>You can now toggle the global data on the global map!</li>
            <li>
                Reminder that I am looking for Interns. Want to give a hand with marketing? Social Media? Graphic
                Design? Code? Reseach? Co-authoring Papers? Advancing GIScience? Becoming a local expert? Anything else?
                Get in touch! Or would you prefer to do your own thing? Open Data and Littercoin are permissionless so
                if you think you can use them for something go for it!
            </li>
            <li>
                I need more people to crowdfund OpenLitterMap with $5 once-off or once a month. So far 7 grant
                applications have been rejected and OpenLitterMap could use another developer or 6. Are there 100 or
                1000 people out there who think Open Data is important? I really hope so and not just for my sake.
            </li>
        </ul>

        <div class="milestone">
            <strong>Litter World Cup Launched!</strong> Countries can now compete, and LitterCat has joined TrashDog!
            Plus 38 new brands added to help track corporate responsibility.
        </div>

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
