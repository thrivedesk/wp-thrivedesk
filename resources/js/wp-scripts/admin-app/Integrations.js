import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Button,
	Notice,
	__experimentalText as Text,
} from '@wordpress/components';

import { postAction } from './request';

/**
 * One integration card.
 *
 * Reads top to bottom - logo, what it is, what it gives you, then the action -
 * so the description is what decides the click rather than the name alone.
 *
 * Three states, and they are not interchangeable: `external` hands off to the
 * ThriveDesk app because those partners authorize on their side; `connected`
 * offers a disconnect; anything else offers a connect, disabled when the
 * partner plugin is not active on this site.
 *
 * `locked` cuts across all three. Every one of these connections is a handshake
 * between a ThriveDesk workspace and this site, so none of them can be started
 * before there is a workspace - but the list is most of the reason to connect
 * in the first place, so it is shown and made inert rather than hidden.
 */
function IntegrationCard( { integration, locked, onError } ) {
	const [ busy, setBusy ] = useState( false );
	const { slug, name, category, description, image, installed, connected, external } = integration;

	// One sentence, on every disabled control, rather than a lone greyed-out
	// button that never says why.
	const lockedLabel = __( 'Connect ThriveDesk first', 'thrivedesk' );

	const connect = async () => {
		setBusy( true );

		try {
			// The handler answers with the URL to hand the user off to, not
			// with JSON - see Admin::ajax_connect_plugin().
			const url = ( await postAction( 'thrivedesk_connect_plugin', { plugin: slug } ) ).trim();

			if ( ! /^https?:\/\//i.test( url ) ) {
				throw new Error(
					__( 'ThriveDesk could not start the connection. Make sure this plugin is running on a live site.', 'thrivedesk' )
				);
			}

			window.location.href = url;
		} catch ( error ) {
			setBusy( false );
			onError( error.message );
		}
	};

	const disconnect = async () => {
		setBusy( true );

		try {
			await postAction( 'thrivedesk_disconnect_plugin', { plugin: slug } );
			window.location.reload();
		} catch ( error ) {
			setBusy( false );
			onError( error.message );
		}
	};

	return (
		<li
			className={ locked ? 'td-integration is-locked' : 'td-integration' }
			data-plugin={ slug }
			data-connected={ connected ? '1' : '0' }
		>
			<img className="td-integration__logo" src={ image } alt="" width="48" height="48" />

			<div className="td-integration__title">
				<span className="td-integration__name">{ name }</span>
				<span className="td-integration__category">{ category }</span>
			</div>

			{ /* Grows, so every footer in a row sits on the same line however
			     long the sentence above it runs. */ }
			<Text variant="muted" size="12px" className="td-integration__description">
				{ description }
			</Text>

			<div className="td-integration__footer">
				<span className="td-integration__state">
					{ locked && (
						<span className="td-integration__lock">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="13" height="13" fill="none" aria-hidden="true">
								<rect x="4.75" y="10.75" width="14.5" height="9.5" rx="2.25" stroke="currentColor" strokeWidth="1.5" />
								<path d="M8 10.5V7.5a4 4 0 1 1 8 0v3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
							</svg>
							{ __( 'Locked', 'thrivedesk' ) }
						</span>
					) }
					{ ! locked && ! external && connected && (
						<span className="td-integration__badge">{ __( 'Connected', 'thrivedesk' ) }</span>
					) }
					{ ! locked && ! external && ! connected && ! installed && (
						<Text variant="muted" size="12px">
							{ __( 'Not installed', 'thrivedesk' ) }
						</Text>
					) }
				</span>

				{ external && (
					<Button
						variant="secondary"
						// A disabled Button still renders an <a> if it is given an
						// href, and an <a> is clickable whatever else it says.
						href={ locked ? undefined : external }
						target="_blank"
						rel="noreferrer"
						disabled={ locked }
						label={ locked ? lockedLabel : undefined }
						showTooltip={ locked }
					>
						{ __( 'Connect', 'thrivedesk' ) }
					</Button>
				) }

				{ ! external && connected && (
					<Button
						variant="secondary"
						isDestructive
						isBusy={ busy }
						disabled={ busy || locked }
						onClick={ disconnect }
						data-test="integration-disconnect"
					>
						{ __( 'Disconnect', 'thrivedesk' ) }
					</Button>
				) }

				{ ! external && ! connected && (
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy || locked || ! installed }
						onClick={ connect }
						data-test="integration-connect"
						// Says why it is unavailable, which a disabled
						// button on its own never does. The missing account
						// comes first: installing the partner plugin will not
						// help until there is one.
						label={ ( locked && lockedLabel ) || ( ! installed && __( 'Install and activate this plugin first', 'thrivedesk' ) ) || undefined }
						showTooltip={ locked || ! installed }
					>
						{ __( 'Connect', 'thrivedesk' ) }
					</Button>
				) }
			</div>
		</li>
	);
}

export default function Integrations( { integrations, connected } ) {
	const [ error, setError ] = useState( '' );
	const locked = ! connected;

	if ( ! integrations.length ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __( 'No integrations are available.', 'thrivedesk' ) }
			</Notice>
		);
	}

	return (
		<div className="td-integrations">
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			{ locked && (
				<Notice status="info" isDismissible={ false } className="td-integrations__locked">
					{ __( 'Connect this site to ThriveDesk to link your store, CRM and posts. Everything below stays here waiting.', 'thrivedesk' ) }{ ' ' }
					<Button
						variant="link"
						onClick={ () =>
							document.dispatchEvent(
								new CustomEvent( 'thrivedesk:select-tab', { detail: 'overview' } )
							)
						}
					>
						{ __( 'Add your API key', 'thrivedesk' ) }
					</Button>
				</Notice>
			) }

			<ul className="td-integrations__grid">
				{ integrations.map( ( integration ) => (
					<IntegrationCard
						key={ integration.slug }
						integration={ integration }
						locked={ locked }
						onError={ setError }
					/>
				) ) }
			</ul>
		</div>
	);
}
