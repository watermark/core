<?php
/**
 * Created by IntelliJ IDEA.
 * User: lukasreschke
 * Date: 7/22/15
 * Time: 4:55 PM
 */

namespace OC\Settings\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

/**
 * Class ComplianceController
 *
 * @package OC\Settings\Controller
 */
class ComplianceController extends Controller {

	/**
	 * @param string $AppName
	 * @param IRequest $request
	 */
	public function __construct($AppName,
								IRequest $request) {
		parent::__construct($AppName, $request);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function showLegalDisclaimers() {
		return new TemplateResponse($this->appName, 'legal/main', [], 'user');
	}

}