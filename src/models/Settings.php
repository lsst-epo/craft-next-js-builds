<?php
/**
 * Next Builds plugin for Craft CMS 3.x
 *
 * Start Next.js page builds from Craft.
 */

namespace lsst\nextbuilds\models;

use Craft;
use craft\base\Model;

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
	 * @var bool
	 */
	public $enableCDNCacheInvalidation = false; 

    // Public Methods
    // =========================================================================

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
