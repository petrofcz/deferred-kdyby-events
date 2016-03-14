<?php

namespace pcz\DeferredKdybyEvents;

use Doctrine\ORM\Event\LifecycleEventArgs;

class GroupingListener {

	/**
	 * @var \Kdyby\Doctrine\EntityManager
	 */
	protected $em;

	/**
	 * IssueParticipationUpdater constructor.
	 * @param \Kdyby\Doctrine\EntityManager $em
	 */
	public function __construct(\Kdyby\Doctrine\EntityManager $em) { $this->em = $em; }

	public function postPersist(GroupedDeferredEvent $deferredEvent, LifecycleEventArgs $event) {
		$query = $this->em->createQuery(
			'UPDATE ' . GroupedDeferredEvent::class . ' g SET g.state = :new_state, g.successor = :successor
				WHERE g.group_key = :group_key AND g.state = :old_state'
		)->setParameters([
			'new_state' =>  GroupedDeferredEvent::STATE_GROUPED,
			'old_state' =>  GroupedDeferredEvent::STATE_WAITING,
			'successor' =>  $deferredEvent,
			'group_key' =>  $deferredEvent->getGroupKey()
		]);
		$query->execute();
	}

}