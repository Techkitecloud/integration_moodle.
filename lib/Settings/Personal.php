<?php
namespace OCA\Moodle\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IURLGenerator;
use OCP\IInitialStateService;

use OCA\Moodle\AppInfo\Application;

class Personal implements ISettings {

    private $request;
    private $config;
    private $dataDirPath;
    private $urlGenerator;
    private $l;

    public function __construct(
                        string $appName,
                        IL10N $l,
                        IRequest $request,
                        IConfig $config,
                        IURLGenerator $urlGenerator,
                        IInitialStateService $initialStateService,
                        $userId) {
        $this->appName = $appName;
        $this->urlGenerator = $urlGenerator;
        $this->request = $request;
        $this->l = $l;
        $this->config = $config;
        $this->initialStateService = $initialStateService;
        $this->userId = $userId;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse {
        $token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token', '');
        $url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', '');
        $searchCoursesEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_courses_enabled', '0');
        $searchModulesEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_modules_enabled', '0');
        $searchUpcomingEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_upcoming_enabled', '0');
        $userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name', '');
        $checkSsl = $this->config->getUserValue($this->userId, Application::APP_ID, 'check_ssl', '1') === '1';

        $userConfig = [
            'token' => $token,
            'url' => $url,
            'search_courses_enabled' => ($searchCoursesEnabled === '1'),
            'search_modules_enabled' => ($searchModulesEnabled === '1'),
            'search_upcoming_enabled' => ($searchUpcomingEnabled === '1'),
            'user_name' => $userName,
            'check_ssl' => $checkSsl,
        ];
        $this->initialStateService->provideInitialState($this->appName, 'user-config', $userConfig);
        return new TemplateResponse(Application::APP_ID, 'personalSettings');
    }

    public function getSection(): string {
        return 'connected-accounts';
    }

    public function getPriority(): int {
        return 15;
    }
}
