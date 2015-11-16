<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OC\SystemTag;

use \OCP\IDBConnection;

class SystemTagManager implements \OCP\SystemTag\ISystemTagManager {

	const TAG_TABLE = '*PREFIX*systemtag';

	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	* Constructor.
	*
	* @param IDBConnection $connection database connection
	*/
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * {%inheritdoc}
	 */
	public function getTagsById($id) {
		// TODO
	}

	/**
	 * {%inheritdoc}
	 */
	public function getAllTags($visibleOnly = false, $nameSearchPattern = null) {
		// TODO
	}

	/**
	 * {%inheritdoc}
	 */
	public function getTag($tagName, $userVisible, $userAssignable) {
		// TODO
	}

	/**
	 * {%inheritdoc}
	 */
	public function createTag($tagName, $userVisible, $userAssignable) {
		// TODO
	}

	/**
	 * {%inheritdoc}
	 */
	public function updateTag($tagId, $newName, $userVisible, $userAssignable) {
		// TODO
	}

	/**
	 * {%inheritdoc}
	 */
	public function deleteTags($tagIds) {
		// TODO
	}
}
