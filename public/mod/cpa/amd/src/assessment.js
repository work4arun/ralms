// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * assessment.js — Orchestrates the student assessment experience.
 *
 * Responsibilities:
 *   • Countdown timer (minutes:seconds display, auto-submit on expire)
 *   • MCQ option highlight toggling (radio + checkbox)
 *   • Short-answer auto-save debounce
 *   • Monaco editor initialisation (one per coding question)
 *   • Code language-switch handler
 *   • "Run code" button (delegates to Piston sandbox API)
 *   • Answer auto-save (via submit.php AJAX)
 *   • Submit button: unanswered-question warning, final submission
 *
 * @module     mod_cpa/assessment
 * @copyright  2026 CPA Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    // ── Config & state ────────────────────────────────────────────────────────
    let cfg = {};
    let editors = {};      // { [questionId]: Monaco editor instance }
    let saveQueue = {};    // { [questionId]: debounce timer }
    let timerInterval = null;
    let timeRemaining  = 0;

    const SAVE_DEBOUNCE_MS = 1200;
    const AUTOSAVE_LABEL   = document.getElementById('cpa-autosave-indicator');

    // Piston API (free public sandbox — teachers should host their own for production).
    const PISTON_API = 'https://emkc.org/api/v2/piston/execute';

    // Language → Piston runtime mapping.
    const PISTON_RUNTIMES = {
        python:     { language: 'python',     version: '3.10.0' },
        javascript: { language: 'javascript', version: '18.15.0' },
        java:       { language: 'java',       version: '15.0.2' },
        cpp:        { language: 'c++',        version: '10.2.0' },
        c:          { language: 'c',          version: '10.2.0' },
        go:         { language: 'go',         version: '1.16.2' },
        rust:       { language: 'rust',       version: '1.50.0' },
        php:        { language: 'php',        version: '8.2.3' },
        ruby:       { language: 'ruby',       version: '3.0.1' },
        typescript: { language: 'typescript', version: '5.0.3' },
        kotlin:     { language: 'kotlin',     version: '1.8.20' },
        swift:      { language: 'swift',      version: '5.9.1' },
        bash:       { language: 'bash',       version: '5.2.0' },
        sql:        { language: 'sqlite3',    version: '3.36.0' },
    };

    // Monaco → Piston language ID map.
    const MONACO_LANG = {
        python: 'python', javascript: 'javascript', java: 'java',
        cpp: 'cpp', c: 'c', go: 'go', rust: 'rust',
        php: 'php', ruby: 'ruby', typescript: 'typescript',
        kotlin: 'kotlin', swift: 'swift', bash: 'shell', sql: 'sql',
    };

    // ── Public API ────────────────────────────────────────────────────────────
    return {
        init(config) {
            cfg = config;

            _initTimer();
            _initMCQ();
            _initShortAnswers();
            _initSubmitButton();

            if (cfg.hasMonaco) {
                _waitForMonaco(_initAllEditors);
            }
        }
    };

    // ═══════════════════════════════════════════════════════════════════════════
    //  TIMER
    // ═══════════════════════════════════════════════════════════════════════════
    function _initTimer() {
        const el = document.getElementById('cpa-timer');
        if (!el || !cfg.timelimit) return;

        timeRemaining = parseInt(el.dataset.timeleft, 10) || 0;

        timerInterval = setInterval(() => {
            timeRemaining--;

            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                el.textContent = '00:00:00';
                el.style.background = 'rgba(220,38,38,0.8)';
                _submitAttempt(true);
                return;
            }

            // Colour cue: red when < 5 min.
            if (timeRemaining < 300) {
                el.style.background = 'rgba(220,38,38,0.5)';
                el.style.color = '#fff';
            } else if (timeRemaining < 600) {
                el.style.background = 'rgba(217,119,6,0.5)';
            }

            const h = Math.floor(timeRemaining / 3600);
            const m = Math.floor((timeRemaining % 3600) / 60);
            const s = timeRemaining % 60;
            el.textContent = [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
        }, 1000);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  MCQ (radio + checkbox)
    // ═══════════════════════════════════════════════════════════════════════════
    function _initMCQ() {
        // Visual highlight for MCQ options.
        document.querySelectorAll('.cpa-option').forEach(label => {
            const input = label.querySelector('input');
            if (!input) return;

            input.addEventListener('change', () => {
                const container = label.closest('.cpa-mcq-options');
                if (!container) return;
                const qid  = container.dataset.questionid;
                const type = container.dataset.type;

                if (type === 'single') {
                    // Deselect all siblings.
                    container.querySelectorAll('.cpa-option').forEach(l => {
                        _setOptionSelected(l, false);
                    });
                }
                _setOptionSelected(label, input.checked);

                // Collect selected.
                const selected = [];
                container.querySelectorAll('input:checked').forEach(i => {
                    selected.push(parseInt(i.value, 10));
                });

                _saveAnswer(qid, 'selected', JSON.stringify(selected));
            });

            // Restore visual state on page load.
            if (input.checked) {
                _setOptionSelected(label, true);
            }
        });
    }

    function _setOptionSelected(label, on) {
        label.style.borderColor  = on ? '#2563eb' : 'var(--cm-border,#e5e7eb)';
        label.style.background   = on ? 'rgba(37,99,235,0.06)' : 'var(--cm-surface,#fff)';
        label.style.boxShadow    = on ? '0 0 0 3px rgba(37,99,235,0.12)' : 'none';
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SHORT ANSWERS
    // ═══════════════════════════════════════════════════════════════════════════
    function _initShortAnswers() {
        document.querySelectorAll('.cpa-short-answer').forEach(input => {
            const qid = input.dataset.qid;
            input.addEventListener('input', () => {
                _debouncedSave(qid, () => {
                    _saveAnswer(qid, 'text', input.value);
                });
            });
        });
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  MONACO EDITORS
    // ═══════════════════════════════════════════════════════════════════════════
    function _waitForMonaco(cb) {
        if (typeof require !== 'undefined' && require.config) {
            require(['vs/editor/editor.main'], cb);
        } else {
            // Loader not yet ready — poll.
            const interval = setInterval(() => {
                if (typeof require !== 'undefined' && require.config) {
                    clearInterval(interval);
                    require(['vs/editor/editor.main'], cb);
                }
            }, 100);
        }
    }

    function _initAllEditors() {
        document.querySelectorAll('.cpa-monaco-editor').forEach(container => {
            const qid   = container.dataset.qid || container.id.replace('cpa-editor-', '');
            const lang  = container.dataset.lang  || 'python';
            const value = container.dataset.value || '';

            const editor = monaco.editor.create(container, {
                value,
                language:        MONACO_LANG[lang] || 'python',
                theme:           'vs-dark',
                fontSize:        14,
                fontFamily:      '"JetBrains Mono", "Cascadia Code", monospace',
                lineNumbers:     'on',
                minimap:         { enabled: false },
                scrollBeyondLastLine: false,
                wordWrap:        'on',
                tabSize:         4,
                automaticLayout: true,
                renderLineHighlight: 'all',
                contextmenu:     false,  // disabled to block right-click copy in strict mode
                overviewRulerBorder: false,
                padding:         { top: 12, bottom: 12 },
            });

            editors[qid] = editor;

            // Auto-save on change (debounced).
            editor.onDidChangeModelContent(() => {
                _debouncedSave(qid, () => {
                    const panel = container.closest('.cpa-coding-panel');
                    const langSel = panel?.querySelector('.cpa-lang-select');
                    const language = langSel ? langSel.value : lang;
                    _saveAnswer(qid, 'code', editor.getValue(), language);
                });
            });
        });

        // Language select dropdowns.
        document.querySelectorAll('.cpa-lang-select').forEach(sel => {
            sel.addEventListener('change', () => {
                const qid    = sel.dataset.qid;
                const editor = editors[qid];
                if (editor) {
                    monaco.editor.setModelLanguage(
                        editor.getModel(),
                        MONACO_LANG[sel.value] || 'plaintext'
                    );
                    // Save immediately on language change.
                    _saveAnswer(qid, 'code', editor.getValue(), sel.value);
                }
            });
        });

        // Run buttons.
        document.querySelectorAll('.cpa-run-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const qid = btn.dataset.qid;
                _runCode(qid);
            });
        });
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  CODE RUNNER
    // ═══════════════════════════════════════════════════════════════════════════
    async function _runCode(qid) {
        const editor  = editors[qid];
        const panel   = document.querySelector('.cpa-coding-panel[data-qid="' + qid + '"]') ||
                        document.getElementById('cpa-editor-' + qid)?.closest('.cpa-coding-panel');
        const output  = document.getElementById('cpa-output-' + qid);
        const langSel = panel?.querySelector('.cpa-lang-select');
        const btn     = panel?.querySelector('.cpa-run-btn');

        if (!editor || !output) return;

        const code     = editor.getValue();
        const language = langSel?.value || 'python';
        const runtime  = PISTON_RUNTIMES[language];

        if (!runtime) {
            output.textContent = 'Language runtime not available.';
            return;
        }

        // UI feedback.
        if (btn) { btn.disabled = true; btn.textContent = '⏳ Running…'; }
        output.innerHTML = '<span style="opacity:.5">Running…</span>';

        try {
            const res = await fetch(PISTON_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    language: runtime.language,
                    version:  runtime.version,
                    files:    [{ name: 'main', content: code }],
                    stdin:    '',
                    args:     [],
                    compile_timeout: 10000,
                    run_timeout:     5000,
                })
            });

            if (!res.ok) throw new Error('Piston API returned ' + res.status);

            const data   = await res.json();
            const run    = data.run || {};
            const stdout = run.stdout || '';
            const stderr = run.stderr || '';

            output.innerHTML = '';
            if (stdout) {
                const pre = document.createElement('span');
                pre.style.color = '#a6e3a1';
                pre.textContent = stdout;
                output.appendChild(pre);
            }
            if (stderr) {
                const err = document.createElement('span');
                err.style.color = '#f38ba8';
                err.textContent = stderr;
                output.appendChild(err);
            }
            if (!stdout && !stderr) {
                output.innerHTML = '<span style="opacity:.4">(no output)</span>';
            }

            // Save execution result.
            const execResult = { stdout, stderr, exit_code: run.code };
            _saveAnswer(qid, 'code', code, langSel?.value || 'python', execResult);

        } catch (e) {
            output.innerHTML = '<span style="color:#f38ba8">Error connecting to code runner: ' + e.message + '</span>';
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '▶ Run code'; }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SUBMIT BUTTON
    // ═══════════════════════════════════════════════════════════════════════════
    function _initSubmitButton() {
        const btn = document.getElementById('cpa-submit-btn');
        if (!btn) return;

        btn.addEventListener('click', () => {
            const total    = parseInt(btn.dataset.totalq,   10) || 0;
            const answered = parseInt(btn.dataset.answered, 10) || 0;
            const unanswered = total - answered;

            let msg = 'Are you sure you want to submit your assessment? You cannot change your answers after submission.';
            if (unanswered > 0) {
                msg = unanswered + ' question(s) still unanswered. Submit anyway?';
            }

            if (window.confirm(msg)) {
                _submitAttempt(false);
            }
        });
    }

    function _submitAttempt(timedOut = false) {
        const btn = document.getElementById('cpa-submit-btn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = timedOut ? '⏱ Time\'s up — submitting…' : '⏳ Submitting…';
        }

        const body = new URLSearchParams({
            action:    'submit_attempt',
            attemptid: cfg.attemptid,
            sesskey:   cfg.sesskey,
        });

        fetch(cfg.submiturl, {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        })
        .catch(() => {
            // Fallback — navigate to view page directly.
            window.location.href = cfg.wwwroot + '/mod/cpa/view.php?id=' + cfg.cmid;
        });
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  ANSWER AUTO-SAVE
    // ═══════════════════════════════════════════════════════════════════════════
    function _saveAnswer(qid, answertype, value, language, executionresult) {
        _setAutosaveLabel('Saving…');

        const body = new URLSearchParams({
            action:     'save_answer',
            attemptid:  cfg.attemptid,
            sesskey:    cfg.sesskey,
            questionid: qid,
            answertype,
            value,
            language:   language || '',
        });

        if (executionresult) {
            body.set('executionresult', JSON.stringify(executionresult));
        }

        fetch(cfg.submiturl, {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                _setAutosaveLabel('Saved ✓');
                setTimeout(() => _setAutosaveLabel(''), 2000);
                // Update answered counter on submit button.
                _updateAnsweredCount(qid);
            }
        })
        .catch(() => {
            _setAutosaveLabel('⚠ Save failed');
        });
    }

    function _debouncedSave(qid, fn) {
        clearTimeout(saveQueue[qid]);
        saveQueue[qid] = setTimeout(fn, SAVE_DEBOUNCE_MS);
    }

    function _setAutosaveLabel(text) {
        if (AUTOSAVE_LABEL) {
            AUTOSAVE_LABEL.textContent = text;
        }
    }

    function _updateAnsweredCount(qid) {
        const btn = document.getElementById('cpa-submit-btn');
        if (!btn) return;
        const dot = document.getElementById('cpa-qdot-' + qid);
        if (dot) {
            dot.style.background = '#d1fae5';
            dot.style.color      = '#065f46';
        }
    }
});
