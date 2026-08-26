/**
 * The ThriveDesk settings screen.
 *
 * Mounts over a server-rendered container. Everything the app needs on first
 * paint is bootstrapped into window.thrivedeskAdmin by Admin::admin_scripts(),
 * so the tabs render without a round trip.
 */
import { createRoot } from '@wordpress/element';

import App from './admin-app/App';

import './admin-app/style.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const mount = document.getElementById( 'td-admin-app' );

	if ( ! mount ) {
		return;
	}

	const config = window.thrivedeskAdmin || {};

	createRoot( mount ).render( <App integrations={ config.integrations || [] } /> );
} );
