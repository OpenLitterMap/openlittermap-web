<script>
import { Pie } from 'vue-chartjs';
import { categories } from '../../extra/categories';

export default {
    extends: Pie,
    name: 'CategoriesPieChart',
    props: ['categories'],
    data () {
        return {
            colors: [
                '#9c88ff',
                '#8AAE56',
                '#B66C46',
                '#EAE741',
                '#BFE5A6',
                '#FFFFFF',
                '#BF00FE',
                '#add8e6',
                '#ccc',
                '#ff4aa1',
                '#e3a158'
            ]
        };
    },
    mounted ()
    {
        let labels = [];
        // temp fix - we need to add art and pet surprise / dogshit
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
                    backgroundColor: this.categories.map((key, index) => {
                        return this.colors[index];
                    }),
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
                position: 'right',
                labels: {
                    fontColor: '#1DD3B0'
                }
            },
            tooltips: {
                callbacks: {
                    title: (tooltipItem, data) => data.labels[tooltipItem[0].index]
                }
            }
        });
    }
}
</script>
