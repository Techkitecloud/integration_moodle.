<?php
/**
 * Nextcloud - moodle
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Moodle\Controller;

use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\AppFramework\Http\DataDisplayResponse;

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use Psr\Log\LoggerInterface;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Moodle\Service\MoodleAPIService;
use OCA\Moodle\AppInfo\Application;

class MoodleAPIController extends Controller {


	private $userId;
	private $config;
	private $dbconnection;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IServerContainer $serverContainer,
								IConfig $config,
								IL10N $l10n,
								IAppManager $appManager,
								IAppData $appData,
								LoggerInterface $logger,
								MoodleAPIService $moodleAPIService,
								$userId) {
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->appData = $appData;
		$this->serverContainer = $serverContainer;
		$this->config = $config;
		$this->logger = $logger;
		$this->moodleAPIService = $moodleAPIService;
		$this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token', '');
		$this->moodleUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', '');
		$this->checkSsl = $this->config->getUserValue($this->userId, Application::APP_ID, 'check_ssl', '1') === '1';
	}

	/**
	 * get notification list
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getMoodleUrl(): DataResponse {
		return new DataResponse($this->moodleUrl);
	}

	/**
	 * authenticate and get access token
	 * @NoAdminRequired
	 *
	 * @param string $login
	 * @param string $password
	 * @return DataResponse
	 */
	public function getToken(string $login, string $password): DataResponse {
		$result = $this->moodleAPIService->getToken($this->moodleUrl, $login, $password, $this->checkSsl);
		if (!isset($result['error'])) {
			// we save the client ID and secret and give the client ID back to the UI
			$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $result['token']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'privatetoken', $result['privatetoken']);
			// get user info
			$chosenName = $login;
			$params = [
				'wstoken' => $result['token'],
				'wsfunction' => 'core_user_get_users_by_field',
				'moodlewsrestformat' => 'json',
				'field' => 'username',
				'values' => [$login],
			];
			$info = $this->moodleAPIService->request($this->moodleUrl, 'webservice/rest/server.php', $this->checkSsl, $params);
			if (!isset($info['error']) && count($info) > 0) {
				$fullName = $info[0]['fullname'];
				$chosenName = $fullName ?: $login;
				$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $chosenName);
			}
			$data = [
				'user_name' => $chosenName,
			];
			$response = new DataResponse($data);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

	/**
	 * get moodle user avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $url
	 * @return DataDisplayResponse
	 */
	public function getMoodleAvatar(string $url): DataDisplayResponse {
		$content = $this->moodleAPIService->getMoodleAvatar($url, $this->checkSsl);
		//return new DataResponse($content);
		$response = new DataDisplayResponse($content);
		$response->cacheFor(60*60*24);
		return $response;
	}

	/**
	 * get notification list
	 * @NoAdminRequired
	 *
	 * @param ?int $recentSince
	 * @return DataResponse
	 */
	public function getNotifications(?int $recentSince = null): DataResponse {
		if ($this->accessToken === '') {
			return new DataResponse(['error' => 'no-token'], 400);
		}
		$result = $this->moodleAPIService->getNotifications($this->moodleUrl, $this->accessToken, $this->checkSsl, $recentSince);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

}
