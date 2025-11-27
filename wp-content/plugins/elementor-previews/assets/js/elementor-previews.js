/**
 * Elementor Template Previews - JavaScript
 * Version: 1.0.0
 */

(function ($) {
    'use strict';

    // Wait for Elementor to be ready
    $(window).on('elementor:init', function () {
        initElementorPreviews();
    });

    // Also try to init when document is ready (fallback)
    $(document).ready(function () {
        setTimeout(initElementorPreviews, 1000);
    });

    function initElementorPreviews() {
        // Remove the default template display
        if ($('#tmpl-elementor-template-library-template-local').length) {
            $('#tmpl-elementor-template-library-template-local').remove();
        }

        // Add our custom template
        addCustomTemplate();

        // Handle dropdown menu
        handleMoreActions();
    }

    function addCustomTemplate() {
        // Check if already added
        if ($('#tmpl-elementor-template-library-template-local').length) {
            return;
        }

        // Create our custom template
        var customTemplate = `
            <script type="text/template" id="tmpl-elementor-template-library-template-local">
                <div class="template-thumbnail" data-template-id="{{ template_id }}">
                    <# if( thumbnail ) { #>
                        <img src="{{{ thumbnail }}}" alt="{{{ title }}}">
                    <# } else { #>
                        <div class="no-preview">
                            <i class="eicon-document-file" aria-hidden="true"></i>
                        </div>
                    <# } #>
                </div>

                <div class="template-content">
                    <div class="elementor-template-library-template-name">{{{ title }}}</div>
                </div>

                <div class="elementor-template-library-template-controls">
                    <button class="elementor-template-library-template-action elementor-template-library-template-insert elementor-button elementor-button-success">
                        <i class="eicon-file-download" aria-hidden="true"></i>
                        <span class="elementor-button-title">${elementorPreviews.strings.insert}</span>
                    </button>

                    <div class="ep-more-toggle">
                        <i class="eicon-ellipsis-h" aria-hidden="true"></i>
                        <span class="elementor-screen-only">${elementorPreviews.strings.more_actions}</span>
                    </div>

                    <div class="ep-more-menu">
                        <div class="ep-menu-item ep-menu-edit">
                            <a href="${elementorPreviews.adminUrl}post.php?post={{ template_id }}&action=edit" target="_blank">
                                <i class="eicon-pencil" aria-hidden="true"></i>
                                <span>${elementorPreviews.strings.edit}</span>
                            </a>
                        </div>
                        <div class="ep-menu-item ep-menu-export">
                            <a href="{{ export_link }}">
                                <i class="eicon-sign-out" aria-hidden="true"></i>
                                <span>${elementorPreviews.strings.export}</span>
                            </a>
                        </div>
                        <div class="ep-menu-item ep-menu-delete">
                            <i class="eicon-trash" aria-hidden="true"></i>
                            <span>${elementorPreviews.strings.delete}</span>
                        </div>
                    </div>
                </div>
            </script>
        `;

        // Append the custom template to the document
        $('body').append(customTemplate);
    }

    function handleMoreActions() {
        // Remove any existing handlers first to avoid duplicates
        $(document).off('click.epMoreToggle');
        $(document).off('click.epCloseMenu');

        // Handle more actions dropdown toggle
        $(document).on('click.epMoreToggle', '.ep-more-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $dropdown = $(this).siblings('.ep-more-menu');

            // Close all other dropdowns
            $('.ep-more-menu').removeClass('show');

            // Toggle current dropdown
            $dropdown.toggleClass('show');
        });

        // Close dropdown when clicking outside
        $(document).on('click.epCloseMenu', function (e) {
            if (!$(e.target).closest('.ep-more-toggle, .ep-more-menu').length) {
                $('.ep-more-menu').removeClass('show');
            }
        });

        // Handle delete confirmation
        $(document).on('click', '.ep-menu-delete', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this template?')) {
                console.log('Template deletion confirmed');
            }
        });
    }

})(jQuery);
