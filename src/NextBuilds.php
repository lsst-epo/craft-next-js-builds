<?php
/**
 * Next Builds plugin for Craft CMS 3.x
 *
 * Start Next.js page builds from Craft.
 *
 */

namespace lsst\nextbuilds;

use lsst\nextbuilds\services\Request as RequestService;
use lsst\nextbuilds\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\MoveElementEvent;
use craft\events\ModelEvent;
use craft\helpers\ElementHelper;
use craft\services\Plugins;
use craft\events\PluginEvent;
use lsst\nextbuilds\services\Request as NextRequestService;
use craft\services\Structures;
use yii\base\Event;
use benf\neo\elements\Block;

/**
 * Class NextBuilds
 *
 * @author    Cast Iron Coding
 * @package   NextBuilds
 * @since     1.0.0
 *
 * @property  RequestService $request
 */
class NextBuilds extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var NextBuilds
     */
    public static $plugin;

	/**
	 * @var Settings
	 */
	public static $settings;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool
     */
    public bool $hasCpSection = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

	    $this->setComponents([
		    'request' => NextRequestService::class
	    ]);

        Craft::info(
            Craft::t(
                'next-builds',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );

	    // Event Listeners
	    Event::on(
		    Entry::class,
		    Entry::EVENT_AFTER_SAVE,
		    function (ModelEvent $event) {
			    $entry = $event->sender;

                if (
                    $this->settings->activeSections[$entry->section->handle] &&
                    !ElementHelper::isDraftOrRevision($entry) &&
                    !($entry->duplicateOf && $entry->getIsCanonical() && !$entry->updatingFromDerivative) &&
                    !ElementHelper::rootElement($entry)->isProvisionalDraft &&
                    !$entry->resaving
                ) {
                    $revalidateMenu = ($entry->type->handle == "pages");
                    Craft::$app->onAfterRequest(function() use ($entry, $revalidateMenu) {
                        $this->request->buildPagesFromEntry($entry, $revalidateMenu);
                    });
                }
		    }
	    );

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_DELETE,
            function (Event $event) {
                $entry = $event->sender;
                if (
                    $this->settings->activeSections[$entry->section->handle] &&
                    !ElementHelper::isDraftOrRevision($entry) &&
                    !($entry->duplicateOf && $entry->getIsCanonical() && !$entry->updatingFromDerivative) &&
                    !ElementHelper::rootElement($entry)->isProvisionalDraft
                ) {
                    $revalidateMenu = ($entry->type->handle == "pages");
                    Craft::$app->onAfterRequest(function() use ($entry, $revalidateMenu) {
                        $this->request->buildPagesFromEntry($entry, $revalidateMenu);
                    });
                }
            }
        );

        Event::on(
            Structures::class,
            Structures::EVENT_AFTER_INSERT_ELEMENT,
            function (MoveElementEvent $event) {
                $entry = $event->element;
                $handle = null;

                if($entry instanceof Block) {
                    if (($owner = $entry->getOwner()) !== null) {
                        $handle = $owner->section->handle;
                        $entry = $entry->getOwner();
                    }
                } else if(property_exists($entry, "handle")) {
                    $handle = $entry->handle;
                }

                if (
                    $handle != null &&
                    $this->settings->activeSections[$handle] &&
                    !ElementHelper::isDraftOrRevision($entry) &&
                    !($entry->duplicateOf && $entry->getIsCanonical() && !$entry->updatingFromDerivative) &&
                    !ElementHelper::rootElement($entry)->isProvisionalDraft
                ) {
                    $revalidateMenu = ($handle == "pages");
                    Craft::$app->onAfterRequest(function() use ($entry, $revalidateMenu) {
                        $this->request->buildPagesFromEntry($entry, $revalidateMenu);
                    });
                }
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_RESTORE,
            function (Event $event) {
                $entry = $event->sender;
                if (
                    $this->settings->activeSections[$entry->section->handle] &&
                    !ElementHelper::isDraftOrRevision($entry) &&
                    !($entry->duplicateOf && $entry->getIsCanonical() && !$entry->updatingFromDerivative) &&
                    !ElementHelper::rootElement($entry)->isProvisionalDraft
                ) {
                    $revalidateMenu = ($entry->type->handle == "pages");
                    Craft::$app->onAfterRequest(function() use ($entry, $revalidateMenu) {
                        $this->request->buildPagesFromEntry($entry, $revalidateMenu);
                    });
                }
            }
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'next-builds/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
