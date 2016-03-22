<?php

namespace pcz\DeferredKdybyEvents;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\EntityListeners({"GroupingListener"})
 * @ORM\HasLifecycleCallbacks
 */
abstract class GroupedDeferredEvent extends DeferredEvent {

	const STATE_GROUPED = 'grouped';

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected $group_key;

	/**
	 * @var GroupedDeferredEvent|null
	 * @ORM\ManyToOne(targetEntity="pcz\DeferredKdybyEvents\GroupedDeferredEvent")
	 */
	protected $successor;

	/**
	 * @internal
	 * @return string grouping key - all the events with this key will be grouped and time will be set to the last one's.
	 */
	public abstract function getGroupKey();

	/**
	 * @ORM\PrePersist
	 * @internal
	 */
	public function updateGroupKey() {
		$this->group_key = $this->getGroupKey();
	}

}