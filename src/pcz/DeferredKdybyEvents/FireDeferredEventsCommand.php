<?php

namespace pcz\DeferredKdybyEvents;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FireDeferredEventsCommand extends \Symfony\Component\Console\Command\Command {

	const MAX_RUNNING_TIME = 300;    // [s]

	const FETCHED_RECORDS_COUNT = 20;
	const IDLE_DELAY = 10;  // [s]

	/** @var  DeferredEventDispatcher */
	protected $dispatcher;

	/** @var  \Kdyby\Doctrine\EntityManager */
	protected $em;

	/**
	 * FireDeferredEventsCommand constructor.
	 * @param DeferredEventDispatcher $dispatcher
	 * @param \Kdyby\Doctrine\EntityManager $em
	 */
	public function __construct(DeferredEventDispatcher $dispatcher, \Kdyby\Doctrine\EntityManager $em) {
		$this->dispatcher = $dispatcher;
		$this->em = $em;
		parent::__construct();
	}

	protected function configure() {
		$this->setName('deferredEvents:fire')
			->setDescription(sprintf('Dispatches deferred events. Should be used by cron each %d minutes.', ceil(self::MAX_RUNNING_TIME/60)));
	}

	protected function execute(InputInterface $if, OutputInterface $of) {
		$time_start = time();
		$fnCheckTime = function() use ($time_start) {
			return (time() - $time_start) < (self::MAX_RUNNING_TIME - 5);
		};

		do {

			/** @var DeferredEvent[] $events */
			$events = $this->em->createQuery('SELECT e FROM ' . DeferredEvent::class . ' e
				WHERE e.state = :state AND e.execution_date <= :now ORDER BY e.id ASC')
					->setParameters(['state' => DeferredEvent::STATE_WAITING, 'now' => new \DateTime()])
					->setMaxResults(self::FETCHED_RECORDS_COUNT)->execute();

			if(!count($events)) {
				sleep(self::IDLE_DELAY);
			} else {
				foreach($events as $event) {
					if(!$fnCheckTime()) break 2;
					$this->dispatcher->dispatchEvent($event);
				}
			}

		} while ( $fnCheckTime() );
	}

}