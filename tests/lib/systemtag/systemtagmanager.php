<?php

/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 *
*/

namespace OC\Test;

use \OCP\SystemTag\ISystemTagManager;
use \OCP\SystemTag\TagNotFoundException;
use \OCP\SystemTag\TagAlreadyExistsException;
use \OCP\IDBConnection;

class TestSystemTagManager extends \Test\TestCase {

	/**
	 * @var ISystemTagManager
	 **/
	private $tagManager;

	/**
	 * @var IDBConnection
	 */
	private $connection;

	public function setUp() {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->tagManager = new \OC\SystemTag\SystemTagManager($this->connection);
	} 

	public function tearDown() {
		// TODO: delete all tags
	}

	public function getAllTagsDataProvider() {
		return [
			[
				// no tags at all
			],
			[
				// simple
				['one', false, false],
				['two', false, false],
			],
			[
				// duplicate names, different flags
				['one', false, false],
				['one', true, false],
				['one', false, true],
				['one', true, true],
				['two', false, false],
				['two', false, true],
			]
		];
	}

	/**
	 * @dataProvider getAllTagsDataProvider
	 */
	public function testGetAllTags($testTags) {
		foreach ($testTags as $testTag) {
			$this->tagManager->createTag($testTag[0], $testTag[1], $testTag[2]);
		}

		$tagList = $this->tagManager->getAllTags();

		$this->assertCount(count($testTags), $tagList);

		// flatten results
		$tagList = array_map(function($tag) {
			$this->assertNotNull($tag->getId());
			return [
				$tag->getName(),
				$tag->isUserVisible(),
				$tag->isUserAssignable(),
			];
		}, $tagList);

		// check that both arrays contains the same elements regardless of order
		$this->assertSame(array_diff($tagList, $testTags), array_diff($testTags, $tagList));
	}

	public function getAllTagsFilteredDataProvider() {
		return [
			[
				[
					// no tags at all
				],
				null,
				null,
				[]
			],
			// filter by visibile only
			[
				// none visible
				[
					['one', false, false],
					['two', false, false],
				],
				true,
				null,
				[]
			],
			[
				// one visible
				[
					['one', true, false],
					['two', false, false],
				],
				true,
				null,
				[
					['one', true, false],
				]
			],
			// filter by name pattern
			[
				[
					['one', true, false],
					['one', false, false],
					['two', true, false],
				],
				false,
				'on',
				[
					['one', true, false],
					['one', false, false],
				]
			],
			// filter by name pattern and visibility
			[
				// one visible
				[
					['one', true, false],
					['two', true, false],
					['one', false, false],
				],
				true,
				'on',
				[
					['one', true, false],
				]
			],
			// filter by name pattern in the middle
			[
				// one visible
				[
					['abcdefghi', true, false],
					['two', true, false],
				],
				false,
				'def',
				[
					['abcdefghi', true, false],
				]
			]
		];
	}

	/**
	 * @dataProvider getAllTagsFilteredDataProvider
	 */
	public function testGetAllTagsFiltered($testTags, $visibleOnly, $nameSearch, $expectedResults) {
		foreach ($testTags as $testTag) {
			$this->tagManager->createTag($testTag[0], $testTag[1], $testTag[2]);
		}

		$tagList = $this->tagManager->getAllTags($visibleOnly, $nameSearch);

		$this->assertCount(count($expectedResults), $tagList);

		// flatten results
		$tagList = array_map(function($tag) {
			$this->assertNotNull($tag->getId());
			return [
				$tag->getName(),
				$tag->isUserVisible(),
				$tag->isUserAssignable(),
			];
		}, $tagList);

		// check that both arrays contains the same elements regardless of order
		$this->assertSame(array_diff($tagList, $expectedResults), array_diff($expectedResults, $tagList));
	}

	public function oneTagMultipleFlagsProvider() {
		return [
			['one', false, false],
			['one', true, false],
			['one', false, true],
			['one', true, true],
		];
	}

	/**
	 * @dataProvider oneTagMultipleFlagsProvider
	 * @expectedException TagAlreadyExistsException
	 */
	public function testCreateDuplicate($name, $userVisible, $userAssignable) {
		try {
			$this->tagManager->createTag($name, $userVisible, $userAssignable);
		} catch (\Exception $e) {
			$this->assertTrue(false, 'No exception thrown for the first create call');
		}
		$this->tagManager->createTag($name, $userVisible, $userAssignable);
	}

