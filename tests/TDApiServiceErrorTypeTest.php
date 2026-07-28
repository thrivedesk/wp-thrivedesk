<?php
/**
 * TDApiService::getRequest() must tell callers *why* a request failed.
 * Previously every failure (DNS/SSL/timeout, an actual 401 from the API, a
 * 5xx) was collapsed into the same generic 'wp_error' flag, so callers could
 * not tell a transient network problem (e.g. right after a domain migration,
 * while DNS/SSL are still settling) from a genuinely revoked/invalid token.
 * That ambiguity is what let a still-valid saved token get treated as dead.
 *
 * @package ThriveDesk\Tests
 */

class TDApiServiceErrorTypeTest extends WP_UnitTestCase {

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	public function test_connection_failure_is_reported_as_network() {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'Could not resolve host' );
			}
		);

		$service = new \ThriveDesk\Services\TDApiService();
		$service->setApiKey( 'ANY' );
		$result = $service->getRequest( 'https://api.example.test/v1/me' );

		$this->assertTrue( $result['wp_error'] );
		$this->assertSame( 'network', $result['error_type'] );
	}

	public function test_401_response_is_reported_as_auth() {
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [ 'code' => 401 ],
					'body'     => wp_json_encode( [ 'message' => 'Unauthenticated' ] ),
				];
			}
		);

		$service = new \ThriveDesk\Services\TDApiService();
		$service->setApiKey( 'BAD' );
		$result = $service->getRequest( 'https://api.example.test/v1/me' );

		$this->assertTrue( $result['wp_error'] );
		$this->assertSame( 'auth', $result['error_type'] );
	}

	public function test_an_error_body_that_is_not_a_json_object_is_survivable() {
		add_filter(
			'pre_http_request',
			static function () {
				// What a proxy in front of the API can answer with. Indexing
				// this as if it were the usual object is a fatal TypeError.
				return [
					'response' => [ 'code' => 502 ],
					'body'     => wp_json_encode( 'Bad Gateway' ),
				];
			}
		);

		$service = new \ThriveDesk\Services\TDApiService();
		$service->setApiKey( 'ANY' );
		$result = $service->getRequest( 'https://api.example.test/v1/me' );

		$this->assertTrue( $result['wp_error'] );
		$this->assertSame( 'server', $result['error_type'] );
		$this->assertStringContainsString( '502', $result['message'] );
	}

	public function test_a_200_that_is_not_a_json_object_comes_back_as_an_array() {
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( 'OK' ),
				];
			}
		);

		$service = new \ThriveDesk\Services\TDApiService();
		$service->setApiKey( 'ANY' );
		$result = $service->getRequest( 'https://api.example.test/v1/me' );

		// Every caller indexes what this hands back, so a bare string reaches
		// them as a fatal rather than as "nothing useful came back".
		$this->assertSame( [], $result );
	}

	public function test_server_error_is_not_reported_as_auth() {
		add_filter(
			'pre_http_request',
			static function () {
				return [
					'response' => [ 'code' => 500 ],
					'body'     => wp_json_encode( [ 'message' => 'Internal Server Error' ] ),
				];
			}
		);

		$service = new \ThriveDesk\Services\TDApiService();
		$service->setApiKey( 'ANY' );
		$result = $service->getRequest( 'https://api.example.test/v1/me' );

		$this->assertTrue( $result['wp_error'] );
		$this->assertSame( 'server', $result['error_type'] );
	}
}
