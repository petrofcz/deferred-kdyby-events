<?php

namespace pcz\DeferredKdybyEvents\DI;

use Kdyby\Console\DI\ConsoleExtension;
use Kdyby\Doctrine\DI\IEntityProvider;
use Nette\DI\CompilerExtension;
use pcz\DeferredKdybyEvents\DeferredEventDispatcher;
use pcz\DeferredKdybyEvents\FireDeferredEventsCommand;
use pcz\DeferredKdybyEvents\GroupingListener;

class DeferredEventsExtension extends CompilerExtension implements IEntityProvider {

	protected $defaults = [];

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$builder->addDefinition($this->prefix('groupingListener'))
			->setClass(GroupingListener::class)
			->setAutowired(true);

		$eventDispatcher = $builder->addDefinition($this->prefix('eventDispatcher'))
			->setClass(DeferredEventDispatcher::class)
			->setAutowired(true);

		$builder->addDefinition($this->prefix('cronCommand'))
			->setClass(FireDeferredEventsCommand::class)
			->setInject(false)->setAutowired(false)
			->addTag(ConsoleExtension::TAG_COMMAND);
	}

	/**
	 * Returns associative array of Namespace => mapping definition
	 *
	 * @return array
	 */
	function getEntityMappings() {
		return ['pcz\DeferredKdybyEvents' => __DIR__ . DIRECTORY_SEPARATOR . '..'];
	}
}