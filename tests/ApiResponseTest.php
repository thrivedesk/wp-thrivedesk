<?php
/**
 * Characterization tests for the JSON envelope produced by ApiResponse. The
 * `status` property is always true, so the body carries `data` only when the
 * payload is non-empty; errors are message-only.
 *
 * @package ThriveDesk\Tests
 */

use ThriveDesk\Api\ApiResponse;

class ApiResponseTest extends TD_Ajax_TestCase {

	public function test_success_includes_data_when_present() {
		$body = $this->capture_json(
			function () {
				( new ApiResponse() )->success( 200, [ 'a' => 1 ], 'ok' );
			}
		);

		$this->assertSame(
			[
				'message' => 'ok',
				'data'    => [ 'a' => 1 ],
			],
			$body
		);
	}

	public function test_success_omits_empty_data() {
		$body = $this->capture_json(
			function () {
				( new ApiResponse() )->success( 200, [], 'done' );
			}
		);

		$this->assertSame( [ 'message' => 'done' ], $body );
		$this->assertArrayNotHasKey( 'data', $body );
	}

	public function test_error_is_message_only() {
		$body = $this->capture_json(
			function () {
				( new ApiResponse() )->error( 401, 'Request unauthorized' );
			}
		);

		$this->assertSame( [ 'message' => 'Request unauthorized' ], $body );
	}
}
