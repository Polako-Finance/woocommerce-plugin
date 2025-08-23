/**
 * External dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';
import { getPolakoServerData } from './polako-utils';

const Content = () => {
	return decodeEntities(getPolakoServerData()?.description || '');
};

const Label = () => {
	return (
		<>
			<img
				src={getPolakoServerData()?.logo_url}
				alt={getPolakoServerData()?.title}
			/>
			<span style={{ marginInlineStart: '0.5rem' }}>
				{getPolakoServerData()?.title}
			</span>
		</>
	);
};

registerPaymentMethod({
	name: PAYMENT_METHOD_NAME,
	label: <Label />,
	ariaLabel: 'Polako Finance',
	canMakePayment: () => true,
	content: <Content />,
	edit: <Content />,
	supports: {
		features: getPolakoServerData()?.supports ?? [],
	},
});
