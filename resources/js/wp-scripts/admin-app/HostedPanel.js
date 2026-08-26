import { useEffect, useRef } from '@wordpress/element';

/**
 * Hosts a server-rendered panel inside a React tab.
 *
 * A staging device, not a permanent one. The settings form is still PHP with
 * jQuery bound to its ids, so it is moved into the tab rather than re-created:
 * re-rendering it in React would mean re-implementing nine save paths at the
 * same time as introducing the tabs, and a mistake in either would be hard to
 * tell apart from a mistake in the other.
 *
 * React never renders children into this container, so there is nothing for
 * reconciliation to fight over. The node is moved once and left alone, which is
 * also why the jQuery handlers bound at page load keep working - moving a node
 * does not detach its listeners.
 */
export default function HostedPanel( { sourceId } ) {
	const host = useRef( null );

	useEffect( () => {
		const source = document.getElementById( sourceId );

		if ( ! source || ! host.current || source.parentElement === host.current ) {
			return;
		}

		source.hidden = false;
		host.current.appendChild( source );
	}, [ sourceId ] );

	return <div ref={ host } className="td-hosted-panel" />;
}
