<?php

namespace MediaWiki\Extension\WikiDexCustomizations;

use MediaWiki\MediaWikiServices;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserFactory;

/**
 * Singleton that enrichs generated user links
 *
 * @license MIT
 */
class UserLinkEnricher {

	private static $instance = null;

	/**
	 * @var array Simple array of user names and a string with the computed attribute value to add
	 */
	private $mUserGroupCache = [];

	/**
	 * @var string[] List of groups to populate
	 */
	private $mGroupsToPopulate = null;

	protected function __construct() {
	}

	/**
	 * Returns the instance of the class
	 */
	public static function getInstance() : UserLinkEnricher {
		if ( is_null(self::$instance) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Adds user group identifications
	 *
	 * @param LinkTarget $target: the LinkTarget that the link is pointing to
	 * @param string[] &$customAttribs: the HTML attributes that the <a> tag should have, in associative array form, with keys and values unescaped.
	 *                 Should be merged with default values, with a value of false meaning to suppress the attribute.
	 */
	public function addUserGroupIdentifications( $target, &$customAttribs ) {
		if ( is_array( $customAttribs ) && isset( $customAttribs['class'] ) &&
			strpos( " {$customAttribs['class']} ", ' mw-userlink ' ) !== FALSE &&
			strpos( $customAttribs['class'], 'mw-anonuserlink' ) === FALSE )
		{
			// User link
			if ( !($target instanceof LinkTarget) ) {
				wfWarn( __METHOD__ . ': Requires $target to be a LinkTarget object.', 3 );
				return;
			}
			$username = $target->getText();
			if ( array_key_exists( $username, $this->mUserGroupCache ) ) {
				if ( isset( $this->mUserGroupCache[$username] ) ) {
					$customAttribs['class'] .= $this->mUserGroupCache[$username];
				}
				return;
			}
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $username );
			if ( $user === null ) {
				$this->mUserGroupCache[$username] = null;
				return;
			}
			$listGroups = $this->getGroupsToPopulate();
			if ( !is_array( $listGroups ) || empty( $listGroups ) ) {
				return;
			}
			$userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
			$groups = $userGroupManager->getUserGroups( $user );
			$groups = array_intersect( $groups, $listGroups );
			if ( !empty( $groups ) ) {
				$newClasses =  ' mw-usergroup-' . implode( ' mw-usergroup-', $groups );
				$this->mUserGroupCache[$username] = $newClasses;
				$customAttribs['class'] .= $newClasses;
			}
		}
	}

	/**
	 * Gets the list of groups to populate
	 */
	private function getGroupsToPopulate() {
		if ( $this->mGroupsToPopulate === null ) {
			$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'WikiDexCustomizations' );
			$this->mGroupsToPopulate = $config->get( 'WDUserGroupsLinkIdentification' );
		}
		return $this->mGroupsToPopulate;
	}
}
