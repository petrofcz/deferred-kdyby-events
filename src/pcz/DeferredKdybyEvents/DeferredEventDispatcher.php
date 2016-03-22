<?php

namespace pcz\DeferredKdybyEvents;


class DeferredEventDispatcher {

	/**
	 * @var \Kdyby\Events\EventManager
	 */
	protected $evm;

	/**
	 * @var \Kdyby\Doctrine\EntityManager
	 */
	protected $em;

	/**
	 * DeferredEventDispatcher constructor.
	 * @param \Kdyby\Events\EventManager $evm
	 * @param \Kdyby\Doctrine\EntityManager $em
	 */
	public function __construct(\Kdyby\Events\EventManager $evm, \Kdyby\Doctrine\EntityManager $em) {
		$this->evm = $evm;
		$this->em = $em;
	}

	public function dispatchEvent(DeferredEvent $deferredEvent) {
		$this->em->beginTransaction();
		$this->em->lock($deferredEvent, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
		$this->em->refresh($deferredEvent);

		if($deferredEvent->getState() != DeferredEvent::STATE_WAITING) {
			$this->em->rollback();
			throw new InvalidEventStateException;
		} else {

			try {
				$this->evm->dispatchEvent($deferredEvent->getEventName(), new \Doctrine\Common\EventArgs([$deferredEvent]));

				$deferredEvent->markAsExecuted(DeferredEvent::STATE_DONE, new \DateTime());
				$this->em->persist($deferredEvent);
			} catch (\Exception $e) {
				$deferredEvent->markAsExecuted(DeferredEvent::STATE_FAILED, new \DateTime());
				$this->em->persist($deferredEvent);
			}

			$this->em->flush();
			$this->em->commit();
		}
	}

}