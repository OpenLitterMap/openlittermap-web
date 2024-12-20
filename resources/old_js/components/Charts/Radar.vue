<script>
import { Radar } from 'vue-chartjs'
import { categories} from '../../extra/categories';

export default {
    extends: Radar,
    name: 'Radar',
    props: ['categories'],
    mounted ()
    {
        let labels = [];
        //temp fix - we need to add art and dogshit
        categories.filter((category) => (category !== 'art' && category !== 'dogshit')).map((category) => {
            labels.push(
                this.$t('litter.categories.' + category)
            )
        });

        this.renderChart({
            labels,
            datasets: [
                {
                    label: this.$t('profile.dashboard.total-categories'),
                    backgroundColor: '#1DD3B0',
                    data: this.categories,
                    fill: true,
                    borderColor: '#1DD3B0',
                    maxBarThickness: '10'
                }
            ]
        },
        {
            // options
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                labels: {
                    fontColor: '#1DD3B0'
                }
            },
            scale: {
                pointLabels: {
                    fontColor: 'white'
                }
            },
            tooltips: {
                callbacks: {
                    title: (tooltipItem, data) => data.labels[tooltipItem[0].index]
                }
            }
        })
    }
}
</script>
