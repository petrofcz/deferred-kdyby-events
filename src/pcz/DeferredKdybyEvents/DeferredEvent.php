<?php

namespace pcz\DeferredKdybyEvents;

use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\Entities\Attributes\Identifier;

/**
 * @ORM\Entity
 * @ORM\Table(name="abstract_deferred_event")
 * @ORM\InheritanceType("SINGLE_TABLE")
 */
class DeferredEvent {

	use Identifier;

	const STATE_WAITING = 'waiting';
	const STATE_DONE = 'done';
	const STATE_FAILED = 'failed';
	const STATE_CANCELLED = 'cancelled';

	/**
	 * @var
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	protected $execution_date;



}