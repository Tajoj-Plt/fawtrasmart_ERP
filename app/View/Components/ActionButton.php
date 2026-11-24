<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ActionButton extends Component
{
    public $url;
    public $permission;
    public $text;
    public $icon;
    public $class;
    public $method;
    public $confirm;

    /**
     * Create a new component instance.
     *
     * @param string $url
     * @param string|null $permission
     * @param string $text
     * @param string $icon
     * @param string $class
     * @param string $method
     * @param string|null $confirm
     */
    public function __construct(
        $url,
        $permission = null,
        $text = '',
        $icon = '',
        $class = 'btn btn-primary',
        $method = 'GET',
        $confirm = null
    ) {
        $this->url = $url;
        $this->permission = $permission;
        $this->text = $text;
        $this->icon = $icon;
        $this->class = $class;
        $this->method = strtoupper($method);
        $this->confirm = $confirm;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        // Check permission if provided
        if ($this->permission && !hasPermission($this->permission)) {
            return '';
        }

        return view('components.action-button');
    }
}
