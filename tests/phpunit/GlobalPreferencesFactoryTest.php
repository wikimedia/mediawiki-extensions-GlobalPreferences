<?php

namespace GlobalPreferences\Test;

use GlobalPreferences\GlobalPreferencesFactory;
use GlobalPreferences\Storage;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\HTMLForm\Field\HTMLCheckMatrix;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group GlobalPreferences
 */
class GlobalPreferencesFactoryTest extends MediaWikiIntegrationTestCase {

	/** @var mixed[][] */
	private $formDescriptor = [
		'onbydefault' => [
			'type' => 'radio',
			'options' => [ 'Vector' => 'vector', 'Monobook' => 'monobook' ],
			'section' => 'rendering/skin',
			'default' => 'vector',
		],
		'onbydefault-global' => [
			'type' => 'toggle',
			'label-raw' => '...',
			'default' => true,
			'section' => 'rendering/skin',
		],
		'offbydefault' => [
			'type' => 'radio',
			'options' => [ 'Vector' => 'vector', 'Monobook' => 'monobook' ],
			'section' => 'rendering/skin',
			'default' => 'vector',
		],
		'offbydefault-global' => [
			'type' => 'toggle',
			'label-raw' => '...',
			'default' => false,
			'section' => 'rendering/skin',
		],
		'almost-echo-matrix' => [
			'class' => HTMLCheckMatrix::class,
			'section' => 'echo/echosubscriptions',
			'rows' => [
				'Talk page message' => 'edit-user-talk',
				'Thanks' => 'edit-thank',
				'Mention' => 'mention',
			],
			'columns' => [
				'Web' => 'web',
				'Email' => 'email',
			],
			'prefix' => 'almost-echo-matrix-',
			'force-options-off' => [
				'email-emailuser',
			],
			'force-options-on' => [
				'web-edit-user-talk',
			],
			'tooltips' => [
				'Talk page message' => 'Notify me when blah blah...',
				'Thanks' => '...',
				'Mention' => '...',
			],
			'default' => [
				'web-edit-user-talk',
				'web-edit-thank',
				'web-mention',
			],
		],
		'almost-echo-matrix-global' => [
			'type' => 'toggle',
			'label-raw' => '...',
			'default' => false,
			'section' => 'echo/echosubscriptions',
		],
	];

