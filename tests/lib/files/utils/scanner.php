<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Utils;

use OC\Files\Filesystem;
use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Temporary;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;

class TestScanner extends \OC\Files\Utils\Scanner {
	/**
	 * @var \OC\Files\Mount\MountPoint[] $mounts
	 */
	private $mounts = array();

	/**
	 * @param \OC\Files\Mount\MountPoint $mount
	 */
	public function addMount($mount) {
		$this->mounts[] = $mount;
	}

	protected function getMounts($dir) {
		return $this->mounts;
	}

	public function getPropagator() {
		return $this->propagator;
	}

	public function setPropagator($propagator) {
		$this->propagator = $propagator;
	}
}

/**
 * Class Scanner
 *
 * @group DB
 *
 * @package Test\Files\Utils
 */
class Scanner extends \Test\TestCase {
	/**
	 * @var \Test\Util\User\Dummy
	 */
	private $userBackend;

	protected function setUp() {
		parent::setUp();

		$this->userBackend = new \Test\Util\User\Dummy();
		\OC::$server->getUserManager()->registerBackend($this->userBackend);
		$this->loginAsUser();
	}

	protected function tearDown() {
		$this->logout();
		\OC::$server->getUserManager()->removeBackend($this->userBackend);
		parent::tearDown();
	}

	public function testReuseExistingRoot() {
		$storage = new Temporary(array());
		$mount = new MountPoint($storage, '');
		Filesystem::getMountManager()->addMount($mount);
		$cache = $storage->getCache();

		$storage->mkdir('folder');
		$storage->file_put_contents('foo.txt', 'qwerty');
		$storage->file_put_contents('folder/bar.txt', 'qwerty');

		$scanner = new TestScanner('', \OC::$server->getDatabaseConnection());
		$scanner->addMount($mount);

		$scanner->scan('');
		$this->assertTrue($cache->inCache('folder/bar.txt'));
		$oldRoot = $cache->get('');

		$scanner->scan('');
		$newRoot = $cache->get('');
		$this->assertEquals($oldRoot, $newRoot);
	}

	public function testReuseExistingFile() {
		$storage = new Temporary(array());
		$mount = new MountPoint($storage, '');
		Filesystem::getMountManager()->addMount($mount);
		$cache = $storage->getCache();

		$storage->mkdir('folder');
		$storage->file_put_contents('foo.txt', 'qwerty');
		$storage->file_put_contents('folder/bar.txt', 'qwerty');

		$scanner = new TestScanner('', \OC::$server->getDatabaseConnection());
		$scanner->addMount($mount);

		$scanner->scan('');
		$this->assertTrue($cache->inCache('folder/bar.txt'));
		$old = $cache->get('folder/bar.txt');

		$scanner->scan('');
		$new = $cache->get('folder/bar.txt');
		$this->assertEquals($old, $new);
	}

	public function testScanSubMount() {
		$uid = $this->getUniqueID();
		$this->userBackend->createUser($uid, 'test');

		$mountProvider = $this->getMock('\OCP\Files\Config\IMountProvider');

		$storage = new Temporary(array());
		$mount = new MountPoint($storage, '/' . $uid . '/files/foo');

		$mountProvider->expects($this->any())
			->method('getMountsForUser')
			->will($this->returnCallback(function (IUser $user, IStorageFactory $storageFactory) use ($mount, $uid) {
				if ($user->getUID() === $uid) {
					return [$mount];
				} else {
					return [];
				}
			}));

		\OC::$server->getMountProviderCollection()->registerProvider($mountProvider);
		$cache = $storage->getCache();

		$storage->mkdir('folder');
		$storage->file_put_contents('foo.txt', 'qwerty');
		$storage->file_put_contents('folder/bar.txt', 'qwerty');

		$scanner = new \OC\Files\Utils\Scanner($uid, \OC::$server->getDatabaseConnection());

		$this->assertFalse($cache->inCache('folder/bar.txt'));
		$scanner->scan('/' . $uid . '/files/foo');
		$this->assertTrue($cache->inCache('folder/bar.txt'));
	}

	public function testChangePropagator() {
		/**
		 * @var \OC\Files\Cache\ChangePropagator $propagator
		 */
		$propagator = $this->getMock('\OC\Files\Cache\ChangePropagator', array('propagateChanges'), array(), '', false);

		$storage = new Temporary(array());
		$mount = new MountPoint($storage, '/foo');
		Filesystem::getMountManager()->addMount($mount);
		$cache = $storage->getCache();

		$storage->mkdir('folder');
		$storage->file_put_contents('foo.txt', 'qwerty');
		$storage->file_put_contents('folder/bar.txt', 'qwerty');

		$scanner = new TestScanner('', \OC::$server->getDatabaseConnection());
		$originalPropagator = $scanner->getPropagator();
		$scanner->setPropagator($propagator);
		$scanner->addMount($mount);

		$scanner->scan('');

		$changes = $propagator->getChanges();
		$parents = $propagator->getAllParents();
		sort($changes);
		sort($parents);
		$this->assertEquals(array('/foo', '/foo/folder', '/foo/folder/bar.txt', '/foo/foo.txt'), $changes);
		$this->assertEquals(array('/', '/foo', '/foo/folder'), $parents);

		$cache->put('foo.txt', array('storage_mtime' => time() - 50));

		$propagator = $this->getMock('\OC\Files\Cache\ChangePropagator', array('propagateChanges'), array(), '', false);
		$scanner->setPropagator($propagator);
		$storage->file_put_contents('foo.txt', 'asdasd');

		$scanner->scan('');

		$changes = $propagator->getChanges();
		$parents = $propagator->getAllParents();
		$this->assertEquals(array('/foo/foo.txt'), $changes);
		$this->assertEquals(array('/', '/foo'), $parents);

		$scanner->setPropagator($originalPropagator);

		$oldInfo = $cache->get('');
		$cache->put('foo.txt', array('storage_mtime' => time() - 70));
		$storage->file_put_contents('foo.txt', 'asdasd');

		$scanner->scan('');
		$newInfo = $cache->get('');
		$this->assertNotEquals($oldInfo['etag'], $newInfo['etag']);
	}

	/**
	 * @return array
	 */
	public function invalidPathProvider() {
		return [
			[
				'../',
			],
			[
				'..\\',
			],
			[
				'../..\\../',
			],
		];
	}

	/**
	 * @dataProvider invalidPathProvider
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Invalid path to scan
	 * @param string $invalidPath
	 */
	public function testInvalidPathScanning($invalidPath) {
		$scanner = new TestScanner('', \OC::$server->getDatabaseConnection());
		$scanner->scan($invalidPath);
	}
}
