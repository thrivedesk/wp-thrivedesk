<?php

namespace ThriveDesk\Services;

if (!defined('ABSPATH')) {
	exit;
}

class PortalService {

	public array $plans = [
		'business_ltd_22',
		'agency_plus_ltd',
		'agency-ltd1',
		'founder-ltd-business',
		'pro',
		'pro_annual'
	];

	public function current_plan(  ) {
		$apiService = new TDApiService();

		return $apiService->getRequest( THRIVEDESK_API_URL . '/v1/billing/plans/current' );
	}

	public function is_allowed_portal_feature( ): bool {
		$plan = $this->current_plan();
		if ( in_array($plan['overview'] && $plan['overview']['slug'], $this->plans ) ) {
			return true;
		}
		return false;
	}
}