/**
 * Attendance marking with auto-save and freeze confirmation for mod_aale.
 * Handles tab switching, auto-save on radio changes, visual feedback, and session freezing.
 *
 * @module mod_aale/attendance
 * @copyright 2024 AALE Contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    var AttendanceUI = {

        // Track state
        state: {
            slotid: null,
            currentSessionId: null,
            isSaving: false,
            frozenSessions: {}
        },

        /**
         * Initialize attendance marking UI.
         *
         * @param {Object} params Configuration parameters
         * @param {number} params.slotid The slot ID for this attendance session
         * @param {string} params.sesskey Moodle session key for CSRF protection
         * @param {string} params.tabSelector CSS selector for tab triggers (default: '[role="tab"]')
         * @param {string} params.radioSelector CSS selector for attendance radio buttons (default: '[name^="attend"]')
         * @param {string} params.freezeButtonSelector CSS selector for freeze buttons (default: '[data-action="freeze"]')
         */
        init: function(params) {
            if (!params || !params.slotid) {
                console.warn('mod_aale/attendance: Missing required slotid parameter');
                return;
            }

            this.state.slotid = params.slotid;
            this.state.sesskey = params.sesskey;

            // Setup tab switching
            this.setupTabSwitching(params);

            // Setup auto-save on radio changes
            this.setupAutoSave(params);

            // Setup freeze buttons
            this.setupFreezeButtons(params);

            // Mark initially frozen sessions
            this.markFrozenSessions(params);

            // Inject CSS animations if needed
            this.injectStyles();
        },

        /**
         * Setup tab switching for different sessions.
         *
         * @private
         * @param {Object} params Configuration parameters
         */
        setupTabSwitching: function(params) {
            var self = this;
            var $tabs = $(params.tabSelector || '[role="tab"]');

            if ($tabs.length === 0) {
                return;
            }

            $tabs.on('click', function() {
                var $tab = $(this);
                var tabId = $tab.attr('id') || $tab.attr('data-session-id');
                var sessionId = tabId.replace(/^tab-/, '');

                self.state.currentSessionId = sessionId;

                // Update active tab styling
                $tabs.removeClass('active').attr('aria-selected', 'false');
                $tab.addClass('active').attr('aria-selected', 'true');

                // Show corresponding panel
                var panelId = $tab.attr('aria-controls') || 'panel-' + tabId;
                var $panels = $(document).find('[role="tabpanel"]');
                $panels.removeClass('active').addClass('hidden');
                $(document).find('#' + panelId).removeClass('hidden').addClass('active');
            });

            // Set initial active session
            var $activeTab = $tabs.filter('.active').first();
            if ($activeTab.length > 0) {
                $activeTab.trigger('click');
            } else if ($tabs.length > 0) {
                $tabs.first().trigger('click');
            }
        },

        /**
         * Setup auto-save when attendance radio buttons change.
         *
         * @private
         * @param {Object} params Configuration parameters
         */
        setupAutoSave: function(params) {
            var self = this;
            var radioSelector = params.radioSelector || '[name^="attend"]';
            var $radios = $(radioSelector);

            if ($radios.length === 0) {
                return;
            }

            $radios.on('change', function() {
                var $radio = $(this);
                var $row = $radio.closest('tr, [data-attendance-row]');
                var userId = $radio.attr('data-userid') || $radio.attr('name').replace(/\D/g, '');
                var status = $radio.val();
                var sessionId = self.state.currentSessionId;

                // Don't save if session is frozen
                if (self.isFrozen(sessionId)) {
                    $radio.prop('checked', false);
                    Notification.addNotification({
                        message: 'This session is frozen and cannot be modified.',
                        type: 'warning'
                    });
                    return;
                }

                // Show loading state on the row
                self.showRowLoading($row);

                // Perform auto-save
                self.saveAttendance(userId, status, sessionId, $row);
            });
        },

        /**
         * Save attendance mark via AJAX.
         *
         * @private
         * @param {number} userId The user ID
         * @param {string} status 'present' or 'absent'
         * @param {number} sessionId The session/slot ID
         * @param {jQuery} $row The table row being updated
         */
        saveAttendance: function(userId, status, sessionId, $row) {
            var self = this;

            if (this.state.isSaving) {
                return;
            }

            this.state.isSaving = true;

            var postData = {
                action: 'mark',
                userid: userId,
                status: status,
                sessionid: sessionId,
                slotid: this.state.slotid,
                sesskey: this.state.sesskey
            };

            // Use Fetch API for POST request
            fetch('/mod/aale/faculty/attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(postData),
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                self.state.isSaving = false;

                if (data.success) {
                    // Flash the row with success color
                    self.flashRowSuccess($row);
                } else {
                    // Show error and revert
                    self.flashRowError($row);
                    Notification.addNotification({
                        message: data.message || 'Failed to save attendance',
                        type: 'error'
                    });
                    // Revert the radio button
                    $row.find('[name^="attend"]').prop('checked', false);
                }
            })
            .catch(function(error) {
                self.state.isSaving = false;
                self.flashRowError($row);
                Notification.addNotification({
                    message: 'Error saving attendance: ' + error.message,
                    type: 'error'
                });
                // Revert the radio button
                $row.find('[name^="attend"]').prop('checked', false);
            });
        },

        /**
         * Show loading state on a row.
         *
         * @private
         * @param {jQuery} $row The table row
         */
        showRowLoading: function($row) {
            $row.addClass('attendance-saving');
        },

        /**
         * Flash row with success color.
         *
         * @private
         * @param {jQuery} $row The table row
         */
        flashRowSuccess: function($row) {
            $row.removeClass('attendance-saving');
            $row.addClass('attendance-success');

            setTimeout(function() {
                $row.removeClass('attendance-success');
            }, 1200);
        },

        /**
         * Flash row with error color.
         *
         * @private
         * @param {jQuery} $row The table row
         */
        flashRowError: function($row) {
            $row.removeClass('attendance-saving');
            $row.addClass('attendance-error');

            setTimeout(function() {
                $row.removeClass('attendance-error');
            }, 1200);
        },

        /**
         * Setup freeze session buttons.
         *
         * @private
         * @param {Object} params Configuration parameters
         */
        setupFreezeButtons: function(params) {
            var self = this;
            var freezeSelector = params.freezeButtonSelector || '[data-action="freeze"]';
            var $freezeButtons = $(freezeSelector);

            if ($freezeButtons.length === 0) {
                return;
            }

            $freezeButtons.on('click', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var sessionId = $btn.attr('data-session-id') || self.state.currentSessionId;
                var sessionName = $btn.attr('data-session-name') || ('Session ' + sessionId);

                var confirmMessage = 'This will lock attendance for ' + sessionName + ' and cannot be undone. Proceed?';

                Notification.confirm(
                    confirmMessage,
                    'Freeze Session',
                    function() {
                        self.freezeSession(sessionId, sessionName, $btn);
                    },
                    null
                );
            });
        },

        /**
         * Freeze a session via AJAX.
         *
         * @private
         * @param {number} sessionId The session ID
         * @param {string} sessionName Display name of the session
         * @param {jQuery} $btn The freeze button
         */
        freezeSession: function(sessionId, sessionName, $btn) {
            var self = this;

            var postData = {
                action: 'freeze_session',
                sessionid: sessionId,
                slotid: this.state.slotid,
                sesskey: this.state.sesskey
            };

            $btn.prop('disabled', true);
            $btn.html('<span class="spinner-border spinner-border-sm mr-2"></span>Freezing...');

            fetch('/mod/aale/faculty/attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(postData),
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
                    // Mark session as frozen
                    self.state.frozenSessions[sessionId] = true;

                    // Update UI
                    self.disableSessionInputs(sessionId);
                    self.updateFreezeButton($btn, sessionId);

                    Notification.addNotification({
                        message: sessionName + ' has been frozen.',
                        type: 'success'
                    });
                } else {
                    throw new Error(data.message || 'Failed to freeze session');
                }
            })
            .catch(function(error) {
                Notification.addNotification({
                    message: 'Error: ' + error.message,
                    type: 'error'
                });
                $btn.prop('disabled', false);
                $btn.html('Freeze Session');
            });
        },

        /**
         * Disable all inputs in a frozen session.
         *
         * @private
         * @param {number} sessionId The session ID
         */
        disableSessionInputs: function(sessionId) {
            var $panel = $(document).find('[data-session-id="' + sessionId + '"]').closest('[role="tabpanel"]');

            if ($panel.length === 0) {
                // Try alternate selector
                $panel = $(document).find('[id*="' + sessionId + '"]').filter('[role="tabpanel"]');
            }

            if ($panel.length > 0) {
                $panel.find('[name^="attend"], [data-action="freeze"]').prop('disabled', true);
                $panel.addClass('attendance-frozen');
            }
        },

        /**
         * Update the freeze button appearance for a frozen session.
         *
         * @private
         * @param {jQuery} $btn The freeze button
         * @param {number} sessionId The session ID
         */
        updateFreezeButton: function($btn, sessionId) {
            $btn.replaceWith(
                '<span class="badge badge-secondary">' +
                '<i class="fa fa-lock mr-1"></i>Frozen</span>'
            );
        },

        /**
         * Mark sessions that are already frozen based on data attributes.
         *
         * @private
         * @param {Object} params Configuration parameters
         */
        markFrozenSessions: function(params) {
            var self = this;
            var $frozenElements = $(document).find('[data-frozen="true"], [data-is-frozen="1"]');

            $frozenElements.each(function() {
                var $el = $(this);
                var sessionId = $el.attr('data-session-id') || $el.closest('[role="tabpanel"]').attr('data-session-id');

                if (sessionId) {
                    self.state.frozenSessions[sessionId] = true;
                    self.disableSessionInputs(sessionId);
                }
            });
        },

        /**
         * Check if a session is frozen.
         *
         * @private
         * @param {number} sessionId The session ID
         * @returns {boolean} True if frozen
         */
        isFrozen: function(sessionId) {
            return this.state.frozenSessions[sessionId] === true;
        },

        /**
         * Inject CSS styles for animations and states.
         *
         * @private
         */
        injectStyles: function() {
            if (!$('#aale-attendance-styles').length) {
                var $style = $('<style id="aale-attendance-styles">')
                    .text(
                        '.attendance-saving {\n' +
                        '    background-color: #fff3cd !important;\n' +
                        '    opacity: 0.8;\n' +
                        '}\n' +
                        '.attendance-success {\n' +
                        '    animation: attendanceFlashGreen 1.2s ease-out;\n' +
                        '}\n' +
                        '.attendance-error {\n' +
                        '    animation: attendanceFlashRed 1.2s ease-out;\n' +
                        '}\n' +
                        '@keyframes attendanceFlashGreen {\n' +
                        '    0% { background-color: #d4edda; }\n' +
                        '    100% { background-color: transparent; }\n' +
                        '}\n' +
                        '@keyframes attendanceFlashRed {\n' +
                        '    0% { background-color: #f8d7da; }\n' +
                        '    100% { background-color: transparent; }\n' +
                        '}\n' +
                        '.attendance-frozen {\n' +
                        '    opacity: 0.6;\n' +
                        '    pointer-events: none;\n' +
                        '}\n' +
                        '.attendance-frozen input[type="radio"] {\n' +
                        '    cursor: not-allowed;\n' +
                        '}\n' +
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

    return AttendanceUI;
});
