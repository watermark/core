<?php
/**
 * @author Robin Appelman <icewind@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_External\Tests\Service;


use OCA\Files_External\Service\DBConfigService;
use OCP\IDBConnection;
use Test\TestCase;

class DBConfigServiceTest extends TestCase {
	/**
	 * @var DBConfigService
	 */
	private $dbConfig;

	/**
	 * @var IDBConnection
	 */
	private $connection;

	private $mounts = [];

	public function setUp() {
		parent::setUp();
		$this->connection = \OC::$server->getDatabaseConnection();
		$this->dbConfig = new DBConfigService($this->connection);
	}

	public function tearDown() {
		foreach ($this->mounts as $mount) {
			$this->dbConfig->removeMount($mount);
		}
		$this->mounts = [];
	}

	private function addMount($mountPoint, $storageBackend, $authBackend, $priority, $type) {
		$id = $this->dbConfig->addMount($mountPoint, $storageBackend, $authBackend, $priority, $type);
		$this->mounts[] = $id;
		return $id;
	}

	public function testAddSimpleMount() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals('/test', $mount['mount_point']);
		$this->assertEquals('foo', $mount['storage_backend']);
		$this->assertEquals('bar', $mount['auth_backend']);
		$this->assertEquals(100, $mount['priority']);
		$this->assertEquals(DBConfigService::MOUNT_TYPE_ADMIN, $mount['type']);
		$this->assertEquals([], $mount['applicable']);
		$this->assertEquals([], $mount['config']);
		$this->assertEquals([], $mount['options']);
	}

	public function testAddApplicable() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals([
			['type' => DBConfigService::APPLICABLE_TYPE_USER, 'value' => 'test', 'mount_id' => $id]
		], $mount['applicable']);

		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_GROUP, 'bar');
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_GLOBAL, null);

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals([
			['type' => DBConfigService::APPLICABLE_TYPE_USER, 'value' => 'test', 'mount_id' => $id],
			['type' => DBConfigService::APPLICABLE_TYPE_GROUP, 'value' => 'bar', 'mount_id' => $id],
			['type' => DBConfigService::APPLICABLE_TYPE_GLOBAL, 'value' => null, 'mount_id' => $id]
		], $mount['applicable']);
	}

	public function testAddApplicableDouble() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals([
			['type' => DBConfigService::APPLICABLE_TYPE_USER, 'value' => 'test', 'mount_id' => $id]
		], $mount['applicable']);
	}

	public function testDeleteMount() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);

		$this->dbConfig->removeMount($id);

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(null, $mount);
	}

	public function testRemoveApplicable() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');
		$this->dbConfig->removeApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, 'test');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals([], $mount['applicable']);
	}

	public function testSetConfig() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->setConfig($id, 'foo', 'bar');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'bar'], $mount['config']);

		$this->dbConfig->setConfig($id, 'foo2', 'bar2');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'bar', 'foo2' => 'bar2'], $mount['config']);
	}

	public function testSetConfigOverwrite() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->setConfig($id, 'foo', 'bar');
		$this->dbConfig->setConfig($id, 'foo', 'qwerty');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'qwerty'], $mount['config']);
	}

	public function testSetOption() {
		$id = $this->addMount('/test', 'foo', 'bar', 100, DBConfigService::MOUNT_TYPE_ADMIN);
		$this->dbConfig->setOption($id, 'foo', 'bar');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'bar'], $mount['options']);

		$this->dbConfig->setOption($id, 'foo2', 'bar2');

		$mount = $this->dbConfig->getMountById($id);
		$this->assertEquals(['foo' => 'bar', 'foo2' => 'bar2'], $mount['options']);
	}
}