<?php

namespace GlobalPreferences;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Options\UserOptionsStore;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * An interface which allows core to update pre-existing global preferences
 */
class GlobalUserOptionsStore implements UserOptionsStore {

	/** @var CentralIdLookup */
	private $centralIdLookup;

	/** @var LoggerInterface */
	private $logger;

	public function __construct( CentralIdLookup $centralIdLookup ) {
		$this->centralIdLookup = $centralIdLookup;
		$this->logger = LoggerFactory::getInstance( 'preferences' );
	}

	/**
	 * @param UserIdentity $user
	 * @param int $recency
	 * @return array|string[]
	 */
	public function fetch( UserIdentity $user, int $recency ) {
		$storage = $this->getStorage( $user );
		if ( !$storage ) {
			return [];
		}
		if ( DBAccessObjectUtils::hasFlags( $recency, IDBAccessObject::READ_LATEST ) ) {
			$dbType = DB_PRIMARY;
		} else {
			$dbType = DB_REPLICA;
		}
		return $storage->loadFromDB( $dbType, $recency );
	}

	/**
	 * @param UserIdentity $user
	 * @param array $updates
	 * @return bool
	 */
	public function store( UserIdentity $user, array $updates ) {
		$storage = $this->getStorage( $user );
		if ( !$storage ) {
			$this->logger->warning(
				'Unable to store preference for non-global user "{userName}"',
				[ 'userName' => $user->getName() ]
			);
			return false;
		}
		$replacements = [];
		$deletions = [];
		foreach ( $updates as $key => $value ) {
			if ( $value === null ) {
				$deletions[] = $key;
			} else {
				$replacements[$key] = $value;
			}
		}
		$storage->replaceAndDelete( $replacements, $deletions );
		return $replacements || $deletions;
	}

	private function getStorage( UserIdentity $user ): ?Storage {
		if ( !$this->centralIdLookup->isOwned( $user ) ) {
			return null;
		}
		$id = $this->centralIdLookup->centralIdFromName( $user->getName() );
		if ( !$id ) {
			return null;
		}
		return new Storage( $id );
	}
}
