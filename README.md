pcz/deferred-kdyby-events
======
deferred events extension for Nette framework (using [kdyby/events](https://packagist.org/packages/kdyby/events) & [kdyby/doctrine](https://packagist.org/packages/kdyby/doctrine))
*aneb ~~kdyby~~ až bude Bavorov...*


This extension of kdyby/events event system provides support of deferred events in your appliaction.
Two following basic types of deferred events are implemented:

- simple deferred event - the event is persisted & set up to be fired at some particular time in future
- grouped deferred event - each event has a *groupID* property. When a new event is persisted, all waiting events with its *groupID*
	are cancelled. You can use them for example for sending notifications - when user receives 5 messages in 10 minutes, only 1 notification
	will be sent using this type of event. Also, there's a class annotation *@Keeping*, that changes the behaviour of grouping
	strategy - the nearest event will be kept, and all the following will be cancelled, so the final waiting period will not be extended.

Requirements
------------

- PHP 5.4 or higher.
- [Nette Framework](https://github.com/nette/nette)
- [kdyby/doctrine](https://packagist.org/packages/kdyby/doctrine)
- [kdyby/events](https://packagist.org/packages/kdyby/events)
- [kdyby/console](https://packagist.org/packages/kdyby/console)


Installation
-----------

* install this extension using [Composer](http://getcomposer.org/):

```sh
$ composer require pcz/deferred-kdyby-events
```

* enable the extension in your config neon file

```yml
extensions:
	# add this line
	deferredEvents: pcz\DeferredKdybyEvents\DI\DeferredEventsExtension 
```

* configuration of other extesions is also required, see documentation of following dependencies: [Kdyby/Doctrine](https://github.com/Kdyby/Doctrine/blob/master/docs/en/index.md), [Kdyby/Events](https://github.com/Kdyby/Events/blob/master/docs/en/index.md) and [Kdyby/Console](https://github.com/Kdyby/Console/blob/master/docs/en/index.md).

* **update the database schema** after enabling the extension in config (be sure to clear cache and run the `orm:schema-tool:update` command)

* almost there, let's setup a cron job:
```
*/5 * * * * /path/to/php /path/to/your/app/www/index.php deferredEvents:fire
```
	
Introduction
--------
Each event is represented by its event class. It must extend one of the following base classes: `pcz\DeferredKdybyEvents\DeferredEvent` or `pcz\DeferredKdybyEvents\GroupedDeferredEvent`.
In fact, each event class is an **ORM Entity**, so don't forget to use **ORM Annotations** on class attributes. Usage of MTI (multi-table-inheritance) inheritance type causes that **each event is represented by 1 table** in database schema. 
It's also necessary to **update database schema after adding new event class**.

Examples
--------
Let's start with the simple deferred event type, the following example shows implementation of planned change of product price:

**The event class**
```php
use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity */
class ChangeProductPriceEvent extends \pcz\DeferredKdybyEvents\DeferredEvent {

	const EVENT_NAME = 'ChangeProductPrice';

	/**
	 * @var Product
	 * @ORM\ManyToOne(targetEntity="Product")
	 */
	protected $product;

	/**
	 * @var integer
	 * @ORM\Column(type="integer")
	 */
	protected $new_price;

	public function __construct(\DateTime $execution_date, Product $product, $new_price) {
		parent::__construct($execution_date);
		$this->product = $product;
		$this->new_price = $new_price;
	}

	public function getEventName() {
		return self::EVENT_NAME;
	}

	public function getProduct() { return $this->product; }

	public function getNewPrice() { return $this->new_price; }

}
```

**Event creation**
```php
/** @var $em Kdyby\Doctrine\EntityManager */

/** @var $product Product */

$event = new ChangeProductPriceEvent(
	new \DateTime('2016-04-01 00:00:00'),
	$product,
	10
);

$em->persist($event);
$em->flush();
```

**Event handling**
```php
class ChangeProductEventListener implements Kdyby\Events\Subscriber {

	// this listener must be registered in config, with 'kdyby.subscriber' tag of course

	/** @var Kdyby\Doctrine\EntityManager */
	protected $em;

	public function getSubscribedEvents() {
		return [ChangeProductPriceEvent::EVENT_NAME     =>  'changePrice'];
	}

	public function changePrice(ChangeProductPriceEvent $e) {
		// don't use this in production, some locking should be implemented
		$product = $e->getProduct();
		$product->setPrice($e->getNewPrice());
		$this->em->persist($product)->flush();
	}

}
```

.. that's it:-) Now, let's see some more interesting scenario. An internal message system is part of your awesome app
and you want to send a notification when user receives a message. But you don't want to spam user's mailbox, so the notification
will be delayed. If another message is received during the delay period, user will still receive the single notification.

```php
/** @pcz\DeferredKdybyEvents\Annotations\Keeping */
class NewMessageNotificationEvent extends pcz\DeferredKdybyEvents\GroupedDeferredEvent {
	// ...

	protected $user;

	public function __construct(\DateTime $execution_date, User $user) {
		parent::__construct($execution_date);
		$this->user = $user;
	}

	public function getGroupKey() {
		return $this->user->getId();
	}
	
	// ...
}
```

Note the `getGroupKey()` method, it must be implemented when overriding the `GroupedDeferredEvent` class. It's necessary because it
defines the grouping key. 
Also the `@Keeping` annotation is important, it will be discussed later. Another example is removing inactive chat member from a chat room.
Notice the lack of the `@Keeping` annotation and the `getGroupKey()` method.

```php
class RemoveUserFromRoomEvent extends pcz\DeferredKdybyEvents\GroupedDeferredEvent {
	// ...

	public function getGroupKey() {
		return $this->user->getId() . '_' . $this->room->getId();
	}

	// ...
}
```
New event then would be created every time user sends a chat message. If the delay period would be for example 48 hours (eg. call like
`$event = new RemoveUserFromRoomEvent((new \DateTime())->add(new \DateInterval('PT48H')), ...)` would be used), user would be removed
from a chat room that hasn't participated to for 48 hours. When new event is persisted, all other waiting events with the same `groupKey` 
will be suspended.

But it might not be the proper behavior for the first case (notification example). If someone send me a message every 5 minutes, i'll never
receive a notification, because the created event will always "overwrite" the last waiting one. In such cases, use the `@Keeping` annotation. It
changes the strategy of grouping - the closest event will be kept and all succeeding events will be suspended.

Thanks & enjoy
**& a big round of applause for [Kdyby extensions](https://github.com/Kdyby/)**