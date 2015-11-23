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

namespace OCA\Files_External\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class DBConfigService {
	const MOUNT_TYPE_ADMIN = 1;
	const MOUNT_TYPE_PERSONAl = 2;

	const APPLICABLE_TYPE_GLOBAL = 1;
	const APPLICABLE_TYPE_GROUP = 2;
	const APPLICABLE_TYPE_USER = 3;

	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	 * DBConfigService constructor.
	 *
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param int $mountId
	 * @return array
	 */
	public function getMountById($mountId) {
		$builder = $this->connection->getQueryBuilder();
		$query = $builder->select(['mount_id', 'mount_point', 'storage_backend', 'auth_backend', 'priority', 'type'])
			->from('external_mounts', 'm')
			->where($builder->expr()->eq('mount_id', $builder->createNamedParameter($mountId, \PDO::PARAM_INT)));
		$mounts = $this->getMountsFromQuery($query);
		if (count($mounts) > 0) {
			return $mounts[0];
		} else {
			return null;
		}
	}

	public function getAdminMounts() {
		$builder = $this->connection->getQueryBuilder();
		$query = $builder->select(['mount_id', 'mount_point', 'storage_backend', 'auth_backend', 'priority', 'type'])
			->from('external_mounts')
			->where($builder->expr()->eq('type', self::MOUNT_TYPE_ADMIN));
		return $this->getMountsFromQuery($query);
	}

	protected function getForQuery(IQueryBuilder $builder, $type, $value) {
		$query = $builder->select(['m.mount_id', 'mount_point', 'storage_backend', 'auth_backend', 'priority', 'm.type'])
			->from('external_mounts', 'm')
			->innerJoin('external_mounts', 'a', 'external_applicable', 'm.mount_id = a.mount_id')
			->where($builder->expr()->eq('a.type', $builder->createNamedParameter($type, \PDO::PARAM_INT)))
			->andWhere($builder->expr()->eq('a.value', $builder->createNamedParameter($value, \PDO::PARAM_STR)));
		return $query;
	}

	/**
	 * @param int $type any of the self::APPLICABLE_TYPE_ constants
	 * @param string|null $value user_id, group_id or null for global mounts
	 * @return array
	 */
	public function getMountsFor($type, $value) {
		$builder = $this->connection->getQueryBuilder();
		$query = $this->getForQuery($builder, $type, $value);

		return $this->getMountsFromQuery($query);
	}

	/**
	 * @param int $type any of the self::APPLICABLE_TYPE_ constants
	 * @param string|null $value user_id, group_id or null for global mounts
	 * @return array
	 */
	public function getAdminMountsFor($type, $value) {
		$builder = $this->connection->getQueryBuilder();
		$query = $this->getForQuery($builder, $type, $value);
		$query->andWhere($builder->expr()->eq('type', self::MOUNT_TYPE_ADMIN));

		return $this->getMountsFromQuery($query);
	}

	/**
	 * @param int $type any of the self::APPLICABLE_TYPE_ constants
	 * @param string|null $value user_id, group_id or null for global mounts
	 * @return array
	 */
	public function getUserMountsFor($type, $value) {
		$builder = $this->connection->getQueryBuilder();
		$query = $this->getForQuery($builder, $type, $value);
		$query->andWhere($builder->expr()->eq('type', self::MOUNT_TYPE_PERSONAl));

		return $this->getMountsFromQuery($query);
	}

	/**
	 * @param string $mountPoint
	 * @param string $storageBackend
	 * @param string $authBackend
	 * @param int $priority
	 * @param int $type self::MOUNT_TYPE_ADMIN or self::MOUNT_TYPE_PERSONAL
	 * @return int the id of the new mount
	 */
	public function addMount($mountPoint, $storageBackend, $authBackend, $priority, $type) {
		$builder = $this->connection->getQueryBuilder();
		$query = $builder->insert('external_mounts')
			->values([
				'mount_point' => $builder->createNamedParameter($mountPoint, \PDO::PARAM_STR),
				'storage_backend' => $builder->createNamedParameter($storageBackend, \PDO::PARAM_STR),
				'auth_backend' => $builder->createNamedParameter($authBackend, \PDO::PARAM_STR),
				'priority' => $builder->createNamedParameter($priority, \PDO::PARAM_INT),
				'type' => $builder->createNamedParameter($type, \PDO::PARAM_INT)
			]);
		$query->execute();
		return $this->connection->lastInsertId('external_mounts');
	}

	/**
	 * @param int $mountId
	 */
	public function removeMount($mountId) {
		$builder = $this->connection->getQueryBuilder();
		$query = $builder->delete('external_mounts')
			->where($builder->expr()->eq('mount_id', $builder->createNamedParameter($mountId, \PDO::PARAM_INT)));
		$query->execute();

		$query = $builder->delete('external_applicable')
			->where($builder->expr()->eq('mount_id', $builder->createNamedParameter($mountId, \PDO::PARAM_INT)));
		$query->execute();

		$query = $builder->delete('external_config')
			->where($builder->expr()->eq('mount_id', $builder->createNamedParameter($mountId, \PDO::PARAM_INT)));
		$query->execute();

		$query = $builder->delete('external_options')
			->where($builder->expr()->eq('mount_id', $builder->createNamedParameter($mountId, \PDO::PARAM_INT)));
		$query->execute();
	}

