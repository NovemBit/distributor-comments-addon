<?php
/**
 *
 * Include necessary files for comments
 *
 * @package distributor-comments
 */

	/**
	 * Require files
	 */
	require_once __DIR__ . '/includes/hub.php';
	require_once __DIR__ . '/includes/rest-api.php';
	require_once __DIR__ . '/includes/utils.php';

	\DT\NbAddon\Comments\Hub\setup();
	\DT\NbAddon\Comments\Api\setup();
