<template>
    <div class="update-content">
        <h2>Update #24 - OpenLitterMap awarded $50,000 by cryptocurrency Cardano</h2>

        <p>Wonderful news!</p>

        <ul>
            <li>
                People holding the cryptocurrency Cardano voted, for the first time, to finance the development of
                environmental monitoring. Over 285 million ada tokens representing $0.5 billion dollars voted for our
                proposal to develop Littercoin on Cardano.
            </li>
            <li>
                This money is being used to employ 2 software developers who have already started to help build our app
                full-time over the next 3 months.
            </li>
            <li>
                Littercoin is the first token rewarded for doing citizen science. We want to see you rewarded with
                access to zero waste non-plastic goods for doing the work of contributing data to OpenLitterMap.
            </li>
            <li>
                Huge improvements have been made to the global map. You can now filter all global point data by
                category. LitterArt has been re-introduced. Go check it out and see for yourself! Do you know of any art
                made from litter? Tag it with our app and it will appear in the Art category on the global map for
                everyone to see!
            </li>
            <li>
                Significant mobile app update (v2.4.0) which fixes many bugs. Mobile app version 3 is in development.
            </li>
            <li>
                Big changes to our open source code including many bug fixes. Dozens of old files were removed and
                existing ones were refactored to make onboarding new developers easier.
            </li>
            <li>
                New languages added: Spanish & Polish added to openlittermap.com. Can you help us add more languages?
                This can be done by anyone and requires no coding experience. If you would like to help, please send us
                an email.
            </li>
            <li>
                We have a new partnership with University College Dublin who are interested in doing research with our
                data and community. The School of Education (psychology dept) are interested in doing a short survey
                with our participants about the impacts of gamification on your use of the app. They are looking for 10
                people to do a 45 minute interview. If you would like to help, €10 amazon gift cards are available as a
                reward. More information including a link to participate can be found in the PDF attached.
            </li>
        </ul>

        <div class="milestone">
            <strong>$50,000 Cardano Grant Awarded!</strong> After 60+ rejections, 285 million ada tokens voted to fund
            OpenLitterMap. Two full-time developers hired, global map filtering launched, and UCD research partnership
            formed!
        </div>

        <p class="signature">
            Looking forward to sharing more updates with you again soon.<br /><br />Thanks for taking an interest in
            OpenLitterMap.<br /><br />Seán @ OpenLitterMap
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
