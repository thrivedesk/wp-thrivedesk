import { useEffect, useRef } from '@wordpress/element';

/**
 * Nodes already adopted, keyed by the id they were adopted from.
 *
 * Without this a panel is adoptable exactly once: React removing the host div
 * takes the adopted node out of the document with it, and getElementById can
 * no longer find it. Holding the reference means a re-mount can put the same
 * node back, with its jQuery listeners and any in-progress state intact.
 */
const adopted = new Map();

/**
 * Hosts a server-rendered panel inside a React tab.
 *
 * A staging device, not a permanent one. These panels are still PHP with
 * jQuery bound to their ids, so they are moved into their tab rather than
 * re-created - re-implementing the save paths at the same time as introducing
 * the tabs would make a fault in either hard to attribute.
 *
 * React never renders children into this container, so there is nothing for
 * reconciliation to fight over. Moving a node does not detach its listeners,
 * which is what keeps the existing handlers working.
 */
export default function HostedPanel( { sourceId, hidden } ) {
	const host = useRef( null );

	useEffect( () => {
		const source = adopted.get( sourceId ) || document.getElementById( sourceId );

		if ( ! source || ! host.current ) {
			return;
		}

		adopted.set( sourceId, source );

		if ( source.parentElement !== host.current ) {
			host.current.appendChild( source );
		}

		source.hidden = false;
	}, [ sourceId ] );

	return <div ref={ host } className="td-hosted-panel" hidden={ hidden } />;
}
