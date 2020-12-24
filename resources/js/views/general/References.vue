<template>
    <div class="content p2">
        <p class="ref-title">OpenLiterMap has been referenced {{ items.length }} times and counting</p>
        <ul>
            <li v-for="item in items">
                <div class="mb1">
                    <p>{{ getDate(item.date) }}</p>
                    <a :href="item.link">{{ item.title }}</a>
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
    data ()
    {
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
                    author: 'Magista, M., et al.'
                },
                {
                    date: '2020/06/13',
                    title: 'Volunteered geographic information systems: Technological design patterns',
                    link: 'https://onlinelibrary.wiley.com/doi/abs/10.1111/tgis.12544',
                    author: 'Gómez-Barrón, J.P., et al.'
                },
                {
                    date: '2019/10/09',
                    title: 'Citizen science and the United Nations Sustainable Development Goals',
                    link: 'https://www.nature.com/articles/s41893-019-0390-3',
                    author: 'Fritz, S., et al.'
                },
                {
                    date: '2019/12/04',
                    title: 'Citizen Science - International Encyclopedia of Human Geography (Second Edition, Pages 209-214',
                    link: 'https://www.sciencedirect.com/science/article/pii/B9780081022955106018',
                    author: 'Fast, V. and Haworth, B.'
                },
                {
                    date: null,
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
                    date: '2020/07/02',
                    title: 'Mapping citizen science contributions to the UN sustainable development goals',
                    link: 'https://link.springer.com/article/10.1007/s11625-020-00833-7',
                    author: 'Fraisl, D., et al.'
                },
                {
                    date: '2020/11/02',
                    title: 'Citizen science and marine conservation: a global review',
                    link: 'https://royalsocietypublishing.org/doi/full/10.1098/rstb.2019.0461',
                    author: 'Kelly, R., et al.'
                },
                {
                    date: '2020/11/04',
                    title: 'Towards fair and efficient task allocation in blockchain-based crowdsourcing',
                    link: 'https://link.springer.com/article/10.1007/s42045-020-00043-w',
                    author: 'Pang, X., et al.'
                },
                {
                    date: '2020/12/21',
                    title: 'Open-source geospatial tools and technologies for urban and environmental studies',
                    link: 'https://opengeospatialdata.springeropen.com/articles/10.1186/s40965-020-00078-2',
                    author: 'Mobasheri, A., et al.'
                }
            ]
        }
    },
    methods: {

        /**
         * Return formatted date if it exists
         */
        getDate (date)
        {
            return date ? moment(date).format('LL') : 'unknown';
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
