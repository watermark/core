<?php
/**
 * @author Roeland Jago Douma <rullzer@owncloud.com>
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
namespace OC\Share20;


use OCP\IAppConfig;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\ILogger;
use OCP\Files\Folder;

use OC\Share20\Exception\ShareNotFound;

/**
 * This class is the communication hub for all sharing related operations.
 */
class Manager {

	/**
	 * @var IShareProvider[]
	 */
	private $defaultProvider;

	/** @var IUser */
	private $currentUser;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var ILogger */
	private $logger;

	/** @var IAppConfig */
	private $appConfig;

	/** @var IFolder */
	private $userFolder;

	public function __construct(IUser $user,
								IUserManager $userManager,
								IGroupManager $groupManager,
								ILogger $logger,
								IAppConfig $appConfig,
								Folder $userFolder,
								IShareProvider $defaultProvider) {
		$this->currentUser = $user;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
		$this->appConfig = $appConfig;
		$this->userFolder = $userFolder;

		// TEMP SOLUTION JUST TO GET STARTED
		$this->defaultProvider = $defaultProvider;
	}

	/**
	 * Share a path
	 * 
	 * @param Share $share
	 * @return Share The share object
	 */
	public function createShare(Share $share) {
		throw new \Exception();
	}

	/**
	 * Update a share
	 *
	 * @param Share $share
	 * @return Share The share object
	 */
	public function updateShare(Share $share) {
		throw new \Exception();
	}

	/**
	 * Delete all the children of this share
	 *
	 * @param IShare $share
	 * @return IShare[] List of deleted shares
	 */
	protected function deleteChildren(IShare $share) {
		$deletedShares = [];
		foreach($this->defaultProvider->getChildren($share) as $child) {
			$deletedChildren = $this->deleteChildren($child);
			$deletedShares = array_merge($deletedShares, $deletedChildren);

			$this->defaultProvider->delete($child);
			$deletedShares[] = $child;
		}

		return $deletedShares;
	}

	/**
	 * Delete a share
	 *
	 * @param Share $share
	 * @throws ShareNotFound
	 * @throws \OC\Share20\Exception\BackendError
	 */
	public function deleteShare(IShare $share) {
		// Just to make sure we have all the info
		$share = $this->getShareById($share->getId());

		$formatHookParams = function($share) {
			// Prepare hook
			$shareType = $share->getShareType();
			$sharedWith = '';
			if ($shareType === \OCP\Share::SHARE_TYPE_USER) {
				$sharedWith = $share->getSharedWith()->getUID();
			} else if ($shareType === \OCP\Share::SHARE_TYPE_GROUP) {
				$sharedWith = $share->getSharedWith()->getGID();
			} else if ($shareType === \OCP\Share::SHARE_TYPE_REMOTE) {
				$sharedWith = $share->getSharedWith();
			}

			$hookParams = [
				'id'         => $share->getId(),
				'itemType'   => $share->getPath() instanceof \OCP\Files\File ? 'file' : 'folder',
				'itemSource' => $share->getPath()->getId(),
				'shareType'  => $shareType,
				'shareWith'  => $sharedWith,
				'itemparent' => $share->getParent(),
				'uidOwner'   => $share->getSharedBy()->getUID(),
				'fileSource' => $share->getPath()->getId(),
				'fileTarget' => $share->getTarget()
			];
			return $hookParams;
		};

		$hookParams = $formatHookParams($share);

		// Emit pre-hook
		\OC_Hook::emit('OCP\Share', 'pre_unshare', $hookParams);

		// Get all children and delete them as well
		$deletedShares = $this->deleteChildren($share);

		// Do the actual delete
		$this->defaultProvider->delete($share);

		// All the deleted shares caused by this delete
		$deletedShares[] = $share;

		//Format hook info
		$formattedDeletedShares = array_map(function($share) use ($formatHookParams) {
			return $formatHookParams($share);
		}, $deletedShares);

		$hookParams['deletedShares'] = $formattedDeletedShares;

		// Emit post hook
		\OC_Hook::emit('OCP\Share', 'post_unshare', $hookParams);
	}

	/**
	 * Retrieve all shares by the current user
	 *
	 * @param int $page
	 * @param int $perPage
	 * @return Share[]
	 */
	public function getShares($page=0, $perPage=50) {
		throw new \Exception();
	}

	/**
	 * Retrieve a share by the share id
	 *
	 * @param string $id
	 * @return Share
	 *
	 * @throws ShareNotFound
	 */
	public function getShareById($id) {
		if ($id === null) {
			throw new ShareNotFound();
		}

		$share = $this->defaultProvider->getShareById($id);

		if ($share->getSharedWith() !== $this->currentUser &&
		    $share->getSharedBy()   !== $this->currentUser &&
			$share->getShareOwner() !== $this->currentUser) {
			throw new ShareNotFound();
		}

		return $share;
	}

	/**
	 * Get all the shares for a given path
	 *
	 * @param \OCP\Files\Node $path
	 * @param int $page
	 * @param int $perPage
	 *
	 * @return Share[]
	 */
	public function getSharesByPath(\OCP\Files\Node $path, $page=0, $perPage=50) {
		throw new \Exception();
	}

	/**
	 * Get all shares that are shared with the current user
	 *
	 * @param int $shareType
	 * @param int $page
	 * @param int $perPage
	 *
	 * @return Share[]
	 */
	public function getSharedWithMe($shareType = null, $page=0, $perPage=50) {
		throw new \Exception();
	}

	/**
	 * Get the share by token possible with password
	 *
	 * @param string $token
	 * @param string $password
	 *
	 * @return Share
	 *
	 * @throws ShareNotFoundException
	 */
	public function getShareByToken($token, $password=null) {
		throw new \Exception();
	}

	/**
	 * Get access list to a path. This means
	 * all the users and groups that can access a given path.
	 *
	 * Consider:
	 * -root
	 * |-folder1
	 *  |-folder2
	 *   |-fileA
	 *
	 * fileA is shared with user1
	 * folder2 is shared with group2
	 * folder1 is shared with user2
	 *
	 * Then the access list will to '/folder1/folder2/fileA' is:
	 * [
	 * 	'users' => ['user1', 'user2'],
	 *  'groups' => ['group2']
	 * ]
	 *
	 * This is required for encryption
	 *
	 * @param \OCP\Files\Node $path
	 */
	public function getAccessList(\OCP\Files\Node $path) {
		throw new \Exception();
	}
}
