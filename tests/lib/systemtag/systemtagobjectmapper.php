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
use \OCP\SystemTag\ISystemTagObjectMapper;
use \OCP\SystemTag\TagNotFoundException;
use \OCP\SystemTag\TagAlreadyExistsException;
use \OCP\IDBConnection;

class TestSystemTagObjectMapper extends \Test\TestCase {

	/**
	 * @var ISystemTagManager
	 **/
	private $tagManager;

	/**
	 * @var ISystemTagObjectMapper
	 **/
	private $tagMapper;

	/**
	 * @var IDBConnection
	 */
	private $connection;

	public function setUp() {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();

		$this->tagManager = $this->getMockBuilder('OCP\SystemTag\ISystemTagManager')
			->getMock();

		$this->tagMapper = new \OC\SystemTag\SystemTagObjectMapper($this->connection, $this->tagManager);

		// TODO: create with dummy object type and make sure it does not appear anywhere
	} 

	public function tearDown() {
		// TODO: delete all mappings
	}

	public function testGetTagsForObjects() {
		// TODO
	}

	public function testGetObjectsForTags() {
		// TODO
		// TODO: make sure to test with different object types
	}

	public function testAssignTags() {
		// TODO
	}

	/**
	 * @expectedException TagNotFoundException
	 */
	public function testAssignNonExistingTags() {
		// TODO
	}

	public function testUnassignTags() {
		// TODO
	}

	/**
	 * @expectedException TagNotFoundException
	 */
	public function testUnassignNonExistingTags() {
		// TODO
	}

	public function testHaveTagAllMatches() {
		// TODO
	}

	public function testHaveTagAtLeastOneMatch() {
		// TODO
	}

	/**
	 * @expectedException TagNotFoundException
	 */
	public function testHaveTagNonExisting() {
		// TODO
	}

}
