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
 */
function IntegrationCard( { integration, onError } ) {
	const [ busy, setBusy ] = useState( false );
	const { slug, name, category, description, image, installed, connected, external } = integration;

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
		<li className="td-integration">
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
					{ ! external && connected && (
						<span className="td-integration__badge">{ __( 'Connected', 'thrivedesk' ) }</span>
					) }
					{ ! external && ! connected && ! installed && (
						<Text variant="muted" size="12px">
							{ __( 'Not installed', 'thrivedesk' ) }
						</Text>
					) }
				</span>

				{ external && (
					<Button variant="secondary" href={ external } target="_blank" rel="noreferrer">
						{ __( 'Connect', 'thrivedesk' ) }
					</Button>
				) }

				{ ! external && connected && (
					<Button variant="secondary" isDestructive isBusy={ busy } disabled={ busy } onClick={ disconnect }>
						{ __( 'Disconnect', 'thrivedesk' ) }
					</Button>
				) }

				{ ! external && ! connected && (
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy || ! installed }
						onClick={ connect }
						// Says why it is unavailable, which a disabled
						// button on its own never does.
						label={ ! installed ? __( 'Install and activate this plugin first', 'thrivedesk' ) : undefined }
						showTooltip={ ! installed }
					>
						{ __( 'Connect', 'thrivedesk' ) }
					</Button>
				) }
			</div>
		</li>
	);
}

export default function Integrations( { integrations } ) {
	const [ error, setError ] = useState( '' );

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

			<ul className="td-integrations__grid">
				{ integrations.map( ( integration ) => (
					<IntegrationCard key={ integration.slug } integration={ integration } onError={ setError } />
				) ) }
			</ul>
		</div>
	);
}
