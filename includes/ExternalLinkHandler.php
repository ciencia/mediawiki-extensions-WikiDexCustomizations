<?php

namespace MediaWiki\Extension\WikiDexCustomizations;

use MediaWiki\MediaWikiServices;

/**
 * Singleton that handles external links
 *
 * @license MIT
 */
class ExternalLinkHandler {

	private static $instance = null;

	/**
	 * @var string[]|null List of regular expressions to test hosts against
	 */
	private $mHostsRE = null;

	protected function __construct() {
	}

	/**
	 * Returns the instance of the class
	 */
	public static function getInstance() : ExternalLinkHandler {
		if ( is_null(self::$instance) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Remove the "external" class of links pointing to the own host
	 */
	public function handleExternalLink( $url, &$attribs ) {
		$hostsRE = $this->getHostsRE();
		foreach ( $hostsRE as $pattern ) {
			if ( preg_match( '/^(https?:)?\/\/' . $pattern . '\//', $url ) ) {
				// remove the external class
				$attribs['class'] = trim( str_replace( ' external ', '', ' ' . $attribs['class'] . ' ' ), ' ' );
				break;
			}
		}
	}

	/**
	 * Returns the list of regular expressions to test hosts against
	 *
	 * @returns string[]
	 */
	private function getHostsRE() {
		if ( $this->mHostsRE === null ) {
			$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikiDexCustomizations' );
			$this->mHostsRE = $config->get( 'WDNoExternalHostnameRegExp' );
			if ( !is_array( $this->mHostsRE ) ) {
				$this->mHostsRE = [];
			}
		}
		return $this->mHostsRE;
	}
}
