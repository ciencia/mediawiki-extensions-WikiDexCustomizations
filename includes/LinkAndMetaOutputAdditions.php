<?php

namespace MediaWiki\Extension\WikiDexCustomizations;

use Action;
use MediaWiki\MediaWikiServices;

/**
 * Singleton that handles external links
 *
 * @license MIT
 */
class LinkAndMetaOutputAdditions {

	private static $instance = null;

	/**
	 * @var Configuration Config object from this extension
	 */
	private $mConfig = null;

	protected function __construct() {
	}

	/**
	 * Returns the instance of the class
	 */
	public static function getInstance() : LinkAndMetaOutputAdditions {
		if ( is_null(self::$instance) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Adds additional link and meta elements to the page
	 *
	 * @param OutputPage $out - The OutputPage object.
	 * @param Skin $skin - Skin object that will be used to generate the page, added in 1.13.
	 */
	public function addOutputLinkAndMeta( &$out, &$skin ) {
		$this->addMobileCanonicalLink( $out );
		$this->addWebManifest( $out );
		$this->addTwitterMeta( $out );
	}

	/**
	 * Add link rel=alternate for Mobile view (SEO)
	 *
	 * @param $out OutputPage object
	 */
	private function addMobileCanonicalLink( &$out ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ( $config->get( 'EnableCanonicalServerLink' ) ) {
			$canonicalUrl = $this->getCanonicalUrl( $out );

			// Construct the "mobile" canonical URL
			$mobileUrl = wfAppendQuery( $canonicalUrl, 'mobileaction=toggle_view_mobile' );
			$out->addLink( [
				'rel' => 'alternate',
				'media' => 'only screen and (max-width: 640px)',
				'href' => $mobileUrl,
				'title' => wfMessage( 'mobile-frontend-view' )
			] );
		}
	}

	/**
	 * Gets the configuration object relative to this extension
	 *
	 * @returns Configuration
	 */
	private function getExtensionConfig() {
		if ( $this->mConfig === null ) {
			$this->mConfig = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikiDexCustomizations' );
		}
		return $this->mConfig;
	}

	/**
	 * Gets the canonical URL
	 *
	 * @param $out OutputPage object
	 * @returns string canonical URL
	 */
	private function getCanonicalUrl( $out ) {
		$canonicalUrl = $out->getCanonicalUrl();
		// This logic is copied from OutputPage from MediaWiki core, just to get the canonical URL
		if ( $canonicalUrl !== false ) {
			$canonicalUrl = wfExpandUrl( $canonicalUrl, PROTO_CANONICAL );
		} else {
			if ( $out->isArticleRelated() ) {
				// This affects all requests where "setArticleRelated" is true. This is
				// typically all requests that show content (query title, curid, oldid, diff),
				// and all wikipage actions (edit, delete, purge, info, history etc.).
				// It does not apply to File pages and Special pages.
				// 'history' and 'info' actions address page metadata rather than the page
				// content itself, so they may not be canonicalized to the view page url.
				// TODO: this ought to be better encapsulated in the Action class.
				$action = Action::getActionName( $out->getContext() );
				if ( in_array( $action, [ 'history', 'info' ] ) ) {
					$query = "action={$action}";
				} else {
					$query = '';
				}
				$canonicalUrl = $out->getTitle()->getCanonicalURL( $query );
			} else {
				$reqUrl = $out->getRequest()->getRequestURL();
				$canonicalUrl = wfExpandUrl( $reqUrl, PROTO_CANONICAL );
			}
		}
		// End of copied code
		return $canonicalUrl;
	}

	/**
	 * Adds a web manifest link
	 *
	 * @param $out OutputPage object
	 */
	private function addWebManifest( &$out ) {
		$config = $this->getExtensionConfig();
		$manifest = $config->get( 'WDWebManifest' );
		if ( $manifest ) {
			$out->addLink( [
				'rel' => 'manifest',
				'href' => $manifest,
			] );
		}
	}

	/**
	 * Adds meta with Twitter ID
	 *
	 * @param $out OutputPage object
	 */
	private function addTwitterMeta( &$out ) {
		$config = $this->getExtensionConfig();
		$twitterUser = $config->get( 'WDTwitterUser' );
		if ( $twitterUser ) {
			$out->addMeta( 'twitter:site', $twitterUser );
		}
	}
}
