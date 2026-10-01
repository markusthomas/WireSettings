<?php
namespace ProcessWire;

/**
 * Configuration for WireSettings
 *
 * @author Markus Thomas
 * @license MIT
 */

$config = [
    'template_name' => [
        'type' => 'text',
        'label' => __('Settings Template Name'),
        'description' => __('The name of the ProcessWire template used for storing settings fields.'),
        'notes' => __('Default: "settings". Changing this requires updating the associated template.'),
        'value' => 'settings',
        'columnWidth' => 50,
        'required' => true,
    ],
    'api_var_name' => [
        'type' => 'text',
        'label' => __('API Variable Name'),
        'description' => __('The variable name exposed globally in frontend templates (e.g. $settings->company).'),
        'notes' => __('Default: "settings". Also accessible via wire("settings") and $wire->settings.'),
        'value' => 'settings',
        'columnWidth' => 50,
        'required' => true,
    ],
    'admin_title' => [
        'type' => 'text',
        'label' => __('Admin Menu Title'),
        'description' => __('The label displayed in the ProcessWire admin navigation bar.'),
        'value' => 'Settings',
        'columnWidth' => 50,
    ],
    'admin_icon' => [
        'type' => 'text',
        'label' => __('Admin Menu Icon'),
        'description' => __('Icon name (e.g. "sliders", "cog", "wrench").'),
        'value' => 'sliders',
        'columnWidth' => 50,
    ],
    'hide_system_tabs' => [
        'type' => 'checkbox',
        'label' => __('Hide System Tabs'),
        'description' => __('Hides the core "Settings" (template/parent/name), "Delete", and "Children" tabs in the editor so editors only see actual settings tabs.'),
        'value' => 1,
        'columnWidth' => 50,
    ],
    'show_template_shortcut' => [
        'type' => 'checkbox',
        'label' => __('Show Template Shortcut'),
        'description' => __('Display a quick link to "Setup > Templates > settings" for Superusers.'),
        'value' => 1,
        'columnWidth' => 50,
    ],
    'hide_from_page_tree' => [
        'type' => 'checkbox',
        'label' => __('Remove from Normal Page Tree'),
        'description' => __('Encapsulates the settings page inside the admin hierarchy (/admin/settings/data/), completely eliminating it from the normal page tree.'),
        'value' => 1,
        'columnWidth' => 50,
    ],
    'settings_page_id' => [
        'type' => 'integer',
        'label' => __('Settings Data Page ID'),
        'description' => __('ID of the page holding the settings field values (automatically detected).'),
        'value' => 0,
        'columnWidth' => 50,
    ],
];