	/**
	 * @param int $mountId
	 * @param string $key
	 * @param string $value
	 */
	public function setConfig($mountId, $key, $value) {
		$count = $this->connection->insertIfNotExist('*PREFIX*external_config', [
			'mount_id' => $mountId,
			'key' => $key,
			'value' => $value
		], ['mount_id', 'key']);
		if ($count === 0) {
			$builder = $this->connection->getQueryBuilder();
			$query = $builder->update('external_config')
				->set('value', $builder->createNamedParameter($value, \PDO::PARAM_STR));
			$query->execute();
		}
	}

	/**
	 * @param int $mountId
	 * @param string $key
	 * @param string $value
	 */
	public function setOption($mountId, $key, $value) {
		$count = $this->connection->insertIfNotExist('*PREFIX*external_options', [
			'mount_id' => $mountId,
			'key' => $key,
			'value' => $value
		], ['mount_id', 'key']);
		if ($count === 0) {
			$builder = $this->connection->getQueryBuilder();
			$query = $builder->update('external_options')
				->set('value', $builder->createNamedParameter($value, \PDO::PARAM_STR));
			$query->execute();
		}
	}

	public function addApplicable($mountId, $type, $value) {
		$this->connection->insertIfNotExist('*PREFIX*external_applicable', [
			'mount_id' => $mountId,
			'type' => $type,
			'value' => $value
		], ['mount_id', 'type', 'value']);
	}

	public function removeApplicable($mountId, $type, $value) {
		$builder = $this->connection->getQueryBuilder();
		$query = $builder->delete('external_applicable')
			->where($builder->expr()->eq('mount_id', $builder->createNamedParameter($mountId, \PDO::PARAM_INT)))
			->andWhere($builder->expr()->eq('type', $builder->createNamedParameter($type, \PDO::PARAM_INT)))
			->andWhere($builder->expr()->eq('value', $builder->createNamedParameter($value, \PDO::PARAM_STR)));
		$query->execute();
	}

	private function getMountsFromQuery(IQueryBuilder $query) {
		$result = $query->execute();
		$mounts = $result->fetchAll();

		$mountIds = array_map(function ($mount) {
			return $mount['mount_id'];
		}, $mounts);

		$applicable = $this->getApplicableForMounts($mountIds);
		$config = $this->getConfigForMounts($mountIds);
		$options = $this->getOptionsForMounts($mountIds);

		return array_map(function ($mount, $applicable, $config, $options) {
			$mount['applicable'] = $applicable;
			$mount['config'] = $config;
			$mount['options'] = $options;
			return $mount;
		}, $mounts, $applicable, $config, $options);
	}

	/**
	 * Get mount options from a table grouped by mount id
	 *
	 * @param string $table
	 * @param string[] $fields
	 * @param int[] $mountIds
	 * @return array [$mountId => [['field1' => $value1, ...], ...], ...]
	 */
	private function selectForMounts($table, array $fields, array $mountIds) {
		if (count($mountIds) === 0) {
			return [];
		}
		$builder = $this->connection->getQueryBuilder();
		$fields[] = 'mount_id';
		$placeHolders = array_map(function ($id) use ($builder) {
			return $builder->createPositionalParameter($id, \PDO::PARAM_INT);
		}, $mountIds);
		$query = $builder->select($fields)
			->from($table)
			->where($builder->expr()->in('mount_id', $placeHolders));
		$rows = $query->execute()->fetchAll();

		$result = [];
		foreach ($mountIds as $mountId) {
			$result[$mountId] = [];
		}
		foreach ($rows as $row) {
			$result[$row['mount_id']][] = $row;
		}
		return $result;
	}

	/**
	 * @param int[] $mountIds
	 * @return array [$id => [['type' => $type, 'value' => $value], ...], ...]
	 */
	public function getApplicableForMounts($mountIds) {
		return $this->selectForMounts('external_applicable', ['type', 'value'], $mountIds);
	}

	/**
	 * @param int[] $mountIds
	 * @return array [$id => ['key1' => $value1, ...], ...]
	 */
	public function getConfigForMounts($mountIds) {
		$mountConfigs = $this->selectForMounts('external_config', ['key', 'value'], $mountIds);
		return array_map([$this, 'createKeyValueMap'], $mountConfigs);
	}

	/**
	 * @param int[] $mountIds
	 * @return array [$id => ['key1' => $value1, ...], ...]
	 */
	public function getOptionsForMounts($mountIds) {
		$mountOptions = $this->selectForMounts('external_options', ['key', 'value'], $mountIds);
		return array_map([$this, 'createKeyValueMap'], $mountOptions);
	}

	/**
	 * @param array $keyValuePairs [['key'=>$key, 'value=>$value], ...]
	 * @return array ['key1' => $value1, ...]
	 */
	private function createKeyValueMap(array $keyValuePairs) {
		$keys = array_map(function ($pair) {
			return $pair['key'];
		}, $keyValuePairs);
		$values = array_map(function ($pair) {
			return $pair['value'];
		}, $keyValuePairs);

		return array_combine($keys, $values);
	}
}
