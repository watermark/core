<?php
/**
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Morris Jobke <hey@morrisjobke.de>
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

$dbConnection = \OC::$server->getDatabaseConnection();
$userManager = OC::$server->getUserManager();
$shareManager = new \OC\Share20\Manager(null, $userManager, null, null, null, null, null);

/** @var Symfony\Component\Console\Application $application */
$application->add(new OCA\Files\Command\Scan($userManager));
$application->add(new OCA\Files\Command\DeleteOrphanedFiles($dbConnection));
$application->add(new OCA\Files\Command\TransferOwnership($userManager, $shareManager));

