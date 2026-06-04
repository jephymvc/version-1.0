<?php
namespace App\Core;

trait EventSubscriber
{
    /**
     * Subscribe to events
     */
    public function subscribe()
    {
        if (property_exists($this, 'subscribedEvents')) {
            foreach ($this->subscribedEvents as $event => $listener) {
                if (is_array($listener)) {
                    list($method, $priority) = $listener;
                    Event::on($event, [$this, $method], $priority);
                } else {
                    Event::on($event, [$this, $listener]);
                }
            }
        }
    }
    
    /**
     * Unsubscribe from events
     */
    public function unsubscribe()
    {
        if (property_exists($this, 'subscribedEvents')) {
            foreach ($this->subscribedEvents as $event => $listener) {
                if (is_array($listener)) {
                    $method = $listener[0];
                } else {
                    $method = $listener;
                }
                Event::off($event, [$this, $method]);
            }
        }
    }
}
