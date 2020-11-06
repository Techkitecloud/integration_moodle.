<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Moodle\Search;

use OCA\Moodle\Service\MoodleAPIService;
use OCA\Moodle\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class MoodleSearchCoursesProvider implements IProvider {

	/** @var IAppManager */
	private $appManager;

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/**
	 * CospendSearchProvider constructor.
	 *
	 * @param IAppManager $appManager
	 * @param IL10N $l10n
	 * @param IURLGenerator $urlGenerator
	 * @param MoodleAPIService $service
	 */
	public function __construct(IAppManager $appManager,
								IL10N $l10n,
								IConfig $config,
								IURLGenerator $urlGenerator,
								MoodleAPIService $service) {
		$this->appManager = $appManager;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->service = $service;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'moodle-search-courses';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Moodle courses');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer Moodle results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$offset = $query->getCursor();
		$offset = $offset ? intval($offset) : 0;

		$theme = $this->config->getUserValue($user->getUID(), 'accessibility', 'theme', '');
		$thumbnailUrl = ($theme === 'dark') ?
			$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg') :
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');

		$moodleUrl = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'url', '');
		$accessToken = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'token', '');
		$checkSsl = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'check_ssl', '1') === '1';
		$searchCoursesEnabled = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'search_courses_enabled', '0') === '1';
		if ($accessToken === '' || !$searchCoursesEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$searchResults = $this->service->searchCourses($moodleUrl, $accessToken, $checkSsl, $term, $offset, $limit);
		if ($searchResults['error'] || $searchResults['exception']) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$formattedResults = \array_map(function (array $entry) use ($thumbnailUrl, $moodleUrl): MoodleSearchResultEntry {
			return new MoodleSearchResultEntry(
				$thumbnailUrl,
				$this->getMainText($entry),
				$this->getSubline($entry),
				$this->getLinkToMoodle($entry, $moodleUrl),
				'',
				false
			);
		}, $searchResults);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getMainText(array $entry): string {
		return $entry['displayname'];
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getSubline(array $entry): string {
		return $this->l10n->t('Moodle course');
	}

	/**
	 * @param array $entry
	 * @param string $url
	 * @return string
	 */
	protected function getLinkToMoodle(array $entry, string $url): string {
		return $url . '/course/view.php?id=' . $entry['id'];
	}

}