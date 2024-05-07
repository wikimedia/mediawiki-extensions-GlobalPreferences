<?php

namespace GlobalPreferences\Test;

use GlobalPreferences\Storage;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ReplaceQueryBuilder;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @group GlobalPreferences
 */
class StorageTest extends MediaWikiIntegrationTestCase {
	private const USER_ID = 123;
	private const CACHE_KEY = 'test:cache:key';

	private function makeMock() {
		return $this->getMockBuilder( Storage::class )
			->setConstructorArgs( [ self::USER_ID ] );
	}

	/**
	 * @covers \GlobalPreferences\Storage::__construct()
	 * @covers \GlobalPreferences\Storage::loadFromDB()
	 */
	public function testLoadFromDB() {
		$db = $this->getMockBuilder( IDatabase::class )
			->getMock();
		$queryBuilder = $this->createMock( SelectQueryBuilder::class );
		$queryBuilder->expects( self::once() )
			->method( 'select' )
			->with( [ 'gp_property', 'gp_value' ] )
			->willReturnSelf();
		$queryBuilder->expects( self::once() )
			->method( 'from' )
			->with( Storage::TABLE_NAME )
			->willReturnSelf();
		$queryBuilder->expects( self::once() )
			->method( 'where' )
			->with( [ 'gp_user' => self::USER_ID ] )
			->willReturnSelf();
		$queryBuilder->expects( self::once() )
			->method( 'caller' )->willReturnSelf();
		$queryBuilder->expects( self::once() )
			->method( 'fetchResultSet' )
			->willReturn( new FakeResultWrapper( [
				(object)[ 'gp_property' => 'foo', 'gp_value' => 'bar' ]
			] ) );
		$db->expects( self::once() )
			->method( 'newSelectQueryBuilder' )
			->willReturn( $queryBuilder );

		$storage = $this->makeMock()
			->onlyMethods( [ 'getDatabase' ] )
			->getMock();

		$storage->expects( self::once() )
			->method( 'getDatabase' )
			->with( DB_REPLICA )
			->willReturn( $db );
		$storage = TestingAccessWrapper::newFromObject( $storage );

		/** @var Storage $storage */
		self::assertEquals( [ 'foo' => 'bar' ], $storage->loadFromDB() );
	}

	/**
	 * @covers \GlobalPreferences\Storage::save()
	 */
	public function testSave() {
		$rqb = $this->createMock( ReplaceQueryBuilder::class );
		$db = $this->getMockBuilder( IDatabase::class )
			->getMock();
		$db->expects( self::once() )
			->method( 'newReplaceQueryBuilder' )
			->willReturn( $rqb );
		$rqb->expects( self::once() )->method( 'replaceInto' )->with( Storage::TABLE_NAME )->willReturnSelf();
		$rqb->expects( self::once() )->method( 'uniqueIndexFields' )->with( [ 'gp_user', 'gp_property' ] )
			->willReturnSelf();
		$rqb->expects( self::once() )->method( 'rows' )->with( [
				[ 'gp_user' => self::USER_ID, 'gp_property' => 'add this', 'gp_value' => 'added' ],
				[ 'gp_user' => self::USER_ID, 'gp_property' => 'change this', 'gp_value' => 'changed' ],
			]
		)->willReturnSelf();
		$rqb->expects( self::once() )->method( 'caller' )->willReturnSelf();

		/* TODO:
		 $cache = $this->getMockBuilder( \WANObjectCache::class )
			->disableOriginalConstructor()
			->setMethodsExcept( [] )
			->getMock();
		$cache->expects( self::once() )
			->method( 'delete' )
			->with( self::CACHE_KEY )
			->willReturn( true );
		*/

		$storage = $this->makeMock()
			->onlyMethods( [ 'loadFromDB', 'getDatabase', 'getCacheKey', 'delete' ] )
			->getMock();
		$storage->expects( self::once() )
			->method( 'loadFromDB' )
			->with( DB_PRIMARY )
			->willReturn( [
				'keep this' => 'yes',
				'change this' => '1',
				'this will die' => '1',
				'this will die explicitly' => '1',
				'this is from a different wiki' => 'test',
				'matrix' => 'to be deleted',
				'matrix-web' => 'to be deleted',
			] );
		$storage->expects( self::once() )
			->method( 'getDatabase' )
			->with( DB_PRIMARY )
			->willReturn( $db );
		$storage->expects( self::once() )
			->method( 'getCacheKey' )
			->willReturn( self::CACHE_KEY );
		/*$storage->expects( self::once() )
			->method( 'getCache' )
			->willReturn( $cache );*/
		$storage->expects( self::once() )
			->method( 'delete' )
			->with( [ 'this will die explicitly', 'this will die', 'matrix', 'matrix-web' ] );

		/** @var Storage $storage */
		$storage->save(
			[
				'add this' => 'added',
				'keep this' => 'yes',
				'change this' => 'changed',
				'this will die explicitly' => null,
			],
			[ 'add this', 'keep this', 'change this', 'this will die', 'this will die explicitly' ],
			[ 'matrix' ]
		);
	}

}
