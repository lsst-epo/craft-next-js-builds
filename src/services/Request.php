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

use Google\Cloud\Compute\V1\{CacheInvalidationRule, InvalidateCacheUrlMapRequest, Client\UrlMapsClient};

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

	public function invalidateCDNCache(string $projectId, string $urlMapName, string $path = '/*', string|null $host = null): bool
	{
		try {
			$urlMapsClient = new UrlMapsClient();
	
			$invalidateCacheRule = new CacheInvalidationRule();
			$invalidateCacheRule->setPath($path);

			if ($host != null) {
				# strip scheme from $host if it exists (like http:// or https://)
				$pos = strpos($host, '//');
				if ($pos !== false) {
					$host = substr($host, $pos + 2);
				}
				$invalidateCacheRule->setHost($host);
			}
	
			$invalidateCacheRequest = new InvalidateCacheUrlMapRequest([
				'project' => $projectId,
				'url_map' => $urlMapName,
				'cache_invalidation_rule_resource' => $invalidateCacheRule
			]);
		} catch (\Throwable $th) {
			Craft::error("UrlMapsClient error: " . $th->getMessage() . "\n" . $th->getTraceAsString(), "INVALIDATE_STATUS");
			return false;
		}
		
	
		try {
			$operation = $urlMapsClient->invalidateCache($invalidateCacheRequest);
	
			$operation->pollUntilComplete(['totalPollTimeoutMillis' => 30*1000]);

			Craft::warning("project_id: {$projectId}, url_map: {$urlMapName}, host: {$host}, path: {$path}", "INVALIDATE_STATUS");
	
			if ($operation->operationSucceeded()) {
				Craft::warning("Cache invalidation success for URL MAP " . $urlMapName . " and path " . $path, "INVALIDATE_STATUS");
			}
			elseif ($operation->getError()) {
				Craft::warning("Cache invalidation failed. Message: " . $operation->getError()->getMessage() . " Details: " . $operation->getError()->getDetails(), "INVALIDATE_STATUS");
			}

			$result = $operation->getResult();
			Craft::warning("Invalidation result: " . print_r($result), "INVALIDATE_STATUS");
		} catch (\Throwable $th) {
			Craft::error("CDN Invalidation error: " . $th->getMessage() . "-" . $th->getTraceAsString(), "INVALIDATE_STATUS");
		} finally {
			$urlMapsClient->close();
		}

		return true;
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
