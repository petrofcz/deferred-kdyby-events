<?php

namespace pcz\DeferredKdybyEvents;

/**
 * Interface representing a handler of exceptions thrown by event subscribers of deferred events.
 */
interface IExceptionHandler {

	/**
	 * This method is called when some subscriber of deferred event throws an exception.
	 * When some attribute(s) of the event object are changed, persist & flush sequence must be called too!
	 * @param $exception \Exception
	 * @param $event DeferredEvent
	 * @return mixed
	 */
	public function handleException(\Exception $exception, $event);

}