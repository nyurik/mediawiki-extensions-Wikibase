<template>
	<div class="wb-db-thankyou">
		<h2 class="wb-db-thankyou__head">
			{{ $messages.get( $messages.KEYS.THANK_YOU_HEAD ) }}
		</h2>

		<p class="wb-db-thankyou__body">
			{{ $messages.get( $messages.KEYS.THANK_YOU_EDIT_REFERENCE_ON_REPO_BODY ) }}
		</p>

		<div class="wb-db-thankyou__button">
			<EventEmittingButton
				type="primaryProgressive"
				size="M"
				:message="$messages.get( $messages.KEYS.THANK_YOU_EDIT_REFERENCE_ON_REPO_BUTTON )"
				:href="repoLink"
				:new-tab="true"
				:prevent-default="false"
				@click="click"
			/>
		</div>
	</div>
</template>

<script lang="ts">
import Component from 'vue-class-component';
import { Prop, Vue } from 'vue-property-decorator';
import EventEmittingButton from '@/presentation/components/EventEmittingButton.vue';

/**
 * A component to thank the user for their edit and present them with
 * the option to continue editing (e.g. references) on the repository.
 */
@Component( {
	components: { EventEmittingButton },
} )
export default class ThankYou extends Vue {
	/**
	 * The link to continue editing on the repository if desired
	 */
	@Prop( { required: true } )
	private readonly repoLink!: string;

	private click(): void {
		/**
		 * An event fired when the user clicks the CTA to edit references on the repository
		 * @type {Event}
		 */
		this.$emit( 'opened-reference-edit-on-repo' );
	}
}
</script>

<style lang="scss">
.wb-db-thankyou {
	display: flex;
	align-items: center;
	justify-content: center;
	flex-direction: column;
	padding: $padding-panel-form;

	&__head {
		@include h3();
		@include marginForCenterColumnHeading();
	}

	&__body {
		@include body-responsive();
		@include marginForCenterColumn( 3 * $base-spacing-unit );
	}

	&__button {
		@include marginForCenterColumn();
	}
}
</style>
