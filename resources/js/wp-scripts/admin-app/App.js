import { __ } from '@wordpress/i18n';
import { TabPanel } from '@wordpress/components';

import Integrations from './Integrations';
import HostedPanel from './HostedPanel';

const TAB_PARAM = 'td_tab';

const TABS = [
	{ name: 'overview', title: __( 'Overview', 'thrivedesk' ), panel: 'td-panel-overview' },
	{ name: 'integrations', title: __( 'Integrations', 'thrivedesk' ) },
	{ name: 'livechat', title: __( 'Live Chat', 'thrivedesk' ), panel: 'td-panel-livechat' },
	{ name: 'portal', title: __( 'Portal', 'thrivedesk' ), panel: 'td-panel-portal' },
	{ name: 'settings', title: __( 'Settings', 'thrivedesk' ), panel: 'td-panel-settings' },
];

/**
 * Which tab to open on load.
 *
 * Read from the URL so a tab can be linked to and survives a reload - which
 * matters here because connecting an integration round-trips through the
 * ThriveDesk app and comes back to this page.
 */
function initialTab() {
	const requested = new URLSearchParams( window.location.search ).get( TAB_PARAM );

	return TABS.some( ( tab ) => tab.name === requested ) ? requested : TABS[ 0 ].name;
}

export default function App( { integrations } ) {
	const onSelect = ( name ) => {
		// replaceState, not pushState: tab changes are not navigation, and
		// stacking them would make the browser back button walk through tabs
		// instead of leaving the page.
		const url = new URL( window.location.href );
		url.searchParams.set( TAB_PARAM, name );
		window.history.replaceState( {}, '', url );
	};

	return (
		<TabPanel className="td-tabs" tabs={ TABS } initialTabName={ initialTab() } onSelect={ onSelect }>
			{ ( active ) => (
				/*
				 * Every panel is rendered on every tab, and only `hidden`
				 * changes. Returning just the active one would unmount the
				 * others, and unmounting a HostedPanel destroys the server
				 * markup it adopted - the panel would come back empty. Keeping
				 * them mounted also preserves what the user typed in a tab they
				 * have switched away from.
				 */
				<>
					<div hidden={ active.name !== 'integrations' }>
						<Integrations integrations={ integrations } />
					</div>

					{ TABS.filter( ( tab ) => tab.panel ).map( ( tab ) => (
						<HostedPanel key={ tab.name } sourceId={ tab.panel } hidden={ active.name !== tab.name } />
					) ) }
				</>
			) }
		</TabPanel>
	);
}
