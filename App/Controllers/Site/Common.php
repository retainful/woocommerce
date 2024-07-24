<?php

namespace RNOC\App\Controllers\Site;

use RNOC\App\Helpers\Settings;
use RNOC\App\Helpers\Webhook;

defined( 'ABSPATH' ) || exit;

class Common {

	/**
	 * Run when our plugin get deactivated
	 */
	public static function onPluginDeactivation() {
		Webhook::removeWebhook();
	}
}