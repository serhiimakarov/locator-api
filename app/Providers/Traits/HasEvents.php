<?php

namespace App\Providers\Traits;

use Illuminate\Support\Facades\Event;

trait HasEvents
{
    /**
     * Listeners
     * @var array
     */
    protected $listeners = [];

    /**
     * Register event listeners.
     *
     */
    protected function registerListeners()
    {
        foreach ($this->listeners as $event => $listeners) {
            if (is_array($listeners)) {
                foreach ($listeners as $listener) {
                    Event::listen($event, $listener);
                }
            } else {
                Event::listen($event, $listeners);
            }
        }

        foreach ($this->subscribers as $subscriber) {
            Event::subscribe($subscriber);
        }

        return $this;
    }
}
