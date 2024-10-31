
jQuery(document).ready(function($) {
    function toggleSections() {
        var selectedService = $('input[name="opengraphmagic_service_options[service_type]"]:checked').val();
        if (selectedService === 'screenshot_one') {
            $('#screenshotone-section').closest('tr').show();
            $('#pikwy-section').closest('tr').hide();
        } else if (selectedService === 'pikwy') {
            $('#screenshotone-section').closest('tr').hide();
            $('#pikwy-section').closest('tr').show();
        }
    }

    // Initial call to set the correct state
    toggleSections();

    // Bind change event
    $('input[name="opengraphmagic_service_options[service_type]"]').change(function() {
        toggleSections();
    });
});
