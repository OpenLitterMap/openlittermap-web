<template>
    <div class="content p2">
        <p class="ref-title">
            OpenLitterMap has been referenced {{ references.length }} times and counting
        </p>

        <ul>
            <li v-for="item in references.slice().reverse()">
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
            references: [
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
                    date: '2021/10/21',
                    title: 'From City to Sea: Integrated Management of Litter and Plastics and Their Effects on Waterways - A Guide for Municipalities',
                    link: 'https://openknowledge.worldbank.org/handle/10986/36523',
                    author: 'World Bank'
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
                },
                {
                    date: '2021/12/13',
                    title: 'Citizen science at public libraries: Data on librarians and users perceptions of participating in a citizen science project in Catalunya, Spain',
                    link: 'https://www.sciencedirect.com/science/article/pii/S2352340921009884',
                    author: 'Cigarini et al'
                },
                {
                    date: '2022/01/20',
                    title: 'Real-Time Litter Detection System for Moving Vehicles Using YOLO',
                    link: 'https://ieeexplore.ieee.org/document/9716512',
                    author: 'Amrutha et al'
                },
                {
                    date: '2022/02/01',
                    title: 'Deep learning-based waste detection in natural and urban environments',
                    link: 'https://www.sciencedirect.com/science/article/pii/S0956053X21006474',
                    author: 'Majchrowskaa et al'
                },
                {
                    date: '2022/02/24',
                    title: 'Image Classification Approaches for Segregation of Plastic Waste Based on Resin Identification Code',
                    link: 'https://link.springer.com/article/10.1007/s41403-022-00324-4',
                    author: 'Agarwal et al'
                },
                {
                    date: '2022/02/24',
                    title: 'Toward smarter management and recovery of municipal solid waste: A critical review on deep learning approaches',
                    link: 'https://www.sciencedirect.com/science/article/abs/pii/S0959652622005807',
                    author: 'Kunsen et al'
                },
                {
                    date: '2022/03/31',
                    title: 'Retraining of object detectors to become suitable for trash detection in the context of autonomous driving',
                    link: 'https://www.researchgate.net/profile/Ishan-Srivastava-8/publication/360688760_Object_detection_in_self_driving_cars_using_YOLOv5/links/6285697c50c4566fc2744ac0/Object-detection-in-self-driving-cars-using-YOLOv5.pdf',
                    author: 'Srivastava, I'
                },
                {
                    date: '2022/04/26',
                    title: 'Beach beauty in Bengal: Perception of scenery and its implications for coastal management in Purba Medinipur district, eastern India',
                    link: 'https://www.sciencedirect.com/science/article/abs/pii/S0308597X22000811',
                    author: 'Chatterjee et al'
                },
                {
                    date: '2022/04/26',
                    title: 'Application of blockchain technology for geospatial data protection and management',
                    link: 'http://zgt.com.ua/en/%D0%B7%D0%B0%D1%81%D1%82%D0%BE%D1%81%D1%83%D0%B2%D0%B0%D0%BD%D0%BD%D1%8F-%D1%82%D0%B5%D1%85%D0%BD%D0%BE%D0%BB%D0%BE%D0%B3%D1%96%D1%97-blockchain-%D0%B4%D0%BB%D1%8F-%D0%B7%D0%B0%D1%85%D0%B8%D1%81%D1%82/',
                    author: 'Chetverikov, B & Kilaru, V'
                },
                {
                    date: '2022/06/11',
                    title: 'Towards geospatial blockchain: A review of research on blockchain technology applied to geospatial data',
                    link: 'https://agile-giss.copernicus.org/articles/3/71/2022/agile-giss-3-71-2022.pdf',
                    author: 'Zhao et al'
                },
                {
                    date: '2022/07/01',
                    title: 'Determinants of Household Waste Disposal Practices and Implications for Practical Community Interventions: Lessons from Lilongwe',
                    link: 'https://papers.ssrn.com/sol3/papers.cfm?abstract_id=4151604',
                    author: 'Kalonde et al'
                },
                {
                    date: '2022/07/29',
                    title: 'Plastic waste mapping and monitoring using geospatial approaches',
                    link: 'https://iopscience.iop.org/article/10.1088/1755-1315/1064/1/012008',
                    author: 'Zulkifli et al'
                },
                {
                    date: '2022/07/29',
                    title: 'Smart waste segmentation deep learning based approach',
                    link: 'http://dspace.univ-tebessa.dz:8080/jspui/bitstream/123456789/4951/1/Achi%20Belgacem%20Aimen%20pfe_finale.pdf',
                    author: 'Belgacem, A'
                },
                {
                    date: '2022/10/15',
                    title: 'Role of Citizen Scientists in Environmental Plastic Litter Research—A Systematic Review',
                    link: 'https://www.proquest.com/openview/aa8927ea09166e0b8af056edc05e6b19/1?pq-origsite=gscholar&cbl=2032327',
                    author: 'Cristina et al'
                },
                {
                    date: '2022/11/22',
                    title: 'The Sharing Green Economy: Sharing What’s Possible with New Labor Economics ',
                    link: 'https://www.amazon.com/dp/B0BN6W4W15',
                    author: 'Mike Duwe'
                },
                {
                    date: '2022/11/25',
                    title: 'Waste management: A comprehensive state of the art about the rise of blockchain technology',
                    link: 'https://www.sciencedirect.com/science/article/abs/pii/S0166361522002081',
                    author: 'Baralla et al'
                },
                {
                    date: '2023/01/17',
                    title: 'Smardy: Zero-Trust FAIR Marketplace for Research Data',
                    link: 'https://ieeexplore.ieee.org/abstract/document/10020710',
                    author: 'Ion-Dorinel et al'
                },
                {
                    date: '2023/02/24',
                    title: 'Monitoring contaminants of emerging concern in aquatic systems through the lens of citizen science',
                    link: 'https://www.sciencedirect.com/science/article/pii/S0048969723011439?ref=pdf_download&fr=RR-2&rr=7c4c1b55da651c89',
                    author: 'Raman et al'
                },
                {
                    date: '2023/03/01',
                    title: 'Applications of convolutional neural networks for intelligent waste identification and recycling: A review',
                    link: 'https://www.sciencedirect.com/science/article/abs/pii/S0921344922006450',
                    author: 'Wu et al'
                },
                {
                    date: '2023/05/01',
                    title: 'Waste classification using vision transformer based on multilayer hybrid convolution neural network',
                    link: 'https://www.sciencedirect.com/science/article/abs/pii/S2212095523000779',
                    author: 'Alrayes et al'
                },
                {
                    date: '2023/05/25',
                    title: 'Optimized Custom Dataset for Efficient Detection of Underwater Trash',
                    link: 'https://arxiv.org/pdf/2305.16460.pdf',
                    author: 'Walia et al'
                },
                {
                    date: '2023/05/29',
                    title: 'A methodology for quantifying and characterizing litter from trash capture devices (TCDs) to measure impact and inform upstream solutions',
                    link: 'https://www.facetsjournal.com/doi/full/10.1139/facets-2022-0034',
                    author: 'Sherlock et al'
                },
                {
                    date: '2023/07/01',
                    title: 'Study on the real-time object detection approach for end-of-life battery-powered electronics in the waste of electrical and electronic equipment recycling process',
                    link: 'https://www.sciencedirect.com/science/article/abs/pii/S0956053X23003355',
                    author: 'Yang et al'
                },
                {
                    date: '2023/08/16',
                    title: 'Mapping Waste Piles in an Urban Environment Using Ground Surveys, Manual Digitization of Drone Imagery, and Object Based Image Classification Approach',
                    link: 'https://assets.researchsquare.com/files/rs-3244445/v1/ae8bcfa1-65c2-4ca1-ab43-8216902c2b0b.pdf?c=1692214271',
                    author: 'Kalonde et al'
                },
                {
                    date: '2023/08/16',
                    title: 'The Privacy-Value-App Relationship and the Value-Centered Privacy Assistant',
                    link: 'https://arxiv.org/pdf/2308.05700.pdf',
                    author: 'Carter et al'
                },
                {
                    date: '2023/08/18',
                    title: 'Centralized road to a decentralized circular plastics economy',
                    link: 'https://dutchblockchaincoalition.org/assets/images/default/Msc_Thesis_NinaHuijberts.pdf',
                    author: 'Huijberts, N.'
                },
                {
                    date: '2023/08/23',
                    title: 'A survey on bias in machine learning research',
                    link: 'https://arxiv.org/pdf/2308.11254.pdf',
                    author: 'Mikolajczyk-Bare & Grochowski'
                },
                {
                    date: '2023/08/31',
                    title: 'Deep learning-based object detection for smart solid waste management system',
                    link: 'https://www.peertechzpublications.org/articles/AEST-7-170.pdf',
                    author: 'Desta et al'
                },
                {
                    date: '08/09/23',
                    title: 'Outdoor Trash Detection in Natural Environment Using a Deep Learning Model',
                    link: 'https://ieeexplore.ieee.org/abstract/document/10244010',
                    author: 'Das et al'
                },
            ]
        };
    },
    methods: {
        /**
         * Return formatted date if it exists
         */
        getDate (date)
        {
            return moment(date).format('LL');
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
