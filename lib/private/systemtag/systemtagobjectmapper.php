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

use \OCP\SystemTag\ISystemTagManager;
use \OCP\IDBConnection;

class SystemTagObjectMapper implements \OCP\SystemTag\ISystemTagObjectMapper {

	const RELATION_TABLE = '*PREFIX*systemtag_object_mapping';

	/**
	 * @var ISystemTagManager
	 */
	private $tagManager;

	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	* Constructor.
	*
	* @param IDBConnection $connection database connection
	* @param ISystemTagManager system tag manager
	*/
	public function __construct(IDBConnection $connection, ISystemTagManager $tagManager) {
		$this->connection = $connection;
		$this->tagManager = $tagManager;
	}

	/**
	 * {$inheritdoc}
	 */
	public function getTagIdsForObjects($objIds, $objectType) {
		// TODO
	}

	/**
	 * {$inheritdoc}
	 */
	public function getObjectIdsForTags($tagIds, $objectType) {
		// TODO
	}

	/**
	 * {%inheritdoc}
	 */
	public function assignTags($objId, $objectType, $tagIds) {
		// TODO
	}

	/**
	 * {%inheritdoc}
	 */
	public function unassignTags($objId, $objectType, $tagIds) {
		// TODO
	}

	/**
	 * {%inheritdoc}
	 */
	public function haveTag($objIds, $objectType, $tagId, $all = true) {
		// TODO
		return false;
	}
}
