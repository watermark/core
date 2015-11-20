<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Cache;

use OC\Files\Filesystem;
use OC\Files\Storage\Temporary;
use OC\Files\View;

/**
 * Class ChangePropagator
 *
 * @group DB
 *
 * @package Test\Files\Cache
 */
class ChangePropagator extends \Test\TestCase {
	/**
	 * @var \OC\Files\Cache\ChangePropagator
	 */
	private $propagator;

	/**
	 * @var \OC\Files\View
	 */
	private $view;

	/**
	 * @var \OC\Files\Storage\Storage
	 */
	private $storage;

	protected function setUp() {
		parent::setUp();

		$this->storage = new Temporary(array());
		$root = $this->getUniqueID('/');
		Filesystem::mount($this->storage, array(), $root);
		$this->view = new View($root);
		$this->propagator = new \OC\Files\Cache\ChangePropagator($this->view);
	}

	public function testGetParentsSingle() {
		$this->propagator->addChange('/foo/bar/asd');
		$this->assertEquals(array('/', '/foo', '/foo/bar'), $this->propagator->getAllParents());
	}

	public function testGetParentsMultiple() {
		$this->propagator->addChange('/foo/bar/asd');
		$this->propagator->addChange('/foo/qwerty');
		$this->propagator->addChange('/foo/asd/bar');
		$this->assertEquals(array('/', '/foo', '/foo/bar', '/foo/asd'), $this->propagator->getAllParents());
	}

	public function testSinglePropagate() {
		$this->view->mkdir('/foo');
		$this->view->mkdir('/foo/bar');
		$this->view->file_put_contents('/foo/bar/sad.txt', 'qwerty');

		$oldInfo1 = $this->view->getFileInfo('/');
		$oldInfo2 = $this->view->getFileInfo('/foo');
		$oldInfo3 = $this->view->getFileInfo('/foo/bar');

		$time = time() + 50;

		$this->propagator->addChange('/foo/bar/sad.txt');
		$this->propagator->propagateChanges($time);

		$newInfo1 = $this->view->getFileInfo('/');
		$newInfo2 = $this->view->getFileInfo('/foo');
		$newInfo3 = $this->view->getFileInfo('/foo/bar');

		$this->assertEquals($newInfo1->getMTime(), $time);
		$this->assertEquals($newInfo2->getMTime(), $time);
		$this->assertEquals($newInfo3->getMTime(), $time);

		$this->assertNotSame($oldInfo1->getEtag(), $newInfo1->getEtag());
		$this->assertNotSame($oldInfo2->getEtag(), $newInfo2->getEtag());
		$this->assertNotSame($oldInfo3->getEtag(), $newInfo3->getEtag());
	}

	public function testDontLowerMtime() {
		$time = time();
		$this->view->mkdir('/foo');
		$this->view->mkdir('/foo/bar');

		$cache = $this->storage->getCache();
		$cache->put('', ['mtime' => $time - 50]);
		$cache->put('foo', ['mtime' => $time - 150]);
		$cache->put('foo/bar', ['mtime' => $time - 250]);

		$this->propagator->addChange('/foo/bar/foo');
		$this->propagator->propagateChanges($time - 100);

		$this->assertEquals(50, $time - $cache->get('')['mtime']);
		$this->assertEquals(100, $time - $cache->get('foo')['mtime']);
		$this->assertEquals(100, $time - $cache->get('foo/bar')['mtime']);
	}
}
