import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';

import Integrations from './Integrations';
import HostedPanel from './HostedPanel';

const TAB_PARAM = 'td_tab';

/**
 * Event the rest of the page uses to ask for a tab.
 *
 * The empty Live Chat and Portal tabs point at the connect card on Overview,
 * and they are server-rendered PHP with no way into React state. An event is
 * the seam: admin.js dispatches, this component decides.
 */
const SELECT_TAB_EVENT = 'thrivedesk:select-tab';

const TABS = [
	{ name: 'overview', title: __( 'Overview', 'thrivedesk' ), panel: 'td-panel-overview' },
	{ name: 'integrations', title: __( 'Integrations', 'thrivedesk' ) },
	{ name: 'livechat', title: __( 'Live Chat', 'thrivedesk' ), panel: 'td-panel-livechat' },
	{ name: 'portal', title: __( 'Portal', 'thrivedesk' ), panel: 'td-panel-portal' },
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

export default function App( { integrations, connected } ) {
	/*
	 * TabPanel picks up initialTabName on mount and never again, so a request
	 * from outside is honoured by remounting it against a new key. The nonce is
	 * what makes a repeat request work: asking twice for the same tab has to
	 * change the key, or the second ask is a no-op.
	 *
	 * Remounting is cheap and safe here - HostedPanel keeps a reference to every
	 * panel it has adopted precisely so it can put them back.
	 */
	const [ target, setTarget ] = useState( { name: initialTab(), nonce: 0 } );

	useEffect( () => {
		const select = ( event ) => {
			const name = event.detail;

			if ( TABS.some( ( tab ) => tab.name === name ) ) {
				setTarget( ( prev ) => ( { name, nonce: prev.nonce + 1 } ) );
			}
		};

		document.addEventListener( SELECT_TAB_EVENT, select );

		return () => document.removeEventListener( SELECT_TAB_EVENT, select );
	}, [] );

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
			key={ target.nonce }
			className="td-tabs"
			tabs={ TABS }
			initialTabName={ target.name }
			onSelect={ onSelect }
		>
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
						<Integrations integrations={ integrations } connected={ connected } />
					</div>

					{ TABS.filter( ( tab ) => tab.panel ).map( ( tab ) => (
						<HostedPanel key={ tab.name } sourceId={ tab.panel } hidden={ active.name !== tab.name } />
					) ) }
				</>
			) }
		</TabPanel>
	);
}
