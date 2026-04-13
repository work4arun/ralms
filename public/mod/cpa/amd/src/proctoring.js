// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * proctoring.js — Anti-cheat enforcement module for mod_cpa.
 *
 * Enforces:
 *   • Tab/window visibility detection
 *   • Fullscreen enforcement (with overlay gate)
 *   • Clipboard lock (paste / copy / cut)
 *   • Right-click disable
 *   • DevTools keyboard shortcuts block
 *   • Print Screen detection
 *   • Idle timeout detection
 *   • Webcam prompt
 *   • ID verification prompt
 *   • Violation logging via AJAX (POST to submit.php)
 *   • Auto-redirect on threshold breach
 *
 * @module     mod_cpa/proctoring
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    // ── Module state ──────────────────────────────────────────────────────────
    let cfg = {};           // config passed from PHP
    let violationCount = 0;
    let submitUrl = '';
    let idleTimer = null;
    const IDLE_TIMEOUT = 5 * 60 * 1000; // 5 min idle → info log

    // ── Public API ────────────────────────────────────────────────────────────
    return {
        init(config) {
            cfg = config;
            submitUrl = cfg.wwwroot
                ? cfg.wwwroot + '/mod/cpa/submit.php'
                : document.location.origin + '/mod/cpa/submit.php';

            if (cfg.proctoringmode === 'none') {
                return; // nothing to enforce
            }

            _setupPrestart();
        }
    };

    // ── Pre-start (webcam / ID verification) ──────────────────────────────────
    function _setupPrestart() {
        const overlay = document.getElementById('cpa-prestart-overlay');
        const title   = document.getElementById('cpa-prestart-title');
        const confirm = document.getElementById('cpa-prestart-confirm');

        if (!overlay) { _setupProctoring(); return; }

        let steps = [];
        if (cfg.idverification) {
            steps.push({
                msg: 'Please confirm: I am ' + cfg.studentname + ' and I am taking this assessment independently.',
                type: 'id'
            });
        }
        if (cfg.webcamrequired) {
            steps.push({ msg: 'This assessment requests webcam access for identity verification.', type: 'webcam' });
        }

        if (steps.length === 0) {
            _setupProctoring();
            return;
        }

        let stepIdx = 0;

        function showStep() {
            if (stepIdx >= steps.length) {
                overlay.style.display = 'none';
                _setupProctoring();
                return;
            }
            const step = steps[stepIdx];
            title.textContent = step.msg;
            overlay.style.display = 'flex';
        }

        confirm.addEventListener('click', () => {
            const step = steps[stepIdx];
            if (step.type === 'webcam') {
                // Request webcam (advisory — don't block if denied).
                navigator.mediaDevices?.getUserMedia({ video: true })
                    .catch(() => { /* advisory — ignore */ });
            }
            stepIdx++;
            showStep();
        });

        showStep();
    }

    // ── Main proctoring setup ─────────────────────────────────────────────────
    function _setupProctoring() {
        if (cfg.fullscreenrequired) {
            _enforceFullscreen();
        }
        if (cfg.tabswitchdetect) {
            _setupTabSwitch();
        }
        if (cfg.disablepaste) {
            _setupClipboardLock();
        }
        if (cfg.disablerightclick) {
            _setupRightClickBlock();
        }
        if (cfg.blockdevtools) {
            _setupDevToolsBlock();
        }
        if (cfg.blockprintscreen) {
            _setupPrintScreenBlock();
        }
        _setupIdleDetection();
    }

    // ── Fullscreen enforcement ────────────────────────────────────────────────
    function _enforceFullscreen() {
        const gate = document.getElementById('cpa-fullscreen-gate');
        const btn  = document.getElementById('cpa-enter-fs-btn');

        function isFS() {
            return !!(document.fullscreenElement ||
                      document.webkitFullscreenElement ||
                      document.mozFullScreenElement);
        }

        function requestFS() {
            const el = document.documentElement;
            (el.requestFullscreen || el.webkitRequestFullscreen ||
             el.mozRequestFullScreen || el.msRequestFullscreen)?.call(el);
        }

        function checkFS() {
            if (!isFS() && gate) {
                gate.style.display = 'flex';
            } else if (gate) {
                gate.style.display = 'none';
            }
        }

        btn?.addEventListener('click', requestFS);

        // On fullscreen change — if they exit, log a violation and re-show gate.
        document.addEventListener('fullscreenchange',     onFSChange);
        document.addEventListener('webkitfullscreenchange', onFSChange);
        document.addEventListener('mozfullscreenchange',  onFSChange);

        function onFSChange() {
            if (!isFS()) {
                _logViolation('fullscreen_exit', 'warning', 'User exited fullscreen');
                _showToast(cfg.warningsonviolation
                    ? 'You have exited fullscreen. Please return immediately.'
                    : '');
                checkFS();
            }
        }

        // Check on load — if not already fullscreen, show gate.
        setTimeout(checkFS, 200);
    }

    // ── Tab / window switch detection ─────────────────────────────────────────
    function _setupTabSwitch() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                _logViolation('tabswitch', 'warning', 'document.hidden = true');
                _showToast('Tab or window switch detected and recorded.');
            }
        });

        // Blur on window (secondary signal).
        window.addEventListener('blur', () => {
            _logViolation('tabswitch', 'info', 'window blur event');
        });
    }

    // ── Clipboard lock ────────────────────────────────────────────────────────
    function _setupClipboardLock() {
        document.addEventListener('paste', (e) => {
            // Allow paste in Monaco editors (they handle their own clipboard).
            if (e.target?.closest?.('.cpa-monaco-editor')) return;
            e.preventDefault();
            _logViolation('paste', 'warning', 'Paste attempt blocked');
            _showToast('Pasting is not allowed in this assessment.');
        });
        document.addEventListener('copy',  (e) => {
            if (e.target?.closest?.('.cpa-monaco-editor')) return;
            e.preventDefault();
        });
        document.addEventListener('cut',   (e) => {
            if (e.target?.closest?.('.cpa-monaco-editor')) return;
            e.preventDefault();
        });
    }

    // ── Right-click block ─────────────────────────────────────────────────────
    function _setupRightClickBlock() {
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            _logViolation('rightclick', 'info', 'Right-click blocked');
        });
    }

    // ── DevTools keyboard shortcuts block ─────────────────────────────────────
    function _setupDevToolsBlock() {
        // Heuristic: block F12, Ctrl+Shift+I/J/C/U, Ctrl+U
        const blocked = new Set(['F12']);
        const ctrlShift = new Set(['I', 'J', 'C', 'K']);

        document.addEventListener('keydown', (e) => {
            if (blocked.has(e.key)) {
                e.preventDefault();
                _logViolation('devtools', 'critical', 'F12 pressed');
                return;
            }
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && ctrlShift.has(e.key.toUpperCase())) {
                e.preventDefault();
                _logViolation('devtools', 'critical', 'Ctrl+Shift+' + e.key);
                return;
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
                e.preventDefault();
                _logViolation('devtools', 'warning', 'Ctrl+U (view source)');
            }
        }, true);

        // Size-heuristic to detect DevTools open (fires periodically).
        let devtoolsOpen = false;
        setInterval(() => {
            const threshold = 160;
            const widthDiff  = window.outerWidth  - window.innerWidth;
            const heightDiff = window.outerHeight - window.innerHeight;
            const isOpen = widthDiff > threshold || heightDiff > threshold;
            if (isOpen && !devtoolsOpen) {
                devtoolsOpen = true;
                _logViolation('devtools', 'critical', 'DevTools size heuristic triggered');
            } else if (!isOpen) {
                devtoolsOpen = false;
            }
        }, 2000);
    }

    // ── Print Screen detection ────────────────────────────────────────────────
    function _setupPrintScreenBlock() {
        document.addEventListener('keyup', (e) => {
            if (e.key === 'PrintScreen') {
                // Clear clipboard (best-effort).
                navigator.clipboard?.writeText('').catch(() => {});
                _logViolation('printscreen', 'warning', 'PrintScreen key detected');
                _showToast('Screenshot attempt detected and recorded.');
            }
        });
    }

    // ── Idle detection ────────────────────────────────────────────────────────
    function _setupIdleDetection() {
        const reset = () => {
            clearTimeout(idleTimer);
            idleTimer = setTimeout(() => {
                _logViolation('idle_timeout', 'info', 'No interaction for ' + (IDLE_TIMEOUT/60000) + ' minutes');
            }, IDLE_TIMEOUT);
        };
        ['mousemove','keydown','click','touchstart'].forEach(ev =>
            document.addEventListener(ev, reset, { passive: true })
        );
        reset();
    }

    // ── Violation logging ─────────────────────────────────────────────────────
    function _logViolation(type, severity, details) {
        violationCount++;

        const body = new URLSearchParams({
            action:    'log_violation',
            attemptid: cfg.attemptid,
            sesskey:   cfg.sesskey,
            type,
            severity,
            details: details || ''
        });

        fetch(submitUrl, {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.autosubmitted) {
                // Force redirect to view page.
                window.location.href = data.redirect || window.location.origin;
            } else if (cfg.warningsonviolation && data.threshold > 0) {
                const remaining = data.threshold - data.violations;
                if (remaining <= 3 && remaining > 0) {
                    _showToast(
                        `Violation ${data.violations} of ${data.threshold}. ` +
                        `${remaining} more will auto-submit your attempt.`,
                        4000
                    );
                }
            }
        })
        .catch(() => { /* network error — non-fatal */ });
    }

    // ── Toast notification ────────────────────────────────────────────────────
    function _showToast(message, duration = 3500) {
        if (!message || !cfg.warningsonviolation) return;

        const toast = document.getElementById('cpa-violation-toast');
        if (!toast) return;

        toast.textContent = message;
        toast.style.display = 'block';
        toast.style.animation = 'none';
        // Trigger reflow.
        toast.offsetHeight; // eslint-disable-line no-unused-expressions
        toast.style.animation = 'cpa-toast-in 0.3s ease';

        setTimeout(() => {
            toast.style.display = 'none';
        }, duration);
    }
});
