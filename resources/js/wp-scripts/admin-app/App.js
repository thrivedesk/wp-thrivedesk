import { __ } from '@wordpress/i18n';
import { TabPanel } from '@wordpress/components';

import Integrations from './Integrations';
import HostedPanel from './HostedPanel';

const TAB_PARAM = 'td_tab';

/**
 * Which tab to open on load.
 *
 * Read from the URL so a tab can be linked to and survives a reload - which
 * matters here because connecting an integration round-trips through the
 * ThriveDesk app and comes back to this page.
 */
function initialTab( tabs ) {
	const requested = new URLSearchParams( window.location.search ).get( TAB_PARAM );

	return tabs.some( ( tab ) => tab.name === requested ) ? requested : tabs[ 0 ].name;
}

export default function App( { integrations } ) {
	const tabs = [
		{ name: 'integrations', title: __( 'Integrations', 'thrivedesk' ) },
		{ name: 'settings', title: __( 'Settings', 'thrivedesk' ) },
	];

	const onSelect = ( name ) => {
		// replaceState, not pushState: tab changes are not navigation, and
		// stacking them would make the browser back button walk through tabs
		// instead of leaving the page.
		const url = new URL( window.location.href );
		url.searchParams.set( TAB_PARAM, name );
		window.history.replaceState( {}, '', url );
	};

	return (
		<TabPanel
			className="td-tabs"
			tabs={ tabs }
			initialTabName={ initialTab( tabs ) }
			onSelect={ onSelect }
		>
			{ ( tab ) =>
				tab.name === 'integrations' ? (
					<Integrations integrations={ integrations } />
				) : (
					<HostedPanel sourceId="td-settings-panel" />
				)
			}
		</TabPanel>
	);
}
