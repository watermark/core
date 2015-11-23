<?php
/**
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
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

namespace OCA\Files_external\Service;

use \OCP\IUserSession;
use \OC\Files\Filesystem;

use \OCA\Files_external\Lib\StorageConfig;
use \OCA\Files_external\NotFoundException;
use \OCA\Files_External\Service\BackendService;
use \OCA\Files_External\Service\UserTrait;

/**
 * Service class to manage user external storages
 * (aka personal storages)
 */
class UserStoragesService extends StoragesService {

	use UserTrait;

	/**
	 * Create a user storages service
	 *
	 * @param BackendService $backendService
	 * @param DBConfigService $dbConfig
	 * @param IUserSession $userSession user session
	 */
	public function __construct(
		BackendService $backendService,
		DBConfigService $dbConfig,
		IUserSession $userSession
	) {
		$this->userSession = $userSession;
		parent::__construct($backendService, $dbConfig);
	}

	protected function readDBConfig() {
		return $this->dbConfig->getUserMountsFor(DBConfigService::APPLICABLE_TYPE_USER, $this->getUser()->getUID());
	}

	/**
	 * Triggers $signal for all applicable users of the given
	 * storage
	 *
	 * @param StorageConfig $storage storage data
	 * @param string $signal signal to trigger
	 */
	protected function triggerHooks(StorageConfig $storage, $signal) {
		$user = $this->getUser()->getUID();

		// trigger hook for the current user
		$this->triggerApplicableHooks(
			$signal,
			$storage->getMountPoint(),
			\OC_Mount_Config::MOUNT_TYPE_USER,
			[$user]
		);
	}

	/**
	 * Triggers signal_create_mount or signal_delete_mount to
	 * accomodate for additions/deletions in applicableUsers
	 * and applicableGroups fields.
	 *
	 * @param StorageConfig $oldStorage old storage data
	 * @param StorageConfig $newStorage new storage data
	 */
	protected function triggerChangeHooks(StorageConfig $oldStorage, StorageConfig $newStorage) {
		// if mount point changed, it's like a deletion + creation
		if ($oldStorage->getMountPoint() !== $newStorage->getMountPoint()) {
			$this->triggerHooks($oldStorage, Filesystem::signal_delete_mount);
			$this->triggerHooks($newStorage, Filesystem::signal_create_mount);
		}
	}

	/**
	 * Get the visibility type for this controller, used in validation
	 *
	 * @return string BackendService::VISIBILITY_* constants
	 */
	public function getVisibilityType() {
		return BackendService::VISIBILITY_PERSONAL;
	}
}
