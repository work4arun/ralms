/**
 * Slot booking UI enhancements for mod_aale.
 * Handles slot mode switching, form validation, loading states, and confirmation dialogs.
 *
 * @module mod_aale/booking
 * @copyright 2024 AALE Contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    var BookingUI = {

        /**
         * Initialize booking UI enhancements.
         *
         * @param {Object} params Configuration parameters
         * @param {string} params.slotmode 'cpa' or 'class' - determines which selectors to show
         * @param {string} params.formSelector CSS selector for the booking form
         * @param {string} params.bookButtonSelector CSS selector for the book button
         * @param {string} params.cancelButtonSelector CSS selector for the cancel button
         * @param {string} params.levelSelectorContainer CSS selector for level/track container
         */
        init: function(params) {
            var self = this;

            // Validate required parameters
            if (!params || !params.formSelector) {
                console.warn('mod_aale/booking: Missing required parameters');
                return;
            }

            var $form = $(params.formSelector);
            if ($form.length === 0) {
                console.warn('mod_aale/booking: Form not found with selector: ' + params.formSelector);
                return;
            }

            // Initialize features
            this.setupSlotModeToggle(params);
            this.setupFormValidation($form, params);
            this.setupBookingConfirmation($form, params);
            this.setupCancellationConfirmation($form, params);
            this.setupLoadingStates($form, params);
            this.setupCardAnimations($form);
        },

        /**
         * Show/hide level and track selectors based on slot mode.
         *
         * @private
         * @param {Object} params Configuration parameters
         */
        setupSlotModeToggle: function(params) {
            var $levelContainer = $(params.levelSelectorContainer || '[data-level-track-selectors]');

            if ($levelContainer.length === 0) {
                return;
            }

            // Initial state based on slotmode
            if (params.slotmode === 'cpa') {
                $levelContainer.removeClass('hidden').fadeIn(200);
            } else {
                $levelContainer.addClass('hidden').fadeOut(200);
            }

            // Listen for mode changes if there's a hidden mode field
            var $modeField = $('[name="slotmode"]');
            if ($modeField.length > 0) {
                $modeField.on('change', function() {
                    var mode = $(this).val();
                    if (mode === 'cpa') {
                        $levelContainer.removeClass('hidden').fadeIn(200);
                    } else {
                        $levelContainer.addClass('hidden').fadeOut(200);
                    }
                });
            }
        },

        /**
         * Validate booking form before submission.
         * For CPA mode, require level and track to be selected.
         *
         * @private
         * @param {jQuery} $form The booking form
         * @param {Object} params Configuration parameters
         */
        setupFormValidation: function($form, params) {
            var self = this;

            $form.on('submit', function(e) {
                // Skip validation if this is a cancel action
                if ($form.find('[name="action"]').val() === 'cancel') {
                    return true;
                }

                var mode = params.slotmode || $form.find('[name="slotmode"]').val();

                if (mode === 'cpa') {
                    var $levelSelect = $form.find('[name="level"]');
                    var $trackSelect = $form.find('[name="track"]');

                    var levelValid = $levelSelect.length === 0 || $levelSelect.val() !== '';
                    var trackValid = $trackSelect.length === 0 || $trackSelect.val() !== '';

                    if (!levelValid || !trackValid) {
                        e.preventDefault();
                        self.showValidationError('Level and Track are required for CPA slots');
                        return false;
                    }
                }

                return true;
            });
        },

        /**
         * Show validation error message.
         *
         * @private
         * @param {string} message Error message to display
         */
        showValidationError: function(message) {
            Notification.addNotification({
                message: message,
                type: 'error'
            });
        },

        /**
         * Setup confirmation dialog before booking.
         *
         * @private
         * @param {jQuery} $form The booking form
         * @param {Object} params Configuration parameters
         */
        setupBookingConfirmation: function($form, params) {
            var $bookButton = $form.find(params.bookButtonSelector || '[data-action="book"]');

            if ($bookButton.length === 0) {
                return;
            }

            $bookButton.on('click', function(e) {
                e.preventDefault();

                var self = this;
                var slotName = $form.find('[data-slot-name]').text() || 'this slot';
                var level = $form.find('[name="level"]').val() || 'N/A';
                var track = $form.find('[name="track"]').val() || 'N/A';

                var confirmMessage = 'Please confirm your booking:\n\n' +
                    'Slot: ' + slotName + '\n';

                if (level !== 'N/A') {
                    confirmMessage += 'Level: ' + level + '\n';
                }
                if (track !== 'N/A') {
                    confirmMessage += 'Track: ' + track + '\n';
                }

                confirmMessage += '\nThis action cannot be easily undone.';

                Notification.confirm(
                    confirmMessage,
                    'Confirm Booking',
                    function() {
                        // Confirm callback - submit the form
                        $form.find('[name="action"]').val('book');
                        $form.submit();
                    },
                    null
                );
            });
        },

        /**
         * Setup confirmation dialog before cancellation.
         *
         * @private
         * @param {jQuery} $form The booking form
         * @param {Object} params Configuration parameters
         */
        setupCancellationConfirmation: function($form, params) {
            var $cancelButton = $form.find(params.cancelButtonSelector || '[data-action="cancel"]');

            if ($cancelButton.length === 0) {
                return;
            }

            $cancelButton.on('click', function(e) {
                e.preventDefault();

                var confirmMessage = 'Are you sure you want to cancel this booking?\n\nThis action cannot be undone.';

                Notification.confirm(
                    confirmMessage,
                    'Confirm Cancellation',
                    function() {
                        // Confirm callback - submit the cancel action
                        $form.find('[name="action"]').val('cancel');
                        $form.submit();
                    },
                    null
                );
            });
        },

        /**
         * Setup loading state for submit buttons.
         *
         * @private
         * @param {jQuery} $form The booking form
         * @param {Object} params Configuration parameters
         */
        setupLoadingStates: function($form, params) {
            $form.on('submit', function() {
                var $buttons = $form.find('button[type="submit"]');

                $buttons.each(function() {
                    var $btn = $(this);
                    var originalText = $btn.text();
                    var originalHtml = $btn.html();

                    // Show loading spinner
                    $btn.prop('disabled', true);
                    $btn.html('<span class="spinner-border spinner-border-sm mr-2"></span>' + originalText);

                    // Store original state to restore on failure (via error handler in form submission)
                    $btn.data('original-html', originalHtml);
                    $btn.data('original-text', originalText);
                });
            });

            // Restore button state if form returns with errors
            if ($form.find('.error, .alert-danger').length > 0) {
                $form.find('button[type="submit"]').each(function() {
                    var $btn = $(this);
                    if ($btn.data('original-html')) {
                        $btn.html($btn.data('original-html'));
                        $btn.prop('disabled', false);
                    }
                });
            }
        },

        /**
         * Setup smooth card animations on success.
         *
         * @private
         * @param {jQuery} $form The booking form
         */
        setupCardAnimations: function($form) {
            // Add CSS transition class to booking card if not already present
            var $card = $form.closest('[data-booking-card], .card');

            if ($card.length === 0) {
                return;
            }

            // Inject animation styles if not present
            if (!$('#aale-booking-animations').length) {
                var $style = $('<style id="aale-booking-animations">')
                    .text(
                        '.booking-card-fade-in {\n' +
                        '    animation: fadeInSlideDown 0.4s ease-out forwards;\n' +
                        '}\n' +
                        '@keyframes fadeInSlideDown {\n' +
                        '    from {\n' +
                        '        opacity: 0;\n' +
                        '        transform: translateY(-10px);\n' +
                        '    }\n' +
                        '    to {\n' +
                        '        opacity: 1;\n' +
                        '        transform: translateY(0);\n' +
                        '    }\n' +
                        '}\n' +
                        '.booking-success-flash {\n' +
                        '    animation: successFlash 0.6s ease-out;\n' +
                        '}\n' +
                        '@keyframes successFlash {\n' +
                        '    0% { background-color: #d4edda; }\n' +
                        '    100% { background-color: transparent; }\n' +
                        '}'
                    );
                $('head').append($style);
            }

            // Apply animation on page load
            $card.addClass('booking-card-fade-in');

            // Look for success message and flash the card
            var $successMsg = $form.find('[data-booking-success], .alert-success');
            if ($successMsg.length > 0) {
                $card.addClass('booking-success-flash');
            }
        }
    };

    return BookingUI;
});