	/**
	 * @dataProvider oneTagMultipleFlagsProvider
	 * @expectedException TagAlreadyExistsException
	 */
	public function testGetExistingTag($name, $userVisible, $userAssignable) {
		$tag1 = $this->tagManager->createTag($name, $userVisible, $userAssignable);
		$tag2 = $this->tagManager->getTag($name, $userVisible, $userAssignable);

		$this->assertSameTag($tag1, $tag2);
	}

	public function testGetExistingTagById($name, $userVisible, $userAssignable) {
		$tag1 = $this->tagManager->createTag('one', true, false);
		$tag2 = $this->tagManager->createTag('two', false, true);

		$tagList = $this->tagManager->getTagsById([$tag1->getId(), $tag2->getId()]);

		$this->assertCount(2, $tagList);

		if ($tagList[0]->getId() !== $tag1->getId()) {
			array_reverse($tagList);
		}

		$this->assertSameTag($tag1, $tagList[0]);
		$this->assertSameTag($tag2, $tagList[1]);
	}

	/**
	 * @expectedException TagNotFoundException
	 */
	public function testGetNonExistingTag() {
		$this->tagManager->getTag('nonexist', false, false);
	}

	public function testGetNonExistingTagsById() {
		$this->assertEmpty($this->tagManager->getTagsById(['unexist1', 'unexist2']));
	}

	public function updateTagProvider() {
		return [
			[
				// update name
				['one', true, true],
				['two', true, true]
			],
			[
				// update one flag
				['one', false, true],
				['one', true, true]
			],
			[
				// update all flags
				['one', false, false],
				['one', true, true]
			],
			[
				// update all
				['one', false, false],
				['two', true, true]
			],
		];
	}

	/**
	 * @dataProvider updateTagProvider
	 */
	public function testUpdateTag($tagCreate, $tagUpdated) {
		$tag1 = $this->tagManager->createTag(
			$tagCreate[0],
			$tagCreate[1],
			$tagCreate[2]
		);
		$tag2 = $this->tagManager->updateTag(
			$tag1->getId(),
			$tagUpdated[0],
			$tagUpdated[1],
			$tagUpdated[2]
		);

		$this->assertEquals($tag2->getId(), $tag1->getId());
		$this->assertEquals($tag2->getName(), $tagUpdated[0]);
		$this->assertEquals($tag2->isUserVisible(), $tagUpdated[1]);
		$this->assertEquals($tag2->isUserAssignable(), $tagUpdated[2]);
	}

	/**
	 * @dataProvider updateTagProvider
	 * @expectedException TagAlreadyExistsException
	 */
	public function testUpdateTagDuplicate($tagCreate, $tagUpdated) {
		$this->tagManager->createTag(
			$tagCreate[0],
			$tagCreate[1],
			$tagCreate[2]
		);
		$tag2 = $this->tagManager->createTag(
			$tag2->getId(),
			$tagUpdated[0],
			$tagUpdated[1],
			$tagUpdated[2]
		);

		// update to match the first tag
		$this->tagManager->updateTag(
			$tag2->getId(),
			$tagCreate[0],
			$tagCreate[1],
			$tagCreate[2]
		);
	}

	public function testDeleteTags() {
		$tag1 = $this->tagManager->createTag('one', true, false);
		$tag2 = $this->tagManager->createTag('two', false, true);

		$this->tagManager->deleteTags([$tag1->getId(), $tag2->getId()]);

		$this->assertEmpty($this->getAllTags());
	}

	/**
	 * @expectedException TagNotFoundException
	 */
	public function testDeleteNonExistingTag() {
		$this->tagManager->deleteTags(['unexist']);
	}

	private function assertSameTag($tag1, $tag2) {
		$this->assertEquals($tag1->getId(), $tag2->getId());
		$this->assertEquals($tag1->getName(), $tag2->getName());
		$this->assertEquals($tag1->isUserVisible(), $tag2->isUserVisible());
		$this->assertEquals($tag1->isUserAssignable(), $tag2->isUserAssignable());
	}

}
