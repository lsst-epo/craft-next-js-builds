<?php
/**
 * Next Builds plugin for Craft CMS 3.x
 *
 * Start Next.js page builds from Craft.
 */

namespace lsst\nextbuilds\services;

use Craft;
use lsst\nextbuilds\NextBuilds;
use craft\base\Component;
use GuzzleHttp\Client;

/**
 * @author    Cast Iron Coding
 * @package   NextBuilds
 * @since     1.0.0
 */
class Request extends Component
{
	const NEXT_ENDPOINT_REVALIDATE = '/revalidate';

    // Public Methods
    // =========================================================================

	/**
	 * @param String uri
	 * @return void
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function buildPagesFromEntry(string $uri, bool $revalidateMenu)
	{
		$settings = NextBuilds::getInstance()->getSettings();
		$client = new Client();

        $endpoint = $this->getSettingsData($settings->nextApiBaseUrl) . self::NEXT_ENDPOINT_REVALIDATE;
		$params = [
			'uri' => $uri,
			'secret' => $this->getSettingsData($settings->nextSecretToken)
		];
        if ($revalidateMenu) {
            $params["tags"] = ["navigation"];
        }
		$requestUrl = $endpoint . '?' . http_build_query($params);
        Craft::warning("Request URL: " .$requestUrl, "REVALIDATE_STATUS"); // warning severity so this gets logged in production

		try {
			$client->request('GET', $requestUrl, []);
			$isConsoleRequest = Craft::$app->getRequest()->getIsConsoleRequest();
			if (!$isConsoleRequest) {
				Craft::$app->session->setNotice('Requesting revalidation: ' . $uri);
			}
		} catch (\Exception $exception) {
            Craft::error($exception->getMessage(), "REVALIDATE_STATUS");
			$isConsoleRequest = Craft::$app->getRequest()->getIsConsoleRequest();
			if (!$isConsoleRequest) {
				Craft::$app->session->setError('Incremental rebuild failed. Frontend will update after next revalidation interval.');
			}
		}
	}

	// Protected Methods
	// =========================================================================

	/**
	 * @param string $setting
	 * @return string
	 */
	protected function getSettingsData(string $setting): string
	{
		if ($value = Craft::parseEnv($setting)) {
			return $value;
		}

		return $setting;
	}
}
