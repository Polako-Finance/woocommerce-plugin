/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

/** The data comes form the server passed on a global object */
export const getPolakoServerData = () => {
	const serverData = getSetting('polako_data', null);
	if (!serverData) {
		throw new Error('Polako initialization data is not available');
	}
	return serverData;
};
