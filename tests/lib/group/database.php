<?php
/**
* ownCloud
*
* @author Robin Appelman
* @copyright 2012 Robin Appelman icewind@owncloud.com
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

/**
 * Class Test_Group_Database
 *
 * @group DB
 */
class Test_Group_Database extends Test_Group_Backend {
	private $groups=array();

	/**
	 * get a new unique group name
	 * test cases can override this in order to clean up created groups
	 * @return string
	 */
	public function getGroupName($name = null) {
		$name = parent::getGroupName($name);
		$this->groups[] = $name;
		return $name;
	}

	protected function setUp() {
		parent::setUp();
		$this->backend=new OC_Group_Database();
	}

	protected function tearDown() {
		foreach($this->groups as $group) {
			$this->backend->deleteGroup($group);
		}
		parent::tearDown();
	}
}
