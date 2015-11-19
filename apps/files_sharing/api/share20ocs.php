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
namespace OCA\Files_Sharing\API;

use OC\Share20\IShare;
use OCP\IUser;

class Share20OCS {

	/** @var \OC\Share20\Manager */
	private $shareManager;

	/** @var \OCP\IRequest */
	private $request;

	/** @var \OCP\Files\Folder */
	private $userFolder;

	/** @var IUser */
	private $currentUser;

	/**
	 * Share20OCS constructor.
	 *
	 * @param \OC\Share20\Manager $shareManager
	 * @param \OCP\IRequest $request
	 * @param \OCP\Files\Folder $userFolder
	 * @param \OCP\IURLGenerator $urlGenerator
	 * @param IUser $currentUser
	 */
	public function __construct(\OC\Share20\Manager $shareManager,
								\OCP\IRequest $request,
								\OCP\Files\Folder $userFolder,
								\OCP\IURLGenerator $urlGenerator,
								IUser $currentUser) {
		$this->shareManager = $shareManager;
		$this->request = $request;
		$this->userFolder = $userFolder;
		$this->urlGenerator = $urlGenerator;
		$this->currentUser = $currentUser;
	}

	/**
	 * Convert an IShare to an array for OCS output
	 *
	 * @param IShare $share
	 * @return array
	 */
	protected function formatShare($share) {
		$result = [
			'id' => $share->getId(),
			'share_type' => $share->getShareType(),
			'uid_owner' => $share->getSharedBy()->getUID(),
			'displayname_owner' => $share->getSharedBy()->getDisplayName(),
			'permissions' => $share->getPermissions(),
			'stime' => $share->getShareTime(),
			'parent' => $share->getParent(),
			'expiration' => null,
			'token' => null,
		];

		$path = $share->getPath();
		$result['path'] = $this->userFolder->getRelativePath($path->getPath());
		if ($path instanceOf \OCP\Files\Folder) {
			$result['item_type'] = 'folder';
		} else {
			$result['item_type'] = 'file';
		}
		$result['storage_id'] = $path->getStorage()->getId();
		$result['storage'] = \OC\Files\Cache\Storage::getNumericStorageId($path->getStorage()->getId());
		$result['item_source'] = $path->getId();
		$result['file_source'] = $path->getId();
		$result['file_parent'] = $path->getParent()->getId();
		$result['file_target'] = $share->getTarget();

		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_USER) {
			$sharedWith = $share->getSharedWith();
			$result['share_with'] = $sharedWith->getUID();
			$result['share_with_displayname'] = $sharedWith->getDisplayName();
		} else if ($share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP) {
			$sharedWith = $share->getSharedWith();
			$result['share_with'] = $sharedWith->getGID();
			$result['share_with_displayname'] = $sharedWith->getGID();
		} else if ($share->getShareType() === \OCP\Share::SHARE_TYPE_LINK) {

			$result['share_with'] = $share->getPassword();
			$result['share_with_displayname'] = $share->getPassword();

			$result['token'] = $share->getToken();
			$result['url'] = $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $share->getToken()]);

			$expiration = $share->getExpirationDate();
			if ($expiration !== null) {
				$result['expiration'] = $expiration->format('Y-m-d 00:00:00');
			}

		} else if ($share->getShareType() === \OCP\Share::SHARE_TYPE_REMOTE) {
			$result['share_with'] = $share->getSharedWith();
			$result['share_with_displayname'] = $share->getSharedWith();
			$result['token'] = $share->getToken();
		}

		$result['mail_send'] = $share->getMailSend() ? 1 : 0;

		return $result;
	}

	/**
	 * Get a specific share by id
	 *
	 * @param string $id
	 * @return \OC_OCS_Result
	 */
	public function getShare($id) {
		try {
			$share = $this->shareManager->getShareById($id);
		} catch (\OC\Share20\Exception\ShareNotFound $e) {
			return new \OC_OCS_Result(null, 404, 'wrong share ID, share doesn\'t exist.');
		}

		$share = $this->formatShare($share);
		return new \OC_OCS_Result($share);
	}

	/**
	 * Delete a share
	 *
	 * @param string $id
	 * @return \OC_OCS_Result
	 */
	public function deleteShare($id) {
		try {
			$share = $this->shareManager->getShareById($id);
		} catch (\OC\Share20\Exception\ShareNotFound $e) {
			return new \OC_OCS_Result(null, 404, 'wrong share ID, share doesn\'t exist.');
		}

		/*
		 * FIXME
		 * User the old code path for remote shares until we have our remoteshareprovider
		 */
		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_REMOTE) {
			\OCA\Files_Sharing\API\Local::deleteShare(['id' => $id]);
		}

		try {
			$this->shareManager->deleteShare($share);
		} catch (\OC\Share20\Exception\BackendError $e) {
			return new \OC_OCS_Result(null, 404, 'could not delete share');
		}

		return new \OC_OCS_Result();
	}

	/**
	 * Create a share
	 *
	 * @return \OC_OCS_Result
	 */
	public function createShare() {
		$path = $this->request->getParam('path', null);
		$shareType = $this->request->getParam('shareType', null);

		if ($shareType === null) {
			return new \OC_OCS_Result(null, 400, "unknown share type");
		}
		$shareType = (int)$shareType;

		/*
		 * Verify proper path
		 */
		if($path === null) {
			return new \OC_OCS_Result(null, 400, "please specify a file or folder path");
		}

		try {
			$path = $this->userFolder->get($path);
		} catch (\OCP\Files\NotFoundException $e) {
			return new \OC_OCS_Result(null, 404, "wrong path, file/folder doesn't exist.");
		}

		if ($shareType !== \OCP\Share::SHARE_TYPE_LINK) {
			\OCA\Files_Sharing\API\Local::createShare([]);
		}

		/*
		 * Get a new share object
		 */
		$share = $this->shareManager->newShare();
		$share->setPath($path);

		$share->setSharedBy($this->currentUser);

		if ($shareType === \OCP\Share::SHARE_TYPE_LINK) {
			$share->setShareType(\OCP\Share::SHARE_TYPE_LINK);
	
			// Parse permissions
			$publicUpload = $this->request->getParam('publicUpload', null);
			$permissions = \OCP\Constants::PERMISSION_READ;
			if ($publicUpload === 'true') {
				$permissions |= \OCP\Constants::PERMISSION_CREATE | \OCP\Constants::PERMISSION_UPDATE;
			}

			// Parse expire date
			$expireDate = $this->request->getParam('expireDate', null);
			if ($expireDate !== null) {
				try {
					$expireDate = $this->parseDate($expireDate);
				} catch (\Exception $e) {
					return new \OC_OCS_Result(null, 404, 'Invalid Date. Format must be YYYY-MM-DD.');
				}
				$share->setExpirationDate($expireDate);
			}

			// Get password
			$password = $this->request->getParam('password', null);
			if ($password !== null) {
				$share->setPassword($password);
			}
		}

		$share->setPermissions($permissions);

		$share = $this->shareManager->createShare($share);

		return new \OC_OCS_Result($this->formatShare($share));
	}

	/**
	 * Make sure that the passed date is valid ISO 8601
	 * So YYYY-MM-DD
	 * If not throw an exception
	 *
	 * @param string $expireDate
	 *
	 * @throws \Exception
	 * @return \DateTime
	 */
	private function parseDate($expireDate) {
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expireDate) === 0) {
			throw new \Exception('Invalid date. Format must be YYYY-MM-DD');
		}
		
		try {
			$date = new \DateTime($expireDate);
		} catch (\Exception $e) {
			throw new \Exception('Invalid date. Format must be YYYY-MM-DD');
		}

		if ($date === false) {
			throw new \Exception('Invalid date. Format must be YYYY-MM-DD');
		}

		$date->setTime(0,0,0);

		return $date;
	}
}
