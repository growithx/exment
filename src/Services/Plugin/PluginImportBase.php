<?php

/**
 * Execute Batch
 */
namespace Exceedone\Exment\Services\Plugin;

class PluginImportBase
{
    use PluginBase;

    protected $custom_table;
    
    protected $file;
    
    public function __construct($plugin, $custom_table, $file)
    {
        $this->plugin = $plugin;
        $this->custom_table = $custom_table;
        $this->file = $file;
    }

    public function execute()
    {
    }
}
