<?php

namespace lsst\nextbuilds\behaviors;

use Craft;
use craft\base\Element;
use lsst\nextbuilds\services\ElementStatusEvents;
use lsst\nextbuilds\events\StatusChangeEvent;
use lsst\nextbuilds\commands\ScheduledElements;
use yii\base\Behavior;
use yii\base\Event;
use yii\caching\CacheInterface;

/**
 *
 */
class ElementStatusBehavior extends Behavior
{

    /**
     * @var string
     */
    public $statusBeforeSave = '';
    public $previousStatus = "";

    /**
     * Saves the status of an element before it is saved
     */
    public function rememberPreviousStatus()
    {
        /** @var Element $element */
        $element = $this->owner;

        $originalElement = Craft::$app->getElements()->getElementById(
            $element->id,
            get_class($element),
            $element->siteId
        );

        $this->statusBeforeSave = $originalElement === null ?: $originalElement->getStatus();
    }

    /**
     * Triggers an event if the status has changed
     */
    public function fireEventOnChange()
    {
        /** @var Element $element */
        $element = $this->owner;

        if ($this->statusBeforeSave === $element->getStatus()) {
            return;
        }

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            Craft::$container->set(CacheInterface::class, Craft::$app->getCache());
            Craft::$app->controllerMap['element-status-events'] = ScheduledElements::class;
        }

        if (Event::hasHandlers(ElementStatusEvents::class, ElementStatusEvents::EVENT_STATUS_CHANGED)) {
            Event::trigger(
                ElementStatusEvents::class,
                ElementStatusEvents::EVENT_STATUS_CHANGED,
                new StatusChangeEvent([
                    'element' => $element,
                    'statusBeforeSave' => $this->statusBeforeSave
                ])
            );
        }
    }
}
