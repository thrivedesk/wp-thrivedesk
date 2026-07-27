<?php
/**
 * get_knowledgebase must apply the API key it is given. The guard was inverted
 * (it set the key only when the key was empty), so a supplied key was dropped
 * and the request went out with whatever the constructor had loaded instead.
 *
 * @package ThriveDesk\Tests
 */

class KnowledgeBaseApiKeyTest extends WP_UnitTestCase {

	public function test_supplied_api_key_is_applied_to_the_request() {
		update_option( 'td_helpdesk_settings', [ 'td_helpdesk_api_key' => 'STORED' ] );

		$captured = null;
		add_filter(
			'pre_http_request',
			function ( $pre, $args ) use ( &$captured ) {
				$captured = $args['headers']['Authorization'] ?? null;

				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'data' => [] ] ),
				];
			},
			10,
			2
		);

		\ThriveDesk\KnowledgeBase\KnowledgeBase::instance()->get_knowledgebase( 'SUPPLIED' );

		$this->assertSame( 'Bearer SUPPLIED', $captured );
	}
}
