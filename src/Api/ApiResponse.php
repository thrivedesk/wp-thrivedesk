<?php
/**
 * JSON response builder for the ?listener= endpoint.
 *
 * @package ThriveDesk
 */

namespace ThriveDesk\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fluent builder that terminates the request with a JSON body.
 */
class ApiResponse {
	/**
	 * Response status
	 *
	 * @var boolean
	 */
	public $status = true;

	/**
	 * Response HTTP code
	 *
	 * @var int
	 */
	public $status_code = null;

	/**
	 * Response message
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * Response data
	 *
	 * @var array|object
	 */
	public $data = array();

	/**
	 * Construct Response class.
	 *
	 * @since 0.0.1
	 * @access public
	 */
	public function __construct() {
	}

	/**
	 * Set the HTTP status code.
	 *
	 * @param int $status_code HTTP status code.
	 *
	 * @return object
	 */
	public function status_code( int $status_code ): object {
		$this->status_code = $status_code;

		return $this;
	}

	/**
	 * Set the response message.
	 *
	 * @param string $message Human-readable message.
	 *
	 * @return object
	 */
	public function message( string $message ): object {
		$this->message = $message;

		return $this;
	}

	/**
	 * Set the response payload.
	 *
	 * @param array $data Payload returned under the 'data' key.
	 *
	 * @return object
	 */
	public function data( array $data ): object {
		$this->data = $data;

		return $this;
	}

	/**
	 * Send an error response and end the request.
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $message     Human-readable message.
	 *
	 * @return void
	 */
	public function error( int $status_code, string $message = '' ) {
		$this->status_code( $status_code )->message( $message )->send();
	}

	/**
	 * Send a success response and end the request.
	 *
	 * @param int    $status_code HTTP status code.
	 * @param array  $data        Payload returned under the 'data' key.
	 * @param string $message     Human-readable message.
	 *
	 * @return void
	 */
	public function success( int $status_code = 200, array $data = array(), string $message = '' ) {
		$this->status_code( $status_code )->data( $data )->message( $message )->send();
	}

	/**
	 * Emit the JSON body. wp_send_json() ends the request.
	 *
	 * @return void
	 */
	public function send(): void {
		$response = array(
			'message' => $this->message,
		);

		if ( true === $this->status && $this->data ) {
			$response['data'] = $this->data;
		}

		wp_send_json( $response, $this->status_code );
	}
}
