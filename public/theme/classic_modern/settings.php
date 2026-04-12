<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Brand colour
    $settings->add(new admin_setting_configcolourpicker(
        'theme_classic_modern/brandcolor',
        get_string('brandcolor', 'theme_classic_modern'),
        get_string('brandcolordesc', 'theme_classic_modern'),
        '#3B82F6'
    ));

    // Logo
    $settings->add(new admin_setting_configstoredfile(
        'theme_classic_modern/logo',
        get_string('logo', 'theme_classic_modern'),
        get_string('logodesc', 'theme_classic_modern'),
        'logo',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.svg', '.webp']]
    ));

    // Custom CSS
    $settings->add(new admin_setting_configtextarea(
        'theme_classic_modern/customcss',
        get_string('customcss', 'theme_classic_modern'),
        get_string('customcssdesc', 'theme_classic_modern'),
        '',
        PARAM_RAW
    ));
}
