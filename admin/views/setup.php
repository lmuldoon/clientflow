<?php
/**
 * Setup wizard mount point.
 *
 * React takes over from here. The WP admin chrome is hidden by the
 * SetupWizard component via body class manipulation on mount.
 *
 * @package ClientFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="cf-setup-root" class="cf-setup-page"></div>
