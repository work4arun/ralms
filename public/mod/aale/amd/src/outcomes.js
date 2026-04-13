/**
 * Outcome setting with 30-minute countdown timers for mod_aale.
 * Handles countdown timers, outcome selection highlighting, saving, and admin overrides.
 *
 * @module mod_aale/outcomes
 * @copyright 2024 AALE Contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'core/notification'], function($, Str, Notification) {

    var OutcomesUI = {

        // Track state
        state: {
            slotid: null,
            freezeSecs: 1800, // Default 30 minutes
            timers: {}, // Track interval IDs per row
            frozenRows: {} // Track which rows are frozen
        },

        /**
         * Initialize outcomes UI.
         *
         * @param {Object} params Configuration parameters
         * @param {number} params.slotid The slot ID
         * @param {number} params.freezeSecs Freeze time in seconds (default: 1800 = 30 minutes)
         * @param {string} params.rowSelector CSS selector for outcome rows (default: '[data-outcome-row]')
         * @param {string} params.selectSelector CSS selector for outcome selects (default: '[name^="outcome"]')
         * @param {string} params.saveButtonSelector CSS selector for save buttons (default: '[data-action="save-outcome"]')
         * @param {boolean} params.isAdmin Whether user is admin (shows override buttons)
         * @param {string} params.sesskey Moodle session key
         */
        init: function(params) {
            if (!params || !params.slotid) {
                console.warn('mod_aale/outcomes: Missing required slotid parameter');
                return;
            }

            this.state.slotid = params.slotid;
            this.state.freezeSecs = params.freezeSecs || 1800;
            this.state.isAdmin = params.isAdmin || false;
            this.state.sesskey = params.sesskey;

            // Setup outcome row selection highlighting
            this.setupOutcomeSelection(params);

            // Setup countdown timers for each row
            this.setupCountdownTimers(params);

            // Setup save buttons
            this.setupSaveButtons(params);

            // Setup admin override buttons
            if (this.state.isAdmin) {
                this.setupAdminOverride(params);
            }

            // Animate summary bar
            this.setupSummaryBar(params);

            // Inject CSS styles
            this.injectStyles();
        },

        /**
         * Setup outcome selection highlighting based on selected value.
         *
         * @private
         * @param {Object} params Configuration parameters
         */
        setupOutcomeSelection: function(params) {
            var self = this;
            var selectSelector = params.selectSelector || '[name^="outcome"]';
            var $selects = $(selectSelector);

            if ($selects.length === 0) {
                return;
            }

            $selects.on('change', function() {
                var $select = $(this);
                var $row = $select.closest('[data-outcome-row], tr');
                var selectedValue = $select.val();

                // Remove all outcome status classes
                $row.removeClass('outcome-cleared outcome-try-again outcome-malpractice outcome-ignore');

                // Add appropriate class based on selection
                if (selectedValue === 'cleared' || selectedValue === '1') {
                    $row.addClass('outcome-cleared');
                } else if (selectedValue === 'try_again' || selectedValue === '2') {
                    $row.addClass('outcome-try-again');
                } else if (selectedValue === 'malpractice' || selectedValue === '3') {
                    $row.addClass('outcome-malpractice');
                } else if (selectedValue === 'ignore' || selectedValue === '0' || selectedValue === '') {
                    $row.addClass('outcome-ignore');
                }

                // Mark row as modified
                $row.addClass('outcome-modified');
            });

            // Apply initial highlighting
            $selects.each(function() {
                $(this).trigger('change');
            });
        },

        /**
         * Setup countdown timers for outcome rows.
         *
         * @private
         * @param {Object} params Configuration parameters
         */
        setupCountdownTimers: function(params) {
            var self = this;
            var rowSelector = params.rowSelector || '[data-outcome-row]';
            var $rows = $(rowSelector);

            if ($rows.length === 0) {
                return;
            }

            $rows.each(function() {
                var $row = $(this);
                var setAtStr = $row.attr('data-setat') || $row.attr('data-timestamp');
                var freezeAtStr = $row.attr('data-freezeat');

                if (!setAtStr && !freezeAtStr) {
                    return; // Skip rows without timestamps
                }

                // Determine freeze time: either from data-freezeat or calculated from now + freezeSecs
                var freezeAt;
                if (freezeAtStr) {
                    freezeAt = new Date(freezeAtStr).getTime();
                } else {
                    var setAt = new Date(setAtStr).getTime();
                    freezeAt = setAt + (self.state.freezeSecs * 1000);
                }

                var rowId = $row.attr('data-row-id') || 'row-' + Math.random().toString(36).substr(2, 9);
                $row.attr('data-row-id', rowId);

                // Check if already frozen
                var now = new Date().getTime();
                if (now >= freezeAt) {
                    self.freezeOutcomeRow($row);
                } else {
                    // Start countdown timer for this row
                    self.startCountdownTimer($row, freezeAt, rowId);
                }
            });

            // Global timer update every 100ms for smooth countdown
            setInterval(function() {
                self.updateAllCountdowns();
            }, 100);
        },

        /**
         * Start countdown timer for a specific row.
         *
         * @private
         * @param {jQuery} $row The outcome row
         * @param {number} freezeAt Freeze timestamp in milliseconds
         * @param {string} rowId Unique row identifier
         */
        startCountdownTimer: function($row, freezeAt, rowId) {
            var self = this;

            var timerId = setInterval(function() {
                var now = new Date().getTime();
                var remaining = freezeAt - now;

                if (remaining <= 0) {
                    clearInterval(timerId);
                    delete self.state.timers[rowId];
                    self.freezeOutcomeRow($row);
                } else {
                    var seconds = Math.ceil(remaining / 1000);
                    var minutes = Math.floor(seconds / 60);
                    var secs = seconds % 60;

                    var timeText = minutes + ':' + (secs < 10 ? '0' : '') + secs;
                    var $timer = $row.find('[data-countdown-timer]');

                    if ($timer.length === 0) {
                        $row.find('td').first().prepend(
                            '<span class="outcome-timer" data-countdown-timer>' +
                            'Freezes in ' + timeText +
                            '</span>'
                        );
                    } else {
                        $timer.text('Freezes in ' + timeText);
                    }
                }
            }, 500); // Update every 500ms for smooth countdown

            this.state.timers[rowId] = timerId;
        },

        /**
         * Update all active countdowns (called periodically).
         *
         * @private
         */
        updateAllCountdowns: function() {
            // This is called by the global interval but individual timers handle updates
            // This function exists as a hook for future optimizations
        },

        /**
         * Freeze an outcome row when timer reaches zero.
         *
         * @private
         * @param {jQuery} $row The outcome row
         */
        freezeOutcomeRow: function($row) {
            var rowId = $row.attr('data-row-id');

            if (this.state.frozenRows[rowId]) {
                return; // Already frozen
            }

            this.state.frozenRows[rowId] = true;

            // Disable select and save button
            $row.find('[name^="outcome"], [data-action="save-outcome"]').prop('disabled', true);

            // Replace or add frozen badge
            var $timer = $row.find('[data-countdown-timer]');
            if ($timer.length > 0) {
                $timer.replaceWith(
                    '<span class="outcome-frozen-badge">' +
                    '<i class="fa fa-lock"></i> Frozen' +
                    '</span>'
                );
            } else {
                $row.find('td').first().prepend(
                    '<span class="outcome-frozen-badge">' +
                    '<i class="fa fa-lock"></i> Frozen' +
                    '</span>'
                );
            }

            // Add frozen class for styling
            $row.addClass('outcome-row-frozen');
        },

        /**
         * Setup save buttons for individual outcomes.
         *
         * @private
         * @param {Object} params Configuration parameters
         */
        setupSaveButtons: function(params) {
            var self = this;
            var buttonSelector = params.saveButtonSelector || '[data-action="save-outcome"]';
            var $buttons = $(buttonSelector);

            if ($buttons.length === 0) {
                return;
            }

            $buttons.on('click', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $row = $btn.closest('[data-outcome-row], tr');
                var rowId = $row.attr('data-row-id');

                // Don't save if frozen
                if (self.state.frozenRows[rowId]) {
                    Notification.addNotification({
                        message: 'This outcome is frozen and cannot be modified.',
                        type: 'warning'
                    });
                    return;
                }

                var $select = $row.find('[name^="outcome"]');
                var outcomeValue = $select.val();
                var userId = $row.attr('data-userid');
                var outcomeId = $row.attr('data-outcomeid');

                if (!userId || !outcomeId) {
                    console.warn('Missing user or outcome ID on row');
                    return;
                }

                self.saveOutcome($btn, $row, userId, outcomeId, outcomeValue);
            });
        },

        /**
         * Save an outcome via fetch API.
         *
         * @private
         * @param {jQuery} $btn The save button
         * @param {jQuery} $row The row being saved
         * @param {number} userId User ID
         * @param {number} outcomeId Outcome ID
         * @param {string} outcomeValue The selected outcome value
         */
        saveOutcome: function($btn, $row, userId, outcomeId, outcomeValue) {
            var self = this;
            var originalText = $btn.text();

            // Show loading state
            $btn.prop('disabled', true);
            $btn.html('<span class="spinner-border spinner-border-sm mr-1"></span>Saving...');

            var formData = new FormData();
            formData.append('action', 'save_outcome');
            formData.append('userid', userId);
            formData.append('outcomeid', outcomeId);
            formData.append('value', outcomeValue);
            formData.append('slotid', this.state.slotid);
            formData.append('sesskey', this.state.sesskey);

            fetch('/mod/aale/faculty/outcomes.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // Show success feedback
                    self.showOutcomeSaved($btn, $row, originalText);
                    $row.removeClass('outcome-modified');
                } else {
                    throw new Error(data.message || 'Failed to save outcome');
                }
            })
            .catch(function(error) {
                Notification.addNotification({
                    message: 'Error: ' + error.message,
                    type: 'error'
                });
                $btn.prop('disabled', false);
                $btn.text(originalText);
            });
        },

        /**
         * Show saved feedback on button and row.
         *
         * @private
         * @param {jQuery} $btn The save button
         * @param {jQuery} $row The row
         * @param {string} originalText Original button text
         */
        showOutcomeSaved: function($btn, $row, originalText) {
            $btn.html('<i class="fa fa-check mr-1"></i>Saved ✓');
            $btn.addClass('btn-success').removeClass('btn-primary');

            // Revert button after 2 seconds
            setTimeout(function() {
                $btn.prop('disabled', false);
                $btn.text(originalText);
                $btn.removeClass('btn-success').addClass('btn-primary');
            }, 2000);

            // Flash row
            $row.addClass('outcome-saved-flash');
            setTimeout(function() {
                $row.removeClass('outcome-saved-flash');
            }, 600);
        },

        /**
         * Setup admin override functionality.
         *
         * @private
         * @param {Object} params Configuration parameters
         */
        setupAdminOverride: function(params) {
            var self = this;
            var rowSelector = params.rowSelector || '[data-outcome-row]';
            var $rows = $(rowSelector);

            if ($rows.length === 0) {
                return;
            }

            $rows.each(function() {
                var $row = $(this);

                // Add override button to frozen rows
                var rowId = $row.attr('data-row-id');
                if (self.state.frozenRows[rowId]) {
                    var $overrideBtn = $('<button>')
                        .addClass('btn btn-sm btn-warning')
                        .html('<i class="fa fa-unlock mr-1"></i>Override')
                        .attr('data-action', 'override-outcome')
                        .on('click', function(e) {
                            e.preventDefault();
                            self.overrideOutcome($row);
                        });

                    var $saveBtn = $row.find('[data-action="save-outcome"]');
                    if ($saveBtn.length > 0) {
                        $saveBtn.after(' ').after($overrideBtn);
                    }
                }
            });

            // Watch for newly frozen rows and add override buttons dynamically
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.target.classList && mutation.target.classList.contains('outcome-row-frozen')) {
                        var $row = $(mutation.target);
                        if ($row.find('[data-action="override-outcome"]').length === 0) {
                            var $overrideBtn = $('<button>')
                                .addClass('btn btn-sm btn-warning')
                                .html('<i class="fa fa-unlock mr-1"></i>Override')
                                .attr('data-action', 'override-outcome')
                                .on('click', function(e) {
                                    e.preventDefault();
                                    self.overrideOutcome($row);
                                });

                            var $saveBtn = $row.find('[data-action="save-outcome"]');
                            if ($saveBtn.length > 0) {
                                $saveBtn.after(' ').after($overrideBtn);
                            }
                        }
                    }
                });
            });

            observer.observe(document.body, {
                attributes: true,
                attributeFilter: ['class'],
                subtree: true
            });
        },

        /**
         * Override a frozen outcome (admin only).
         *
         * @private
         * @param {jQuery} $row The outcome row
         */
        overrideOutcome: function($row) {
            var rowId = $row.attr('data-row-id');

            // Re-enable controls
            $row.find('[name^="outcome"], [data-action="save-outcome"]').prop('disabled', false);
            $row.removeClass('outcome-row-frozen');

            // Replace frozen badge with timer or remove
            $row.find('[data-countdown-timer], .outcome-frozen-badge').remove();

            // Mark as overridden
            $row.addClass('outcome-admin-override');

            Notification.addNotification({
                message: 'Outcome unlocked for editing.',
                type: 'info'
            });

            // Clear frozen state
            delete this.state.frozenRows[rowId];
        },

        /**
         * Setup animated summary bar showing outcome counts.
         *
         * @private
         * @param {Object} params Configuration parameters
         */
        setupSummaryBar: function(params) {
            var summarySelector = params.summarySelector || '[data-outcomes-summary]';
            var $summary = $(summarySelector);

            if ($summary.length === 0) {
                return;
            }

            // Calculate counts
            var counts = {
                cleared: 0,
                try_again: 0,
                malpractice: 0,
                ignore: 0
            };

            var rowSelector = params.rowSelector || '[data-outcome-row]';
            $(rowSelector).each(function() {
                var $row = $(this);
                var selectedValue = $row.find('[name^="outcome"]').val();

                if (selectedValue === 'cleared' || selectedValue === '1') {
                    counts.cleared++;
                } else if (selectedValue === 'try_again' || selectedValue === '2') {
                    counts.try_again++;
                } else if (selectedValue === 'malpractice' || selectedValue === '3') {
                    counts.malpractice++;
                } else {
                    counts.ignore++;
                }
            });

            // Animate summary display
            $summary.html(
                '<span class="summary-item summary-cleared">' +
                '<i class="fa fa-check-circle"></i> Cleared: ' + counts.cleared +
                '</span> ' +
                '<span class="summary-item summary-try-again">' +
                '<i class="fa fa-exclamation-circle"></i> Try Again: ' + counts.try_again +
                '</span> ' +
                '<span class="summary-item summary-malpractice">' +
                '<i class="fa fa-times-circle"></i> Malpractice: ' + counts.malpractice +
                '</span>'
            );

            $summary.addClass('outcomes-summary-fade-in');
        },

        /**
         * Inject CSS styles for animations and states.
         *
         * @private
         */
        injectStyles: function() {
            if (!$('#aale-outcomes-styles').length) {
                var $style = $('<style id="aale-outcomes-styles">')
                    .text(
                        '.outcome-cleared {\n' +
                        '    background-color: #d4edda !important;\n' +
                        '}\n' +
                        '.outcome-try-again {\n' +
                        '    background-color: #fff3cd !important;\n' +
                        '}\n' +
                        '.outcome-malpractice {\n' +
                        '    background-color: #f8d7da !important;\n' +
                        '}\n' +
                        '.outcome-ignore {\n' +
                        '    background-color: #e2e3e5 !important;\n' +
                        '}\n' +
                        '.outcome-modified {\n' +
                        '    border-left: 3px solid #007bff;\n' +
                        '}\n' +
                        '.outcome-timer {\n' +
                        '    display: inline-block;\n' +
                        '    font-size: 0.85em;\n' +
                        '    color: #666;\n' +
                        '    margin-right: 10px;\n' +
                        '    font-weight: 500;\n' +
                        '}\n' +
                        '.outcome-frozen-badge {\n' +
                        '    display: inline-block;\n' +
                        '    padding: 4px 8px;\n' +
                        '    background-color: #dc3545;\n' +
                        '    color: white;\n' +
                        '    border-radius: 3px;\n' +
                        '    font-size: 0.8em;\n' +
                        '    margin-right: 10px;\n' +
                        '}\n' +
                        '.outcome-row-frozen {\n' +
                        '    opacity: 0.7;\n' +
                        '    pointer-events: none;\n' +
                        '}\n' +
                        '.outcome-row-frozen select,\n' +
                        '.outcome-row-frozen input,\n' +
                        '.outcome-row-frozen button:not([data-action="override-outcome"]) {\n' +
                        '    cursor: not-allowed;\n' +
                        '}\n' +
                        '.outcome-admin-override {\n' +
                        '    border-left: 3px solid #ff9800 !important;\n' +
                        '}\n' +
                        '.outcome-saved-flash {\n' +
                        '    animation: outcomeSavedFlash 0.6s ease-out;\n' +
                        '}\n' +
                        '@keyframes outcomeSavedFlash {\n' +
                        '    0% { background-color: #28a745; color: white; }\n' +
                        '    100% { background-color: transparent; color: inherit; }\n' +
                        '}\n' +
                        '.outcomes-summary-fade-in {\n' +
                        '    animation: summaryFadeIn 0.5s ease-out;\n' +
                        '}\n' +
                        '@keyframes summaryFadeIn {\n' +
                        '    from { opacity: 0; transform: translateY(-10px); }\n' +
                        '    to { opacity: 1; transform: translateY(0); }\n' +
                        '}\n' +
                        '.summary-item {\n' +
                        '    display: inline-block;\n' +
                        '    margin: 0 15px;\n' +
                        '    padding: 5px 10px;\n' +
                        '    border-radius: 4px;\n' +
                        '}\n' +
                        '.summary-cleared { background-color: #d4edda; color: #155724; }\n' +
                        '.summary-try-again { background-color: #fff3cd; color: #856404; }\n' +
                        '.summary-malpractice { background-color: #f8d7da; color: #721c24; }\n' +
                        '.spinner-border-sm {\n' +
                        '    width: 0.9rem;\n' +
                        '    height: 0.9rem;\n' +
                        '    border-width: 0.15em;\n' +
                        '}'
                    );
                $('head').append($style);
            }
        }
    };

    return OutcomesUI;
});
