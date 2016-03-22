<?php

namespace pcz\DeferredKdybyEvents;

use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\Entities\Attributes\Identifier;

/**
 * @ORM\Entity
 * @ORM\InheritanceType("JOINED")
 */
abstract class DeferredEvent {

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

	/**
	 * @var
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	protected $real_execution_date;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected $state = self::STATE_WAITING;

	/**
	 * DeferredEvent constructor.
	 * @param $execution_date
	 */
	public function __construct(\DateTime $execution_date = null) {
		$this->execution_date = $execution_date;
	}

	/**
	 * @return string
	 */
	public function getState() {
		return $this->state;
	}

	/**
	 * @return mixed
	 */
	public function getExecutionDate() {
		return $this->execution_date;
	}

	/**
	 * @return mixed
	 */
	public function getRealExecutionDate() {
		return $this->real_execution_date;
	}

	/** @internal */
	public function markAsExecuted($state, \DateTime $date) {
		$this->state = $state;
		$this->real_execution_date = $date;
	}

	public function cancel() {
		if($this->state != self::STATE_WAITING) throw new InvalidEventStateException;
		$this->state = self::STATE_CANCELLED;
	}

	/**
	 * @internal
	 * @return string Event name that will be fired. Single argument is passed - the event entity class
	 */
	public abstract function getEventName();

}