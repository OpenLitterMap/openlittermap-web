<template>
    <div class="content p2">
        <p class="ref-title">OpenLitterMap has been referenced {{ items.length }} times and counting</p>
        <ul>
            <li v-for="item in items.slice().reverse()">
                <div class="mb1">
                    <p>{{ getDate(item.date) }}</p>

                    <a @click="open(item.link)">{{ item.title }}</a>

                    <p>{{ item.author }}</p>
                </div>
            </li>
        </ul>
    </div>
</template>

<script>
import moment from 'moment'

export default {
    name: 'References',
    created () {
        window.scrollTo(0, 0);
    },
    data () {
        return {
            items: [
                {
                    date: '2018/06/25',
                    title: 'OpenLitterMap.com – Open Data on Plastic Pollution with Blockchain Rewards (Littercoin)',
                    link: 'https://opengeospatialdata.springeropen.com/articles/10.1186/s40965-018-0050-y',
                    author: 'Lynch, S.'
                },
                {
                    date: '2018/12/21',
                    title: 'A Review of the Applicability of Gamification and Game-based Learning to Improve Household-level Waste Management Practices among Schoolchildren',
                    link: 'https://ijtech.eng.ui.ac.id/article/view/2644',
                    author: 'Magista et al.'
                },
                {
                    date: '2019/01/05',
                    title: 'Needs, drivers, participants and engagement actions: a framework for motivating contributions to volunteered geographic information systems',
                    link: 'https://link.springer.com/article/10.1007/s10109-018-00289-5',
                    author: 'Gómez-Barrón, et al.'
                },
                {
                    date: '2019/10/08',
                    title: 'CITIZEN SCIENCE AND DATA INTEGRATION FOR UNDERSTANDING MARINE LITTER',
                    link: 'http://pure.iiasa.ac.at/id/eprint/16095/1/22_Camera_ready_paper.pdf',
                    author: 'Campbell et al.'
                },
                {
                    date: '2019/10/09',
                    title: 'Citizen science and the United Nations Sustainable Development Goals',
                    link: 'https://www.nature.com/articles/s41893-019-0390-3',
                    author: 'Fritz et al.'
                },
                {
                    date: '2019/12/04',
                    title: 'Citizen Science - International Encyclopedia of Human Geography (Second Edition, Pages 209-214)',
                    link: 'https://www.sciencedirect.com/science/article/pii/B9780081022955106018',
                    author: 'Fast, V. and Haworth, B.'
                },
                {
                    date: '2021/01/05',
                    title: 'Workflows and Spatial Analysis in the Age of GeoBlockchain: A Land Ownership Example',
                    link: 'https://cartogis.org/docs/autocarto/2020/docs/abstracts/3e%20Workflows%20and%20Spatial%20Analysis%20in%20the%20Age%20of%20GeoBlockchain%20A%20Land.pdf',
                    author: 'Papantonioua, C. and Hilton, B.'
                },
                {
                    date: '2020/06/09',
                    title: 'Open data and its peers: understanding promising harbingers from Nordic Europe',
                    link: 'https://www.emerald.com/insight/content/doi/10.1108/AJIM-12-2019-0364/full/html',
                    author: 'Kessen, M.'
                },
                {
                    date: '2020/06/13',
                    title: 'Volunteered geographic information systems: Technological design patterns',
                    link: 'https://onlinelibrary.wiley.com/doi/abs/10.1111/tgis.12544',
                    author: 'Gómez-Barrón, et al.'
                },
                {
                    date: '2020/07/02',
                    title: 'Mapping citizen science contributions to the UN sustainable development goals',
                    link: 'https://link.springer.com/article/10.1007/s11625-020-00833-7',
                    author: 'Fraisl et al.'
                },
                {
                    date: '2020/08/27',
                    title: 'Official Survey Data and Virtual Worlds—Designing an Integrative and Economical Open Source Production Pipeline for xR-Applications in Small and Medium-Sized Enterprises',
                    link: 'file:///Users/sean/Documents/BDCC-04-00026-v2.pdf',
                    author: 'Höhl, W.'
                },
                {
                    date: '2020/11/02',
                    title: 'Citizen science and marine conservation: a global review',
                    link: 'https://royalsocietypublishing.org/doi/full/10.1098/rstb.2019.0461',
                    author: 'Kelly et al.'
                },
                {
                    date: '2020/11/04',
                    title: 'Towards fair and efficient task allocation in blockchain-based crowdsourcing',
                    link: 'https://link.springer.com/article/10.1007/s42045-020-00043-w',
                    author: 'Pang et al.'
                },
                {
                    date: '2020/12/21',
                    title: 'Open-source geospatial tools and technologies for urban and environmental studies',
                    link: 'https://opengeospatialdata.springeropen.com/articles/10.1186/s40965-020-00078-2',
                    author: 'Mobasheri et al.'
                },
                {
                    date: '2021/02/01',
                    title: 'Analysis of plastic water pollution data',
                    link: 'https://dspace.lib.uom.gr/bitstream/2159/25376/1/BesiouEleutheriaMsc2021.pdf',
                    author: 'ΜΠΕΣΙΟΥ, E.'
                },
                {
                    date: '2021/01/13',
                    title: 'Enabling a large-scale assessment of litter along Saudi Arabian red sea shores by combining drones and machine learning',
                    link: 'https://www.sciencedirect.com/science/article/abs/pii/S0269749121003109',
                    author: 'Martin et al.'
                },
                {
                    date: '2021/03/04',
                    title: 'Autonomous, Onboard Vision-Based Trash and Litter Detection in Low Altitude Aerial Images Collected by an Unmanned Aerial Vehicle',
                    link: 'https://www.researchgate.net/profile/Mateusz-Piechocki-2/publication/349869848_Autonomous_Onboard_Vision-Based_Trash_and_Litter_Detection_in_Low_Altitude_Aerial_Images_Collected_by_an_Unmanned_Aerial_Vehicle/links/60450db2a6fdcc9c781dc33b/Autonomous-Onboard-Vision-Based-Trash-and-Litter-Detection-in-Low-Altitude-Aerial-Images-Collected-by-an-Unmanned-Aerial-Vehicle.pdf',
                    author: 'Kraft et al.'
                },
                {
                    date: '2021/04/18',
                    title: 'Environmental fate and impacts of microplastics in aquatic ecosystems: a review',
                    link: 'https://pubs.rsc.org/en/content/articlehtml/2021/ra/d1ra00880c',
                    author: 'Du et al.'
                },
                {
                    date: '2021/05/06',
                    title: 'Blockchain technologies to address smart city and society challenges',
                    link: 'https://www.sciencedirect.com/science/article/abs/pii/S0747563221001771',
                    author: 'Mora et al.'
                },
                {
                    date: '2021/05/17',
                    title: 'Waste detection in Pomerania: Non-profit project for detecting waste in environment',
                    link: 'https://arxiv.org/pdf/2105.06808.pdf',
                    author: 'Majchrowska et al.'
                },
                {
                    date: '2021/05/17',
                    title: 'This city is not a bin: Crowdmapping the distribution of urban litter',
                    link: 'https://github.com/andrea-ballatore/litter-dynamics/blob/885de9c61d0b669d007ad871c8494851ce43da9a/publications/ballatore_et_al-2021-city_not_a_bin_crowdmapping.pdf',
                    author: 'Ballatore et al.'
                },
                {
                    date: '2021/08/23',
                    title: 'Using citizen science data to monitor the Sustainable Development Goals: a bottom-up analysis',
                    link: 'https://link.springer.com/article/10.1007/s11625-021-01001-1',
                    author: 'Ballerini & Bergh'
                },
                {
                    date: '2021/09/30',
                    title: 'Is Downloading this App Consistent with my Values?',
                    link: 'https://arxiv.org/pdf/2106.12458.pdf',
                    author: 'Carter, S.'
                },
                {
                    date: '2021/10/27',
                    title: 'A Systematic Literature Review of Blockchain Technology for Smart Villages',
                    link: 'https://link.springer.com/article/10.1007/s11831-021-09659-7',
                    author: 'Kaur & Parashar'
                },
                {
                    date: '2021/11/02',
                    title: 'Environmental Governance. In: Handbook of Environmental Sociology. Handbooks of Sociology and Social Research.',
                    link: 'https://link.springer.com/chapter/10.1007/978-3-030-77712-8_16',
                    author: 'Fisher et al.'
                },
                {
                    date: '2021/11/05',
                    title: 'Recycling Waste Classification Using Vision Transformer on Portable Device',
                    link: 'https://www.mdpi.com/2071-1050/13/21/11572',
                    author: 'Huang et al.'
                },
                {
                    date: '2021/11/23',
                    title: 'Litter origins, accumulation rates, and hierarchical composition on urban roadsides of the Inland Empire, California',
                    link: 'https://iopscience.iop.org/article/10.1088/1748-9326/ac3c6a',
                    author: 'W. Cowger et al.'
                }
            ]
        };
    },
    methods: {
        /**
         * Return formatted date if it exists
         */
        getDate (date)
        {
            return date ? moment(date).format('LL') : 'unknown';
        },

        /**
         * Open link in a new tab
         */
        open (link)
        {
            window.open(link, "_blank");
        }
    }
}
</script>

<style scoped>

    .ref-title {
        font-size: 2em;
        font-weight: 600;
    }

</style>
