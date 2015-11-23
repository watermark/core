<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
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
use \OCA\Files_External\Lib\Backend\Backend;
use \OCA\Files_External\Lib\Auth\AuthMechanism;

/**
 * Service class to manage external storages
 */
abstract class StoragesService {

	/** @var BackendService */
	protected $backendService;

	/**
	 * @var DBConfigService
	 */
	protected $dbConfig;

	/**
	 * @param BackendService $backendService
	 * @param DBConfigService $dbConfigService
	 */
	public function __construct(BackendService $backendService, DBConfigService $dbConfigService) {
		$this->backendService = $backendService;
		$this->dbConfig = $dbConfigService;
	}

	protected function readDBConfig() {
		return $this->dbConfig->getAdminMounts();
	}

	protected function getStorageConfigFromDBMount(array $mount) {
		$applicableUsers = array_filter($mount['applicable'], function ($applicable) {
			return $applicable['type'] === DBConfigService::APPLICABLE_TYPE_USER;
		});
		$applicableUsers = array_map(function ($applicable) {
			return $applicable['value'];
		}, $applicableUsers);

		$applicableGroups = array_filter($mount['applicable'], function ($applicable) {
			return $applicable['type'] === DBConfigService::APPLICABLE_TYPE_GROUP;
		});
		$applicableGroups = array_map(function ($applicable) {
			return $applicable['value'];
		}, $applicableGroups);

		return $this->createStorage(
			$mount['mount_point'],
			$mount['storage_backend'],
			$mount['auth_backend'],
			$mount['config'],
			$mount['options'],
			$applicableUsers,
			$applicableGroups,
			$mount['priority']
		);
	}

	/**
	 * Read the external storages config
	 *
	 * @return array map of storage id to storage config
	 */
	protected function readConfig() {
		$mounts = $this->readDBConfig();
		$configs = array_map([$this, 'getStorageConfigFromDBMount'], $mounts);

		$keys = array_map(function (StorageConfig $config) {
			return $config->getId();
		}, $configs);

		return array_combine($keys, $configs);
	}

	/**
	 * Get a storage with status
	 *
	 * @param int $id storage id
	 *
	 * @return StorageConfig
	 * @throws NotFoundException if the storage with the given id was not found
	 */
	public function getStorage($id) {
		$allStorages = $this->readConfig();

		if (!isset($allStorages[$id])) {
			throw new NotFoundException('Storage with id "' . $id . '" not found');
		}

		return $allStorages[$id];
	}

	/**
	 * Gets all storages, valid or not
	 *
	 * @return StorageConfig[] array of storage configs
	 */
	public function getAllStorages() {
		return $this->readConfig();
	}

	/**
	 * Gets all valid storages
	 *
	 * @return StorageConfig[]
	 */
	public function getStorages() {
		return array_filter($this->getAllStorages(), [$this, 'validateStorage']);
	}

	/**
	 * Validate storage
	 * FIXME: De-duplicate with StoragesController::validate()
	 *
	 * @param StorageConfig $storage
	 * @return bool
	 */
	protected function validateStorage(StorageConfig $storage) {
		/** @var Backend */
		$backend = $storage->getBackend();
		/** @var AuthMechanism */
		$authMechanism = $storage->getAuthMechanism();

		if (!$backend->isVisibleFor($this->getVisibilityType())) {
			// not permitted to use backend
			return false;
		}
		if (!$authMechanism->isVisibleFor($this->getVisibilityType())) {
			// not permitted to use auth mechanism
			return false;
		}

		return true;
	}

	/**
	 * Get the visibility type for this controller, used in validation
	 *
	 * @return string BackendService::VISIBILITY_* constants
	 */
	abstract public function getVisibilityType();

	protected function getType() {
		return DBConfigService::MOUNT_TYPE_ADMIN;
	}

