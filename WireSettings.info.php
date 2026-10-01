<?php
namespace ProcessWire;

/**
 * WireSettings Module Information
 *
 * @author Markus Thomas
 * @license MIT
 */

$info = [
    'title' => 'WireSettings',
    'version' => 102,
    'summary' => 'Centralized settings management for ProcessWire. Eliminates frontend page tree clutter while providing a dedicated top-level admin interface and global $settings API variable in templates.',
    'author' => 'Markus Thomas',
    'icon' => 'sliders',
    'requires' => [
        'ProcessWire>=3.0.180',
        'PHP>=8.1',
    ],
    'permissions' => [
        'wire-settings' => 'Manage global site settings',
    ],
    'autoload' => true,
    'singular' => true,
];
