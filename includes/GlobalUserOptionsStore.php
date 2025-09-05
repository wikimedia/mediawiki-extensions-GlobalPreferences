<?php

namespace GlobalPreferences;

use GlobalPreferences\Services\GlobalPreferencesConnectionProvider;
use GlobalPreferences\Services\GlobalPreferencesHookRunner;
use MediaWiki\HookContainer\HookContainer;
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

	private LoggerInterface $logger;
	private readonly GlobalPreferencesHookRunner $globalPreferencesHookRunner;

	public function __construct(
		private readonly CentralIdLookup $centralIdLookup,
		private readonly GlobalPreferencesConnectionProvider $globalDbProvider,
		HookContainer $hookContainer,
	) {
		$this->logger = LoggerFactory::getInstance( 'preferences' );
		$this->globalPreferencesHookRunner = new GlobalPreferencesHookRunner( $hookContainer );
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
	 * @param array $keys
	 * @param array $userNames
	 * @return array
	 */
	public function fetchBatchForUserNames( array $keys, array $userNames ) {
		$idsByName = $this->centralIdLookup->lookupOwnedUserNames(
			array_fill_keys( $userNames, 0 ) );
		$idsByName = array_filter( $idsByName );
		if ( !$idsByName ) {
			return [];
		}
		$res = $this->globalDbProvider->getReplicaDatabase()
			->newSelectQueryBuilder()
			->select( [ 'gp_user', 'gp_property', 'gp_value' ] )
			->from( 'global_preferences' )
			->where( [
				'gp_user' => array_values( $idsByName ),
				'gp_property' => $keys,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$namesById = array_flip( $idsByName );
		$options = [];
		foreach ( $res as $row ) {
			$name = $namesById[$row->gp_user];
			$options[$row->gp_property][$name] = (string)$row->gp_value;
		}
		return $options;
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
		$oldPreferences = $storage->load();
		$newPreferences = $oldPreferences;

		$replacements = [];
		$deletions = [];
		foreach ( $updates as $key => $value ) {
			if ( $value === null ) {
				$deletions[] = $key;
				unset( $newPreferences[$key] );
			} else {
				$replacements[$key] = $value;
				$newPreferences[$key] = $value;
			}
		}

		$storage->replaceAndDelete( $replacements, $deletions );

		// Run the set preferences hook here because global preferences may be set by the local options API
		// and we still want hook handlers to know about this.
		$this->globalPreferencesHookRunner->onGlobalPreferencesSetGlobalPreferences(
			$user, $oldPreferences, $newPreferences
		);

		return $replacements || $deletions;
	}

	private function getStorage( UserIdentity $user ): ?Storage {
		// Avoid CentralIdLookup::isOwned() since it has a slow worst case
		$userName = $user->getName();
		$id = $this->centralIdLookup->lookupOwnedUserNames( [ $userName => 0 ] )[ $userName ];
		if ( !$id ) {
			return null;
		}
		return new Storage( $id );
	}
}
