<?php

namespace lsst\nextbuilds\events;

use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Entry;
use yii\base\Event;

class StatusChangeEvent extends Event
{
    /**
     * @var ElementInterface|null The element model associated with the event.
     */
    public $element;

    /**
     * @var string Previous status
     */
    public $statusBeforeSave = '';
    public $previousStatus = "";

    public function __construct($config = []) {
        parent::__construct($config);
    }

    // Public Methods
    // =========================================================================

    /**
     * @param string $nameOfStatus
     *
     * @return bool
     */
    public function changedTo(string $nameOfStatus): bool
    {
        return ($this->element->getStatus() === $nameOfStatus);
    }

    /**
     * @return bool
     */
    public function changedToPublished(): bool
    {
        return in_array($this->element->getStatus(), [Entry::STATUS_LIVE, Element::STATUS_ENABLED]);
    }

    /**
     * @return bool
     */
    public function changedToUnpublished(): bool
    {
        return !$this->changedToPublished();
    }

    /**
     * @return ElementInterface|null
     */
    public function getElement()
    {
        return $this->element;
    }
}
