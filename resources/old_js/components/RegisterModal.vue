<template>
<div class="modal is-active">
	<div class="modal-background"></div>
	<div class="modal-content">
		<div class="box">
			<slot></slot>
		</div>
	</div>
	<button class="modal-close" @click="$emit('close')" @keyup.esc="close"></button>
	</div>
</template>