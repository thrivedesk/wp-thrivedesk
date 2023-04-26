<?php

namespace ThriveDesk\Services;

if (!defined('ABSPATH')) {
	exit;
}

class PortalService {
	private static $instance = null;

	public $plans = [
		'business_ltd_22',
		'agency_plus_ltd',
		'agency-ltd1',
		'founder-ltd-business',
		'pro',
		'pro_annual'
	];

	public function __construct(  ) {
		add_action('wp_ajax_thrivedesk_check_portal_access', [$this, 'check_portal_access']);
	}

	public static function instance(): PortalService
	{
		if (!isset(self::$instance) && !(self::$instance instanceof PortalService)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function check_portal_access(  ) {
		$apiKey = $_POST['data']['td_helpdesk_api_key'] ?? '';
		if (empty( $apiKey ) ) {
			echo json_encode( [
				'code' => 422,
				'status' => 'error',
				'data' => [
					'message' => 'API Key is required'
				]
			] );
			die();
		}

		$hasAccess = get_transient( 'thrivedesk_portal_access' );

		if ( $hasAccess ) {
			echo json_encode( [
				'code' => 200,
				'status' => 'success',
				'data' => true
			] );
			die();
		}

		$plan = $this->get_plan( $apiKey );

		if ( isset( $plan['overview']['slug'] ) && in_array( $plan['overview']['slug'], $this->plans ) ) {
			set_transient( 'thrivedesk_portal_access', true, 60 * 60 * 6 );
			echo json_encode( [
				'code' => 200,
				'status' => 'success',
				'data' => true
			] );
		} else {
			echo json_encode( [
				'code' => 422,
				'status' => 'error',
				'data' => false
			] );
		}
		die();
	}

	public function get_plan( $apiKey = '' ) {
		$apiService = new TDApiService();
		if (!empty( $apiKey )) {
			$apiService->setApiKey( $apiKey );
		}

		return $apiService->getRequest( THRIVEDESK_API_URL . '/v1/billing/plans/current' );
	}

	public function has_portal_access(  ) {
		$hasAccess = get_transient( 'thrivedesk_portal_access' );

		if ( $hasAccess ) {
			return $hasAccess;
		}

		$plan = $this->get_plan();

		if ( isset($plan['overview']['slug']) && in_array($plan['overview']['slug'], $this->plans ) ) {
			set_transient( 'thrivedesk_portal_access', true, 60 * 60 * 6 );
			return true;
		} else {
			return false;
		}
	}
}