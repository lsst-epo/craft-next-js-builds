<?php
/**
 * Next Builds plugin for Craft CMS 3.x
 *
 * Start Next.js page builds from Craft.
 */

namespace lsst\nextbuilds\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;

/**
 * @author    Cast Iron Coding
 * @package   NextBuilds
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $nextApiBaseUrl = null;

	/**
	 * @var null
	 */
	public $nextSecretToken = null;

	/**
	 * @var null
	 */
	public $activeSections = [];
    /**
	 * @var bool|null
	 */
    public $enableCDNCacheInvalidation = false;

    // Public Methods
    // =========================================================================

    public function getEnableCDNCacheInvalidation(): bool|null
    {
        return App::parseBooleanEnv('$ENABLE_CDN_CACHE_INVALIDATION');
    }

	/**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['nextApiBaseUrl', 'nextSecretToken'], 'required'],
	        [['activeSections', 'enableCDNCacheInvalidation'], 'default']
        ];
    }
}
