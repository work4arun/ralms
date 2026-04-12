<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Theme RAK — Core renderer overrides.
 *
 * Overrides the course card rendering so every course card displays as a
 * Netflix-style card with a 16:9 hero image, no borders, and a soft glow
 * on hover. All visual polish is handled by CSS; PHP just structures the
 * correct HTML skeleton.
 *
 * @package   theme_rak
 * @copyright 2024 RAK Theme
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Core renderer override for theme_rak.
 *
 * Extends theme_boost's core renderer (which itself extends core_renderer)
 * so we inherit every method unless we explicitly override it.
 */
class theme_rak_core_renderer extends theme_boost\output\core_renderer {

    // ================================================================ //
    // AMD module initialisation                                         //
    // ================================================================ //

    /**
     * Render additional HTML at the end of <body>.
     * We hook our AMD theme_helper here so it runs on every page.
     *
     * @return string  HTML fragment.
     */
    public function standard_end_of_body_html(): string {
        $output = parent::standard_end_of_body_html();
        $output .= $this->require_rak_amd();
        return $output;
    }

    /**
     * Emit the require() call that bootstraps our AMD module.
     *
     * @return string  HTML <script> fragment.
     */
    protected function require_rak_amd(): string {
        $this->page->requires->js_call_amd('theme_rak/theme_helper', 'init', [[]]);
        return '';
    }

    // ================================================================ //
    // Course card rendering                                             //
    // ================================================================ //

    /**
     * Render a single course summary box (used on front page and in
     * the course overview block).
     *
     * We produce a "Netflix-style" card:
     *   • 16:9 aspect-ratio hero image (gradient fallback).
     *   • No visible border.
     *   • Soft outer glow on hover (CSS handles the glow).
     *   • Category badge, short description, enrolment button.
     *
     * @param core_course_list_element $course  The course to render.
     * @return string  HTML output.
     */
    public function coursecat_coursebox_content(core_course_list_element $course): string {

        $content = '';

        // ── Hero image (16:9 wrapper) ── //
        $content .= html_writer::start_tag('div', ['class' => 'card-img-wrapper']);

        $courseimage = $this->get_course_hero_image($course);
        if ($courseimage) {
            $content .= html_writer::empty_tag('img', [
                'src'     => $courseimage,
                'class'   => 'card-img-top',
                'alt'     => '',
                'loading' => 'lazy',
            ]);
        } else {
            // Gradient placeholder keeps the aspect-ratio intact.
            $content .= html_writer::tag('div', '', [
                'class' => 'card-img-placeholder d-flex align-items-center justify-content-center',
                'style' => 'width:100%;height:100%;background:linear-gradient(135deg,#6C63FF,#48CAE4);',
            ]);
        }

        $content .= html_writer::end_tag('div'); // .card-img-wrapper

        // ── Card body ── //
        $content .= html_writer::start_tag('div', ['class' => 'card-body']);

        // Category badge.
        if ($cat = coursecat::get($course->category, IGNORE_MISSING)) {
            $content .= html_writer::tag('span', format_string($cat->name), [
                'class' => 'badge bg-primary bg-opacity-10 text-primary mb-2',
            ]);
        }

        // Course name (link).
        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
        $content .= html_writer::tag(
            'h5',
            html_writer::link($courseurl, format_string($course->fullname), [
                'class' => 'text-reset text-decoration-none',
            ]),
            ['class' => 'card-title']
        );

        // Short description.
        $summary = format_text($course->summary, $course->summaryformat, ['noclean' => true, 'para' => false]);
        $summary = html_entity_decode(strip_tags($summary));
        if (core_text::strlen($summary) > 100) {
            $summary = core_text::substr($summary, 0, 97) . '…';
        }
        if ($summary) {
            $content .= html_writer::tag('p', $summary, ['class' => 'card-text text-muted small mb-3']);
        }

        // Enrol / View button.
        $content .= html_writer::link(
            $courseurl,
            get_string('viewcourse', 'theme_rak'),
            ['class' => 'btn btn-primary btn-sm fw-600']
        );

        $content .= html_writer::end_tag('div'); // .card-body

        return $content;
    }

    /**
     * Wrap a single course box in the RAK card shell.
     *
     * This method is called by the frontpage renderer and the course
     * catalogue renderer.  We output a <div class="card rak-course-card">
     * wrapper so our SCSS selectors (.card) apply correctly.
     *
     * @param coursecat_helper $chelper  Rendering helper.
     * @param core_course_list_element  $course  The course object.
     * @param string $additionalclasses  Extra CSS classes.
     * @return string  HTML.
     */
    public function coursecat_coursebox(
        coursecat_helper $chelper,
        $course,
        $additionalclasses = ''
    ): string {
        if (!isset($this->strings->summary)) {
            $this->strings->summary = get_string('summary');
        }

        // Ensure we have the full course object.
        if (is_int($course)) {
            $course = new core_course_list_element(get_course($course));
        }

        $classes = 'card rak-course-card h-100 ' . $additionalclasses;

        $content  = html_writer::start_tag('div', ['class' => $classes, 'data-courseid' => $course->id]);
        $content .= $this->coursecat_coursebox_content($course);
        $content .= html_writer::end_tag('div');

        return $content;
    }

    // ================================================================ //
    // Helper: get hero image URL for a course                          //
    // ================================================================ //

    /**
     * Return the URL of the first course overview image file, or null.
     *
     * @param core_course_list_element $course
     * @return moodle_url|null
     */
    protected function get_course_hero_image(core_course_list_element $course): ?moodle_url {
        foreach ($course->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            if ($isimage) {
                return moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                );
            }
        }
        return null;
    }

    // ================================================================ //
    // Logo helper                                                       //
    // ================================================================ //

    /**
     * Return the URL of the custom logo, or null if none is set.
     *
     * @return moodle_url|null
     */
    public function get_logo_url($maxwidth = null, $maxheight = 200): ?moodle_url {
        $theme = theme_config::load('rak');
        $logo  = $theme->setting_file_url('logo', 'logo');
        if (empty($logo)) {
            $parentlogo = parent::get_logo_url($maxwidth, $maxheight);
            return $parentlogo ?: null;
        }
        return new moodle_url($logo);
    }

    // ================================================================ //
    // Google Fonts injection via <head>                                 //
    // ================================================================ //

    /**
     * Add Inter font import to the page <head>.
     * Hooked from config.php via $THEME->addblockposition is not needed;
     * we use the mustache head_extra partial instead.  This method is
     * kept as a fallback for layouts that don't include head_extra.
     *
     * @return string  HTML fragment.
     */
    public function standard_head_html(): string {
        $output = parent::standard_head_html();
        $output .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
        $output .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        $output .= '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';
        return $output;
    }
}