	/**
	 * Add new storage to the configuration
	 *
	 * @param StorageConfig $newStorage storage attributes
	 *
	 * @return StorageConfig storage config, with added id
	 */
	public function addStorage(StorageConfig $newStorage) {
		$allStorages = $this->readConfig();

		$configId = $this->dbConfig->addMount(
			$newStorage->getMountPoint(),
			$newStorage->getBackend()->getIdentifier(),
			$newStorage->getAuthMechanism()->getIdentifier(),
			$newStorage->getPriority(),
			$this->getType()
		);

		$newStorage->setId($configId);

		foreach ($newStorage->getApplicableUsers() as $user) {
			$this->dbConfig->addApplicable($configId, DBConfigService::APPLICABLE_TYPE_USER, $user);
		}
		foreach ($newStorage->getApplicableGroups() as $group) {
			$this->dbConfig->addApplicable($configId, DBConfigService::APPLICABLE_TYPE_GROUP, $group);
		}
		foreach ($newStorage->getBackendOptions() as $key => $value) {
			$this->dbConfig->setConfig($configId, $key, $value);
		}
		foreach ($newStorage->getMountOptions() as $key => $value) {
			$this->dbConfig->setOption($configId, $key, $value);
		}

		// add new storage
		$allStorages[$configId] = $newStorage;

		$this->triggerHooks($newStorage, Filesystem::signal_create_mount);

		$newStorage->setStatus(\OC_Mount_Config::STATUS_SUCCESS);
		return $newStorage;
	}

	/**
	 * Create a storage from its parameters
	 *
	 * @param string $mountPoint storage mount point
	 * @param string $backendIdentifier backend identifier
	 * @param string $authMechanismIdentifier authentication mechanism identifier
	 * @param array $backendOptions backend-specific options
	 * @param array|null $mountOptions mount-specific options
	 * @param array|null $applicableUsers users for which to mount the storage
	 * @param array|null $applicableGroups groups for which to mount the storage
	 * @param int|null $priority priority
	 *
	 * @return StorageConfig
	 */
	public function createStorage(
		$mountPoint,
		$backendIdentifier,
		$authMechanismIdentifier,
		$backendOptions,
		$mountOptions = null,
		$applicableUsers = null,
		$applicableGroups = null,
		$priority = null
	) {
		$backend = $this->backendService->getBackend($backendIdentifier);
		if (!$backend) {
			throw new \InvalidArgumentException('Unable to get backend for ' . $backendIdentifier);
		}
		$authMechanism = $this->backendService->getAuthMechanism($authMechanismIdentifier);
		if (!$authMechanism) {
			throw new \InvalidArgumentException('Unable to get authentication mechanism for ' . $authMechanismIdentifier);
		}
		$newStorage = new StorageConfig();
		$newStorage->setMountPoint($mountPoint);
		$newStorage->setBackend($backend);
		$newStorage->setAuthMechanism($authMechanism);
		$newStorage->setBackendOptions($backendOptions);
		if (isset($mountOptions)) {
			$newStorage->setMountOptions($mountOptions);
		}
		if (isset($applicableUsers)) {
			$newStorage->setApplicableUsers($applicableUsers);
		}
		if (isset($applicableGroups)) {
			$newStorage->setApplicableGroups($applicableGroups);
		}
		if (isset($priority)) {
			$newStorage->setPriority($priority);
		}

		return $newStorage;
	}

	/**
	 * Triggers the given hook signal for all the applicables given
	 *
	 * @param string $signal signal
	 * @param string $mountPoint hook mount pount param
	 * @param string $mountType hook mount type param
	 * @param array $applicableArray array of applicable users/groups for which to trigger the hook
	 */
	protected function triggerApplicableHooks($signal, $mountPoint, $mountType, $applicableArray) {
		foreach ($applicableArray as $applicable) {
			\OCP\Util::emitHook(
				Filesystem::CLASSNAME,
				$signal,
				[
					Filesystem::signal_param_path => $mountPoint,
					Filesystem::signal_param_mount_type => $mountType,
					Filesystem::signal_param_users => $applicable,
				]
			);
		}
	}

	/**
	 * Triggers $signal for all applicable users of the given
	 * storage
	 *
	 * @param StorageConfig $storage storage data
	 * @param string $signal signal to trigger
	 */
	abstract protected function triggerHooks(StorageConfig $storage, $signal);

	/**
	 * Triggers signal_create_mount or signal_delete_mount to
	 * accomodate for additions/deletions in applicableUsers
	 * and applicableGroups fields.
	 *
	 * @param StorageConfig $oldStorage old storage data
	 * @param StorageConfig $newStorage new storage data
	 */
	abstract protected function triggerChangeHooks(StorageConfig $oldStorage, StorageConfig $newStorage);

