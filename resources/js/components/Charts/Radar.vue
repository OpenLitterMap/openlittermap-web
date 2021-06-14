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
                    backgroundColor: '#8e7fd6',
                    data: this.categories,
                    fill: true,
                    borderColor: '#8e7fd6',
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
                    fontColor: '#8e7fd6'
                }
            },
            scale: {
                pointLabels: {
                    fontColor: 'white'
                }
            }
        })
    }
}
</script>
