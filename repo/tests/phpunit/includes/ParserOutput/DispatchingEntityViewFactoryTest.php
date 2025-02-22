<?php

namespace Wikibase\Repo\Tests\ParserOutput;

use InvalidArgumentException;
use Language;
use LogicException;
use OutOfBoundsException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\Lib\LanguageFallbackChain;
use Wikibase\Lib\Store\EntityInfo;
use Wikibase\Repo\ParserOutput\DispatchingEntityViewFactory;
use Wikibase\View\EntityDocumentView;

/**
 * @covers \Wikibase\Repo\ParserOutput\DispatchingEntityViewFactory
 *
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class DispatchingEntityViewFactoryTest extends \PHPUnit\Framework\TestCase {

	public function testInvalidConstructorArgument() {
		$this->expectException( InvalidArgumentException::class );
		new DispatchingEntityViewFactory(
			[ 'invalid' ]
		);
	}

	public function testUnknownEntityType() {
		$factory = new DispatchingEntityViewFactory(
			[]
		);

		$this->expectException( OutOfBoundsException::class );
		$factory->newEntityView(
			Language::factory( 'en' ),
			new LanguageFallbackChain( [] ),
			$this->createMock( EntityDocument::class ),
			$this->createMock( EntityInfo::class )
		);
	}

	public function testNoEntityViewReturned() {
		$factory = new DispatchingEntityViewFactory(
			[
				'foo' => function() {
					return null;
				}
			]
		);

		$unknownEntity = $this->createMock( EntityDocument::class );
		$unknownEntity->expects( $this->once() )
			->method( 'getType' )
			->willReturn( 'foo' );

		$this->expectException( LogicException::class );
		$factory->newEntityView(
			Language::factory( 'en' ),
			new LanguageFallbackChain( [] ),
			$unknownEntity,
			$this->createMock( EntityInfo::class )
		);
	}

	public function testNewEntityView() {
		$language = Language::factory( 'en' );
		$languageFallbackChain = new LanguageFallbackChain( [] );
		$entity = $this->createMock( EntityDocument::class );
		$entity->expects( $this->once() )
			->method( 'getType' )
			->willReturn( 'foo' );
		$entityInfo = $this->createMock( EntityInfo::class );
		$entityView = $this->createMock( EntityDocumentView::class );

		$factory = new DispatchingEntityViewFactory(
			[
				'foo' => function(
					Language $languageParam,
					LanguageFallbackChain $fallbackChainParam,
					EntityDocument $entityParam,
					EntityInfo $entityInfoParam
				) use(
					$language,
					$languageFallbackChain,
					$entity,
					$entityInfo,
					$entityView
				) {
					$this->assertSame( $language, $languageParam );
					$this->assertSame( $languageFallbackChain, $fallbackChainParam );
					$this->assertSame( $entity, $entityParam );
					$this->assertSame( $entityInfo, $entityInfoParam );

					return $entityView;
				}
			]
		);

		$newEntityView = $factory->newEntityView(
			$language,
			$languageFallbackChain,
			$entity,
			$entityInfo
		);

		$this->assertSame( $entityView, $newEntityView );
	}

}
