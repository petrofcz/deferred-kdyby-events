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
	 * @var IExceptionHandler|null
	 */
	protected $exceptionHandler;

	/**
	 * DeferredEventDispatcher constructor.
	 * @param \Kdyby\Events\EventManager $evm
	 * @param \Kdyby\Doctrine\EntityManager $em
	 */
	public function __construct(\Kdyby\Events\EventManager $evm, \Kdyby\Doctrine\EntityManager $em) {
		$this->evm = $evm;
		$this->em = $em;
		$this->exceptionHandler = null;
	}

	public function dispatchEvent(DeferredEvent $deferredEvent) {
		$this->em->beginTransaction();
		$this->em->lock($deferredEvent, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
		$this->em->refresh($deferredEvent);

		if($deferredEvent->getState() != DeferredEvent::STATE_WAITING) {
			$this->em->rollback();
			throw new InvalidEventStateException;
		} else {

			$exceptionThrown = null;

			try {
				$this->evm->dispatchEvent($deferredEvent->getEventName(), new \Kdyby\Events\EventArgsList([$deferredEvent]));

				$deferredEvent->markAsExecuted(DeferredEvent::STATE_DONE, new \DateTime());
				$this->em->persist($deferredEvent);
			} catch (\Exception $e) {
				$deferredEvent->markAsExecuted(DeferredEvent::STATE_FAILED, new \DateTime());
				$this->em->persist($deferredEvent);
				$exceptionThrown = $e;
			}

			$this->em->flush();
			$this->em->commit();

			if($exceptionThrown && $this->exceptionHandler !== null) {
				$this->exceptionHandler->handleException($exceptionThrown, $deferredEvent);
			}
		}
	}

	/**
	 * @param IExceptionHandler $exceptionHandler
	 */
	public function setExceptionHandler(IExceptionHandler $exceptionHandler) {
		$this->exceptionHandler = $exceptionHandler;
	}

}