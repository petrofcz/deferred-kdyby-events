<?php

namespace pcz\DeferredKdybyEvents;

use Doctrine\ORM\Event\LifecycleEventArgs;
use pcz\DeferredKdybyEvents\Annotations\Keeping;

class GroupingListener {

	/**
	 * @var \Kdyby\Doctrine\EntityManager
	 */
	protected $em;

	/**
	 * @var \Doctrine\Common\Annotations\Reader
	 */
	protected $annotationsReader;

	/**
	 * IssueParticipationUpdater constructor.
	 * @param \Kdyby\Doctrine\EntityManager $em
	 */
	public function __construct(\Kdyby\Doctrine\EntityManager $em, \Doctrine\Common\Annotations\Reader $annotationsReader) {
		$this->em = $em;
		$this->annotationsReader = $annotationsReader;
	}

	public function postPersist(GroupedDeferredEvent $deferredEvent, LifecycleEventArgs $event) {
		if($this->isKeeping($deferredEvent))
			$this->groupKeeping($deferredEvent);
		else $this->groupExtending($deferredEvent);
	}

	protected function isKeeping(GroupedDeferredEvent $deferredEvent){
		$eventReflection = new \ReflectionClass($deferredEvent);
		if ($this->annotationsReader->getClassAnnotation($eventReflection, Keeping::class)) {
			return true;
		} else return false;
	}

	protected function groupExtending(GroupedDeferredEvent $deferredEvent) {
		$whereParams = [
				'old_state' =>  GroupedDeferredEvent::STATE_WAITING,
				'group_key' =>  $deferredEvent->getGroupKey(),
				'new_event' =>  $deferredEvent->getId()
		];
		$whereString = 'g.group_key = :group_key AND g.state = :old_state AND g.id != :new_event';

		$query = $this->em->createQuery(
				'UPDATE ' . GroupedDeferredEvent::class . ' g SET g.successor = :successor
				WHERE ' . $whereString
		)->setParameters(array_merge([
				'successor' =>  $deferredEvent->getId(),
		], $whereParams));
		$query->execute();

		$query = $this->em->createQuery(
				'UPDATE ' . GroupedDeferredEvent::class . ' g SET g.state = :new_state
				WHERE ' . $whereString
		)->setParameters(array_merge([
			'new_state' =>  GroupedDeferredEvent::STATE_GROUPED,
		], $whereParams));
		$query->execute();
	}

	protected function groupKeeping(GroupedDeferredEvent $deferredEvent) {
		$this->em->createQuery('UPDATE ' . GroupedDeferredEvent::class . ' g SET g.state = :new_state
			WHERE g.state = :old_state AND g.group_key = :group_key')
		->execute([
			'old_state' =>  GroupedDeferredEvent::STATE_WAITING,
			'new_state' =>  GroupedDeferredEvent::STATE_GROUPED,
			'group_key' =>  $deferredEvent->getGroupKey()
		]);

		$query = $this->em->createQuery(
				'SELECT g FROM ' . GroupedDeferredEvent::class . ' g
				WHERE g.group_key = :group_key AND g.state = :old_state ORDER BY g.execution_date ASC'
		)->setParameters([
				'old_state' =>  GroupedDeferredEvent::STATE_GROUPED,
				'group_key' =>  $deferredEvent->getGroupKey(),
		]);
		foreach($query->setMaxResults(1)->execute() as $rec) {
			$this->em->createQuery(
				'UPDATE ' . GroupedDeferredEvent::class . ' g SET g.state = :new_state WHERE g.id = :id'
			)->execute([
				'new_state' =>  GroupedDeferredEvent::STATE_WAITING,
				'id'        =>  $rec->getId()
			]);
		}

		$query = $this->em->createQuery(
				'UPDATE ' . GroupedDeferredEvent::class . ' g SET g.successor = :successor
				WHERE g.group_key = :group_key AND g.successor IS NULL AND g != :successor'
		)->setParameters([
			'successor' =>  $deferredEvent,
			'group_key' =>  $deferredEvent->getGroupKey()
		]);
		$query->execute();
	}

}