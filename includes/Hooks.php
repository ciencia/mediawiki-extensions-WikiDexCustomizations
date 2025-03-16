<?php

namespace MediaWiki\Extension\WikiDexCustomizations;

use ApiMessage;
use MediaWiki\MediaWikiServices;
use MWFileProps;
use UploadBase;
use User;

/**
 * Class that implements the hooks called from MediaWiki
 *
 * @license MIT
 */
class Hooks {

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LinkerMakeExternalLink
	 *
	 * @param string $url: The URL of the external link
	 * @param string $text: The link text that would normally be displayed on the page
	 * @param string $link: The link HTML if you choose to override the default.
	 * @param string $attribs: Link attributes (added in MediaWiki 1.15, r48223)
	 * @param string $linktype: Type of external link, e.g. 'free', 'text', 'autonumber'. Gets added to the css classes.
	 */
	public static function onLinkerMakeExternalLink( &$url, &$text, &$link, &$attribs, $linktype ) {
		// Display self-host reference links as not-external
		$elHandler = ExternalLinkHandler::getInstance();
		$elHandler->handleExternalLink( $url, $attribs );
		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/HtmlPageLinkRendererBegin
	 *
	 * @param LinkRenderer $linkRenderer: the LinkRenderer object
	 * @param LinkTarget $target: the LinkTarget that the link is pointing to
	 * @param string|HtmlArmor|null &$text: the contents that the <a> tag should have; either a plain, unescaped string or a HtmlArmor object; null means "default".
	 * @param string[] &$customAttribs: the HTML attributes that the <a> tag should have, in associative array form, with keys and values unescaped.
	 *                 Should be merged with default values, with a value of false meaning to suppress the attribute.
	 * @param string[] &$query: the query string to add to the generated URL (the bit after the "?"), in associative array form, with keys and values unescaped.
	 * @param string &$ret: the value to return if your hook returns false.
	 */
	public static function onHtmlPageLinkRendererBegin( $linkRenderer, $target, &$text, &$customAttribs, &$query, &$ret ) {
		$ule = UserLinkEnricher::getInstance();
		$ule->addUserGroupIdentifications( $target, $customAttribs );
		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @param OutputPage $out - The OutputPage object.
	 * @param Skin $skin - Skin object that will be used to generate the page, added in 1.13.
	 */
	public static function onBeforePageDisplay( &$out, &$skin ) {
		$lmo = LinkAndMetaOutputAdditions::getInstance();
		$lmo->addOutputLinkAndMeta( $out, $skin );
		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetConfigVars
	 *
	 * @param array $vars - Array of variables to be added into the output of the startup module.
	 * @param Skin $skin - (introduced in 1.32) Current skin name to restrict config variables to a certain skin (if needed)
	 * @param Config $config - (introduced in 1.34)
	 */
	public static function onResourceLoaderGetConfigVars( &$vars, $skin, $config ) {
		$extConfig = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikiDexCustomizations' );
		if ( $extConfig->get( 'WDPopulateDiscordInviteUrlJSVar' ) ) {
			$vars['wgDiscordInviteUrl'] = wfMessage( 'discord-url' )->inContentLanguage()->escaped();
		}
		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageCheckLastModified
	 *
	 * @param array $modifiedTimes: array of timestamps, the following keys are set:
	 * @param OutputPage $out: OutputPage object
	 */
	public static function onOutputPageCheckLastModified( &$modifiedTimes, $out ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( $config->get( 'UseCdn' ) ) {
			// T46570: the core page itself may not change, but resources might
			// Force an updated date every SquidMaxage period since last page update
			$timestamp = wfTimestamp( TS_UNIX, $modifiedTimes['page'] );
			$period = $config->get( 'CdnMaxAge' );
			$ages = floor( ( time() - $timestamp ) / $period );
			$modifiedTimes['sepoch'] = wfTimestamp( TS_MW, $timestamp + $period * $ages );
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetCacheVaryCookies
	 *
	 * @param OutputPage $out: OutputPage object
	 * @param array $cookies: array of cookies name, add a value to it if you want to add a cookie that have to vary cache options
	 */
	public static function onGetCacheVaryCookies( $out, &$cookies ) {
		// Prevent MobileFrontend from sending cache-control: private on pages with this cookie.
		// In Varnish we already send Vary: Cookie
		$cookies = array_filter( $cookies, function( $val ) {
			return ( $val != "mf_useformat" && $val != "stopMobileRedirect" );
		} );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinSubPageSubtitle
	 *
	 * @param string &$subpages: Put the subpage links HTML in this variable
	 * @param Skin $skin: Skin object (since 1.17.0)
	 * @param OutputPage $out: OutputPage object (since 1.21)
	 */
	public static function onSkinSubPageSubtitle( &$subpages, $skin, $out ) {
		// Remove automatic subpage links on NS_MAIN without changing wgNamespacesWithSubpages
		// Some pages like guides use {{SUBPAGENAME}} and similar, wich won't work if wgNamespacesWithSubpages disables it
		$title = $out->getTitle();
		if ( $title->getNamespace() == NS_MAIN ) {
			return false;
		}
		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UploadVerifyUpload
	 *
	 * @param UploadBase $upload: An instance of UploadBase, with all info about the upload
	 * @param User $user: An instance of User, the user uploading this file
	 * @param array|null $props: File properties, as returned by MWFileProps::getPropsFromPath().
	 *                   Note this is not always guaranteed to be set, e.g. in test scenarios.
	 *                   Call MWFileProps::getPropsFromPath() yourself in case you need the information.
	 * @param string $comment: Upload log comment, also used as edit summary
	 * @param string $pageText: File description page text. Only used for new uploads.
	 * @param string|string[]|MessageSpecifier &$error: If the file upload should be prevented,
	 *                    set this output parameter to the reason in the form of array( messagename, param1, param2, … )
	 *                    or a MessageSpecifier instance. You might want to use ApiMessage to provide
	 *                    machine-readable details for the API.
	 */
	public static function onUploadVerifyUpload( UploadBase $upload, User $user, $props, $comment, $pageText, &$error ) {
		// Limitar la subida de archivos grandes como imagenes del anime (por el usuario Fran y sus multis)
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikiDexCustomizations' );
		$titleRE = $config->get( 'WDUploadEPRegExp' );
		if ( !$titleRE ) {
			// Not configured
			return;
		}
		if ( !$props ) {
			$mwProps = new MWFileProps( MediaWikiServices::getInstance()->getMimeAnalyzer() );
			$props = $mwProps->getPropsFromPath( $upload->getTempPath(), true );
		}
		$title = $upload->getTitle();
		if ( !$props || !$title ) {
			return;
		}
		if (
			$user &&
			MediaWikiServices::getInstance()->getPermissionManager()->userHasRight( $user, 'wikidex-uploadlimit-exempt' ) )
		{
			return;
		}
		$excludeTitleRE = $config->get( 'WDUploadEPRegExpExclude' );
		$maxWidth = $config->get( 'WDUploadEPMaxWidth' );
		$maxHeight = $config->get( 'WDUploadEPMaxHeight' );
		$maxSizeKB = $config->get( 'WDUploadEPMaxSizeKB' );

		if (
			preg_match( $titleRE, $title->getText() ) &&
			( !$excludeTitleRE || !preg_match( $excludeTitleRE, $title->getText() ) ) )
		{
			// Skup gif or webm
			if ( preg_match( '/\.(gif|webm)$/i', $title->getText() ) ) {
				return;
			}
			if ( ( $maxWidth > 0 && $props['width'] > $maxWidth ) || ( $maxHeight > 0 && $props['height'] > $maxHeight ) ) {
				wfDebugLog( 'wikidex-uploadlimit', sprintf( '[dimensions] triggered by %s. Actual dimensions: %s x %s. %s', $user->getName(), $props['width'], $props['height'], $title->getText() ) );
				$error = new ApiMessage( [ 'wikidex-uploadlimit-dimensions', "$maxWidth x $maxHeight px" ], 'wikidex-uploadlimit-dimensions' );
			} elseif ( $maxSizeKB > 0 && $props['size'] > $maxSizeKB * 1024 ) {
				wfDebugLog( 'wikidex-uploadlimit', sprintf( '[size] triggered by %s. Actual size: %s. %s', $user->getName(), $props['size'], $title->getText() ) );
				$error = new ApiMessage( [ 'wikidex-uploadlimit-size', "$maxSizeKB KB" ], 'wikidex-uploadlimit-size' );
			}
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/WantedPages::getQueryInfo
	 *
	 * @param WantedPagesPage $wantedPages: WantedPagesPage object
	 * @param array &$query: query array, see QueryPage::getQueryInfo() for format documentation
	 */
	public static function onWantedPagesgetQueryInfo( &$wantedPages, &$query ) {
		// Make WantedPages list only content namespaces instead of everything
		$config = MediaWikiServices::getInstance()->getMainConfig();
		// Remove condition 'pg2.page_namespace != ' . $dbr->addQuotes( NS_MEDIAWIKI ),
		unset( $query['conds'][2] );
		// Replace it with all content namespaces
		$query['conds']['pg2.page_namespace'] = $config->get( 'ContentNamespaces' );
	}

}
