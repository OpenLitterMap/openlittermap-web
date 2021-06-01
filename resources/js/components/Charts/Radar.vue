<script>
import { Radar } from 'vue-chartjs'

export default {
    extends: Radar,
    name: 'Radar',
    props: ['categories'],
    mounted ()
    {
        this.renderChart({
            labels: [
                this.$t('profile_dashboard.middle.radar-cat-smoking'),
                this.$t('profile_dashboard.middle.radar-cat-food'),
                this.$t('profile_dashboard.middle.radar-cat-coffee'),
                this.$t('profile_dashboard.middle.radar-cat-softdrinks'),
                this.$t('profile_dashboard.middle.radar-cat-alcohol'),
                this.$t('profile_dashboard.middle.radar-cat-other'),
                this.$t('profile_dashboard.middle.radar-cat-coastal'),
                this.$t('profile_dashboard.middle.radar-cat-sanitary'),
                this.$t('profile_dashboard.middle.radar-cat-dumping'),
                this.$t('profile_dashboard.middle.radar-cat-industrial'),
                this.$t('profile_dashboard.middle.radar-cat-brands')
            ],
            datasets: [
                {
                    label: this.$t('profile_dashboard.middle.radar-total-categories'), //'Total Categories',
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