	/**
	 * Update storage to the configuration
	 *
	 * @param StorageConfig $updatedStorage storage attributes
	 *
	 * @return StorageConfig storage config
	 * @throws NotFoundException if the given storage does not exist in the config
	 */
	public function updateStorage(StorageConfig $updatedStorage) {
		$id = $updatedStorage->getId();

		$existingMount = $this->dbConfig->getMountById($id);

		if (!is_array($existingMount)) {
			throw new NotFoundException('Storage with id "' . $id . '" not found');
		}

		$oldStorage = $this->getStorageConfigFromDBMount($existingMount);

		$removedUsers = array_diff($oldStorage->getApplicableUsers(), $updatedStorage->getApplicableUsers());
		$removedGroups = array_diff($oldStorage->getApplicableUsers(), $updatedStorage->getApplicableUsers());
		$addedUsers = array_diff($updatedStorage->getApplicableUsers(), $oldStorage->getApplicableUsers());
		$addedGroups = array_diff($updatedStorage->getApplicableUsers(), $oldStorage->getApplicableUsers());

		foreach ($removedUsers as $user) {
			$this->dbConfig->removeApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, $user);
		}
		foreach ($removedGroups as $group) {
			$this->dbConfig->removeApplicable($id, DBConfigService::APPLICABLE_TYPE_GROUP, $group);
		}
		foreach ($addedUsers as $user) {
			$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_USER, $user);
		}
		foreach ($addedGroups as $group) {
			$this->dbConfig->addApplicable($id, DBConfigService::APPLICABLE_TYPE_GROUP, $group);
		}

		$changedConfig = array_diff_assoc($updatedStorage->getBackendOptions(), $oldStorage->getBackendOptions());
		$changedOptions = array_diff_assoc($updatedStorage->getMountOptions(), $oldStorage->getMountOptions());

		foreach ($changedConfig as $key => $value) {
			$this->dbConfig->setConfig($id, $key, $value);
		}
		foreach ($changedOptions as $key => $value) {
			$this->dbConfig->setOption($id, $key, $value);
		}

		$this->triggerChangeHooks($oldStorage, $updatedStorage);

		return $this->getStorage($id);
	}

	/**
	 * Delete the storage with the given id.
	 *
	 * @param int $id storage id
	 *
	 * @throws NotFoundException if no storage was found with the given id
	 */
	public function removeStorage($id) {
		$existingMount = $this->dbConfig->getMountById($id);

		if (!is_array($existingMount)) {
			throw new NotFoundException('Storage with id "' . $id . '" not found');
		}

		$this->dbConfig->removeMount($id);

		$deletedStorage = $this->getStorageConfigFromDBMount($existingMount);
		$this->triggerHooks($deletedStorage, Filesystem::signal_delete_mount);

		// delete oc_storages entries and oc_filecache
		try {
			$rustyStorageId = $this->getRustyStorageIdFromConfig($deletedStorage);
			\OC\Files\Cache\Storage::remove($rustyStorageId);
		} catch (\Exception $e) {
			// can happen either for invalid configs where the storage could not
			// be instantiated or whenever $user vars where used, in which case
			// the storage id could not be computed
			\OCP\Util::writeLog(
				'files_external',
				'Exception: "' . $e->getMessage() . '"',
				\OCP\Util::ERROR
			);
		}
	}

	/**
	 * Returns the rusty storage id from oc_storages from the given storage config.
	 *
	 * @param StorageConfig $storageConfig
	 * @return string rusty storage id
	 */
	private function getRustyStorageIdFromConfig(StorageConfig $storageConfig) {
		// if any of the storage options contains $user, it is not possible
		// to compute the possible storage id as we don't know which users
		// mounted it already (and we certainly don't want to iterate over ALL users)
		foreach ($storageConfig->getBackendOptions() as $value) {
			if (strpos($value, '$user') !== false) {
				throw new \Exception('Cannot compute storage id for deletion due to $user vars in the configuration');
			}
		}

		// note: similar to ConfigAdapter->prepateStorageConfig()
		$storageConfig->getAuthMechanism()->manipulateStorageConfig($storageConfig);
		$storageConfig->getBackend()->manipulateStorageConfig($storageConfig);

		$class = $storageConfig->getBackend()->getStorageClass();
		$storageImpl = new $class($storageConfig->getBackendOptions());

		return $storageImpl->getId();
	}

}
