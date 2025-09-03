jQuery(document).ready(function($) {
    // Initialize the WordPress color picker
    $('.rp-color-picker').wpColorPicker({
        // A callback function that fires when the color is changed.
        change: function(event, ui) {
            // Get the new color
            var newColor = ui.color.toString();
            // Get the ID of the input field's name attribute
            var inputName = $(this).attr('name');

            // Update the live preview
            updatePreview(inputName, newColor);
        },
        // A callback function that fires when the color picker is cleared.
        clear: function() {
            var inputName = $(this).attr('name');
            var defaultColor = $(this).data('default-color');
            updatePreview(inputName, defaultColor);
        }
    });

    function updatePreview(inputName, color) {
        var previewBox = $('#rp-preview-box');
        
        if (inputName.includes('[background_color]')) {
            previewBox.css('background-color', color);
        } else if (inputName.includes('[border_color]')) {
            previewBox.css('border-color', color);
        } else if (inputName.includes('[title_color]')) {
            previewBox.find('#rp-preview-title').css('color', color);
        } else if (inputName.includes('[text_color]')) {
            previewBox.find('#rp-preview-text').css('color', color);
            previewBox.find('label').css('color', color);
        } else if (inputName.includes('[primary_color]')) {
            previewBox.find('#rp-preview-button').css('background-color', color);
        } else if (inputName.includes('[link_color]')) {
            previewBox.find('#rp-preview-link').css('color', color);
        }
    }

    // Trigger initial preview update on page load
    $('.rp-color-picker').each(function() {
        var inputName = $(this).attr('name');
        var color = $(this).val();
        updatePreview(inputName, color);
    });

    // Bonus: Update button hover color in preview
    $('input[name="rp_color_settings[secondary_color]"]').on('change', function() {
        var hoverColor = $(this).val();
        var primaryColor = $('input[name="rp_color_settings[primary_color]"]').val();
        
        // Add a style tag to the head to control the preview button's hover state
        $('#rp-preview-hover-style').remove(); // Remove old style tag if it exists
        $('<style id="rp-preview-hover-style">#rp-preview-button:hover { background-color: ' + hoverColor + ' !important; }</style>').appendTo('head');
    }).trigger('change'); // Trigger on load

     $('input[name="rp_color_settings[primary_color]"]').on('change', function(){
         $('input[name="rp_color_settings[secondary_color]"]').trigger('change');
     });
});

