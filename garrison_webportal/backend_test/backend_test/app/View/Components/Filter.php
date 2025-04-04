<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Filter extends Component
{
    public $route;
    public $hasNameFilter;
    public $hasDepartmentFilter;
    public $hasDateFilter;
    public $nameLabel;
    public $namePlaceholder;
    public $departments;
    public $columns;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        $route,
        $hasNameFilter = true,
        $hasDepartmentFilter = false,
        $hasDateFilter = false,
        $nameLabel = 'Name',
        $namePlaceholder = 'Search by name',
        $departments = ['HR', 'Finance', 'IT', 'Sales', 'Marketing', 'Operations'],
        $columns = 4
    ) {
        $this->route = $route;
        $this->hasNameFilter = $hasNameFilter;
        $this->hasDepartmentFilter = $hasDepartmentFilter;
        $this->hasDateFilter = $hasDateFilter;
        $this->nameLabel = $nameLabel;
        $this->namePlaceholder = $namePlaceholder;
        $this->departments = $departments;
        $this->columns = $columns;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.filter');
    }
}
