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
                'smoking',
                'food',
                'coffee',
                'softdrinks',
                'alcohol',
                'other',
                'coastal',
                'sanitary',
                'dumping',
                'industrial',
                'brands'
            ],
            datasets: [
                {
                    label: 'Total Categories',
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
            }
        })
    }
}
</script>
