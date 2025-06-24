<?php
/**
 * Next Builds plugin for Craft CMS 3.x
 *
 * Start Next.js page builds from Craft.
 *
 */

namespace lsst\nextbuilds;

use Exception;
use lsst\nextbuilds\services\Request as RequestService;
use lsst\nextbuilds\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\MoveElementEvent;
use craft\events\ModelEvent;
use craft\helpers\ElementHelper;
use craft\helpers\DateTimeHelper;
use craft\services\Plugins;
use craft\events\PluginEvent;
use lsst\nextbuilds\services\Request as NextRequestService;
use craft\services\Structures;
use lsst\nextbuilds\services\ElementStatusEvents;
use lsst\nextbuilds\events\StatusChangeEvent;
use yii\base\Event;
use benf\neo\elements\Block;

use yii\caching\CacheInterface;
use craft\console\Application as CraftConsoleApp;
use craft\helpers\App;
use lsst\nextbuilds\commands\ScheduledElements;
use craft\services\Elements;
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

    private string $homeUri = "__home__";

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
        $this->initializeElementStatusEvents();

        Event::on(
            ElementStatusEvents::class,
            ElementStatusEvents::EVENT_STATUS_CHANGED,
            function(StatusChangeEvent $event) {
                $newStatus   = $event->element->getStatus();
                $entry = $event->element;

                try {
                    if ($entry instanceof \craft\elements\Entry &&
                        !$entry->resaving &&
                        $this->settings->activeSections[$entry->section->handle] &&
                        !ElementHelper::isDraftOrRevision($entry) &&
                        !($entry->duplicateOf && $entry->getIsCanonical() && !$entry->updatingFromDerivative) &&
                        !ElementHelper::rootElement($entry)->isProvisionalDraft &&
                        $newStatus == Entry::STATUS_LIVE) {

                        if ($entry->type->handle == "callout") {
                            $this->request->buildPagesFromEntry($this->homeUri, false);
                        } else if ($entry->uri != null) {
                            $revalidateMenu = ($entry->type->handle == "pages");
                            $this->request->buildPagesFromEntry($entry->uri, $revalidateMenu);
                        }

                    }
                } catch (Exception $exception) {
                    Craft::error($exception->getMessage(), "REVALIDATE_STATUS");
                }
            }
        );

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

                try {
                    if (
                        $entry->postDate < DateTimeHelper::now() &&
                        $this->settings->activeSections[$entry->section->handle] &&
                        !ElementHelper::isDraftOrRevision($entry) &&
                        !($entry->duplicateOf && $entry->getIsCanonical() && !$entry->updatingFromDerivative) &&
                        !ElementHelper::rootElement($entry)->isProvisionalDraft &&
                        !$entry->resaving &&
                        $entry->uri != null
                    ) {
                        $revalidateMenu = ($entry->type->handle == "pages");
                        Craft::$app->onAfterRequest(function() use ($entry, $revalidateMenu) {
                            $this->request->buildPagesFromEntry($entry->uri, $revalidateMenu);
                            try {
                                $projectId = App::env('GCP_PROJECT_ID');
                                $urlMap = App::env('CDN_URL_MAP');
                                $host = App::env('WEB_BASE_URL');
                                $path = '/*'; # $entry->uri;
                                $this->request->invalidateCDNCache($projectId, $urlMap, $path, $host);
                            } catch (\Throwable $th) {
                                Craft::error($th, "INVALIDATE_STATUS");
                            }
                            
                        });
                    }
                } catch(Exception $exception) {
                    Craft::error($exception->getMessage(), "REVALIDATE_STATUS");
                }

		    }
	    );

        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_DELETE,
            function (Event $event) {
                $entry = $event->sender;

                try {
                    if (
                        $this->settings->activeSections[$entry->section->handle] &&
                        !ElementHelper::isDraftOrRevision($entry) &&
                        !($entry->duplicateOf && $entry->getIsCanonical() && !$entry->updatingFromDerivative) &&
                        !ElementHelper::rootElement($entry)->isProvisionalDraft &&
                        $entry->uri != null
                    ) {
                        $revalidateMenu = ($entry->type->handle == "pages");
                        Craft::$app->onAfterRequest(function() use ($entry, $revalidateMenu) {
                            $this->request->buildPagesFromEntry($entry->uri, $revalidateMenu);
                        });
                    }
                } catch (Exception $exception) {
                    Craft::error($exception->getMessage(), "REVALIDATE_STATUS");
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
                    !ElementHelper::rootElement($entry)->isProvisionalDraft &&
                    $entry->uri != null
                ) {
                    $revalidateMenu = ($handle == "pages");
                    Craft::$app->onAfterRequest(function() use ($entry, $revalidateMenu) {
                        $this->request->buildPagesFromEntry($entry->uri, $revalidateMenu);
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
                    !ElementHelper::rootElement($entry)->isProvisionalDraft &&
                    $entry->uri != null
                ) {
                    $revalidateMenu = ($entry->type->handle == "pages");
                    Craft::$app->onAfterRequest(function() use ($entry, $revalidateMenu) {
                        $this->request->buildPagesFromEntry($entry->uri, $revalidateMenu);
                    });
                }
            }
        );
    }

    /**
     * @return void
     */
    protected function initializeElementStatusEvents(): void
    {
        Event::on(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT, [ElementStatusEvents::class, 'rememberPreviousStatus']);
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, [ElementStatusEvents::class, 'fireEventOnChange']);

        if (Craft::$app instanceof CraftConsoleApp) {
            Craft::$container->set(CacheInterface::class, Craft::$app->getCache());
            Craft::$app->controllerMap['element-status-events'] = ScheduledElements::class;
        }
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
