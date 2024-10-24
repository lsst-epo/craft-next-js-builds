<?php

namespace lsst\nextbuilds\services;

use yii\base\Component;
use craft\base\Element;
use craft\events\ElementEvent;
use lsst\nextbuilds\behaviors\ElementStatusBehavior;

class ElementStatusEvents extends Component
{
    const EVENT_STATUS_CHANGED = 'statusChanged';

    /**
     * Register event listener
     *
     * @param ElementEvent $event
     */
    public static function rememberPreviousStatus(ElementEvent $event)
    {
        /** @var Element|ElementStatusBehavior $element */
        $element = $event->element;
        $element->attachBehavior('elementStatusEvents', ElementStatusBehavior::class);

        if ($event->isNew) {
            return;
        }

        $element->rememberPreviousStatus();
    }

    /**
     * Register event listener
     *
     * @param ElementEvent $event
     */
    public static function fireEventOnChange(ElementEvent $event)
    {
        /** @var Element|ElementStatusBehavior $element */
        $element = $event->element;
        if ($element->getBehavior('elementStatusEvents') !== null) {
            $element->fireEventOnChange();
        }
    }

}
