<?php
/**
 * Registrar todas las acciones y filtros del plugin
 */
class Reviews_Maps_Loader {
    /**
     * El array de acciones registradas con WordPress
     */
    protected $actions;

    /**
     * El array de filtros registrados con WordPress
     */
    protected $filters;

    /**
     * Inicializar las colecciones usadas para mantener las acciones y filtros
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Añadir una nueva acción al array de acciones a ser registradas con WordPress
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Añadir un nuevo filtro al array de filtros a ser registrados con WordPress
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Una función de utilidad que se usa para registrar las acciones y hooks en una colección
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Registrar los filtros y acciones con WordPress
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }
} 