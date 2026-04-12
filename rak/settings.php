<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Theme RAK - Admin settings.
 *
 * @package   theme_rak
 * @copyright 2024 RAK Theme
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings = new theme_boost_admin_settingspage_tabs('themesettingrak', get_string('configtitle', 'theme_rak'));

    // ================================================================== //
    //  TAB 1 — General                                                    //
    // ================================================================== //
    $page = new admin_settingpage('theme_rak_general', get_string('generalsettings', 'theme_rak'));

    // ----- Preset selector (inherits Boost presets) ----- //
    $name        = 'theme_rak/preset';
    $title       = get_string('preset', 'theme_rak');
    $description = get_string('preset_desc', 'theme_rak');
    $default     = 'default.scss';
    $choices     = [
        'default.scss' => 'Default',
        'plain.scss'   => 'Plain',
    ];
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // ----- Logo upload ----- //
    $name        = 'theme_rak/logo';
    $title       = get_string('logo', 'theme_rak');
    $description = get_string('logo_desc', 'theme_rak');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // ----- Background image ----- //
    $name        = 'theme_rak/backgroundimage';
    $title       = get_string('backgroundimage', 'theme_rak');
    $description = get_string('backgroundimage_desc', 'theme_rak');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'backgroundimage');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    // ================================================================== //
    //  TAB 2 — Colours                                                    //
    // ================================================================== //
    $page = new admin_settingpage('theme_rak_colours', get_string('coloursettings', 'theme_rak'));

    // Primary colour.
    $name        = 'theme_rak/primarycolor';
    $title       = get_string('primarycolor', 'theme_rak');
    $description = get_string('primarycolor_desc', 'theme_rak');
    $default     = '#6C63FF';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Secondary colour.
    $name        = 'theme_rak/secondarycolor';
    $title       = get_string('secondarycolor', 'theme_rak');
    $description = get_string('secondarycolor_desc', 'theme_rak');
    $default     = '#48CAE4';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);

    // ================================================================== //
    //  TAB 3 — Advanced                                                   //
    // ================================================================== //
    $page = new admin_settingpage('theme_rak_advanced', get_string('advancedsettings', 'theme_rak'));

    // Raw SCSS.
    $name        = 'theme_rak/scsspre';
    $title       = get_string('rawscsspre', 'theme_rak');
    $description = get_string('rawscsspre_desc', 'theme_rak');
    $setting = new admin_setting_configtextarea($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name        = 'theme_rak/scss';
    $title       = get_string('rawscss', 'theme_rak');
    $description = get_string('rawscss_desc', 'theme_rak');
    $setting = new admin_setting_configtextarea($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Custom CSS.
    $name        = 'theme_rak/customcss';
    $title       = get_string('customcss', 'theme_rak');
    $description = get_string('customcss_desc', 'theme_rak');
    $setting = new admin_setting_configtextarea($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
