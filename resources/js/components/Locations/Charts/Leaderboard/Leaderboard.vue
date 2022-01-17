w<template>
	<div class="columns">
		<div class="column is-three-quarters is-offset-1">
			<table class="table is-fullwidth" style="background-color: transparent;">
				<tr>
					<th>Position</th>
					<th>Name</th>
					<th>XP</th>
				</tr>
					<tr v-for="leader in this.leaders" class="wow slideInLeft">
						<td>{{ leader.position }}</td>
						<td v-if="leader.name || leader.username">{{ leader.name }} {{ leader.username }}</td>
                        <td v-else>{{ $t('common.anonymous') }}</td>
						<td>{{ leader.xp }}</td>
					</tr>
			</table>
		</div>
	</div>
</template>

<script>
export default {
	name: 'Leaderboard',
	props: ['leaderboard'],
	mounted ()
	{
		let arr = this.leaderboard;

		let positions = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];

        for (var i = 0; i < arr.length; i++)
        {
            arr[i]['position'] = positions[i];
        }

		this.leaders = arr;
	},
	data ()
	{
		return {
			leaders: [],
		};
	},
}
</script>
