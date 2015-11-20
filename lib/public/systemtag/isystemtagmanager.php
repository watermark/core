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

namespace OCP\SystemTag;

/**
 * Public interface to access and manage system-wide tags.
 *
 * @since 9.0.0
 */
interface ISystemTagManager {

	/**
	 * Returns the tag objects matching the given tag ids.
	 *
	 * @param array|string $tagIds The ID or array of IDs of the tags to retrieve
	 *
	 * @return \OCP\SystemTag\ISystemTag[] array of system tags or empty array if none found
	 *
	 * @since 9.0.0
	 */
	public function getTagsById($tagIds);

	/**
	 * Returns the tag object matching the given attributes.
	 *
	 * @param string $tagName tag name
	 * @param bool $userVisible whether the tag is visible by users
	 * @param bool $userAssignable whether the tag is assignable by users
	 *
	 * @return \OCP\SystemTag\ISystemTag system tag
	 *
	 * @throws \OCP\SystemTag\TagNotFoundException if tag does not exist
	 *
	 * @since 9.0.0
	 */
	public function getTag($tagName, $userVisible, $userAssignable);

	/**
	 * Creates the tag object using the given attributes.
	 *
	 * @param string $tagName tag name
	 * @param bool $userVisible whether the tag is visible by users
	 * @param bool $userAssignable whether the tag is assignable by users
	 *
	 * @return \OCP\SystemTag\ISystemTag system tag
	 *
	 * @throws \OCP\SystemTag\TagAlreadyExistsException if tag already exists
	 *
	 * @since 9.0.0
	 */
	public function createTag($tagName, $userVisible, $userAssignable);

	/**
	 * Returns all known tags, optionally filtered by visibility.
	 *
	 * @param bool $visibleOnly whether to only return user visible tags
	 * @param string $nameSearchPattern optional search pattern for the tag name
	 *
	 * @return \OCP\SystemTag\ISystemTag[] array of system tags or empty array if none found
	 *
	 * @since 9.0.0
	 */
	public function getAllTags($visibleOnly = false, $nameSearchPattern = null);

	/**
	 * Updates the given tag
	 *
	 * @param string $tagId tag id
	 * @param string $newName the new tag name
	 * @param bool $userVisible whether the tag is visible by users
	 * @param bool $userAssignable whether the tag is assignable by users
	 *
	 * @throws \OCP\SystemTag\TagNotFoundException if tag with the given id does not exist
	 * @throws \OCP\SystemTag\TagAlreadyExistsException if there is already another tag
	 * with the same attributes
	 *
	 * @since 9.0.0
	 */
	public function updateTag($tagId, $newName, $userVisible, $userAssignable);

	/**
	 * Delete the given tags from the database and all their relationships.
	 *
	 * @param string|array $tagIds array of tag ids
	 *
	 * @throws \OCP\SystemTag\TagNotFoundException if tag did not exist
	 *
	 * @since 9.0.0
	 */
	public function deleteTags($tagIds);

}