	/**
	 * @covers       \GlobalPreferences\GlobalPreferencesFactory::saveFormData()
	 *
	 * @dataProvider provideFormSaving
	 *
	 * @param array $formData
	 * @param array|bool $expected
	 * @param string[] $expectedMatrixRemovals
	 */
	public function testFormSaving( array $formData, $expected, array $expectedMatrixRemovals = [] ) {
		$storage = $this->getMockBuilder( Storage::class )
			->disableOriginalConstructor()
			->getMock();
		if ( $expected === false ) {
			$storage->expects( self::never() )->method( 'save' );
		} else {
			$storage->expects( self::once() )
				->method( 'save' )
				->with( $expected, array_keys( $this->formDescriptor ), $expectedMatrixRemovals );
		}

		$user = $this->getMockBuilder( User::class )
			->getMock();
		$this->overrideUserPermissions( $user, [ 'editmyoptions' ] );

		$factory = $this->getMockBuilder( GlobalPreferencesFactory::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getUserID', 'makeStorage', 'getFormDescriptor' ] )
			->getMock();
		$factory->method( 'getUserID' )
			->willReturn( 1 );
		$factory->method( 'makeStorage' )
			->willReturn( $storage );
		$factory->method( 'getFormDescriptor' )->willReturn( $this->formDescriptor );
		$wrapper = TestingAccessWrapper::newFromObject( $factory );
		$wrapper->options = new ServiceOptions( [ 'HiddenPrefs' ], [ 'HiddenPrefs' => [] ] );
		$wrapper->permissionManager = $this->getServiceContainer()->getPermissionManager();

		$postData = [ 'wpFormIdentifier' => 'testFormSaving' ];
		foreach ( $formData as $name => $value ) {
			$postData["wp{$name}"] = $value;
		}
		$request = new FauxRequest( $postData, true );
		/** @var GlobalPreferencesFactory $factory */
		/** @var User $user */
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( $request );
		$context->setUser( $user );
		$context->setTitle( SpecialPage::getTitleFor( 'GlobalPreferences' ) );

		$form = $factory->getForm( $user, $context );
		$form->prepareForm();

		$status = $form->trySubmit();
		self::assertInstanceOf( Status::class, $status );
		self::assertEquals( $expected !== false, $status->isGood() );
	}

	public static function provideFormSaving() {
		return [
			0 => [
				[],
				[],
				// If checkmatrix isn't in the form, it must be purged
				[ 'almost-echo-matrix' ]
			],
			1 => [
				// Add nothing
				[ 'onbydefault-global' => false ],
				[],
				// If you don't have the check matrix, it must be removed
				[ 'almost-echo-matrix' ]
			],
			2 => [
				// Validation failures should properly propagate
				[
					'onbydefault-global' => true,
					'onbydefault' => 'unknown value'
				],
				// The form is not 'good'
				false,
				// If checkmatrix isn't in the form, it must be purged
				[ 'almost-echo-matrix' ]
			],
			3 => [
				// Changing string options
				[
					'onbydefault-global' => true,
					'onbydefault' => 'monobook'
				],
				[
					'onbydefault' => 'monobook',
				],
				// If checkmatrix isn't in the form, it must be purged
				[ 'almost-echo-matrix' ]
			],
			4 => [
				// What if there's something strange in the checkbox?
				[ 'almost-echo-matrix' => [] ],
				[],
				[ 'almost-echo-matrix' ]
			],
			5 => [
				// Any checkmatrix that is being saved without the equivalent
				// -global value, is removed
				[
					'almost-echo-matrix' => [
						'email-edit-user-talk',
						'web-mention'
					]
				],
				[],
				[ 'almost-echo-matrix' ]
			],
			6 => [
				// Checkmatrix that has -global equivalent value will be
				// saved
				[
					'almost-echo-matrix-global' => true,
					'almost-echo-matrix' => [
						'email-edit-user-talk',
						'web-mention'
					]
				],
				[
					'almost-echo-matrix' => true,
					// forced-on; never can be false
					'almost-echo-matrix-web-edit-user-talk' => true,
					'almost-echo-matrix-web-edit-thank' => false,
					// From given values
					'almost-echo-matrix-web-mention' => true,
					// From given values
					'almost-echo-matrix-email-edit-user-talk' => true,
					'almost-echo-matrix-email-edit-thank' => false,
					'almost-echo-matrix-email-mention' => false,
				]
			],
			7 => [
				[ 'almost-echo-matrix-global' => true ],
				[
					'almost-echo-matrix' => true,
					// forced-on; never can be false
					'almost-echo-matrix-web-edit-user-talk' => true,
					'almost-echo-matrix-web-edit-thank' => false,
					'almost-echo-matrix-web-mention' => false,
					'almost-echo-matrix-email-edit-user-talk' => false,
					'almost-echo-matrix-email-edit-thank' => false,
					'almost-echo-matrix-email-mention' => false,
				],
			],
			8 => [
				[
					'almost-echo-matrix-global' => true,
					'onbydefault-global' => true
				],
				[
					'onbydefault' => 'vector',
					'almost-echo-matrix' => true,
					// forced-on; never can be false
					'almost-echo-matrix-web-edit-user-talk' => true,
					'almost-echo-matrix-web-edit-thank' => false,
					'almost-echo-matrix-web-mention' => false,
					'almost-echo-matrix-email-edit-user-talk' => false,
					'almost-echo-matrix-email-edit-thank' => false,
					'almost-echo-matrix-email-mention' => false,
				],
			],
			10 => [
				[
					'almost-echo-matrix-global' => true,
					// Setting explicit global values
					'almost-echo-matrix' => [
						'email-edit-thank'
					]
				],
				[
					'almost-echo-matrix' => true,
					// forced-on; never can be false
					'almost-echo-matrix-web-edit-user-talk' => true,
					'almost-echo-matrix-web-edit-thank' => false,
					'almost-echo-matrix-web-mention' => false,
					'almost-echo-matrix-email-edit-user-talk' => false,
					// Setting true explicitly above:
					'almost-echo-matrix-email-edit-thank' => true,
					'almost-echo-matrix-email-mention' => false,
				],
			],
		];
	}
}
