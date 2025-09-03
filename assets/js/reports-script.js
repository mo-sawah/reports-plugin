jQuery(document).ready(function($) {
    const form = $('#rp-lead-form');

    if (form.length) {
        form.on('submit', function(e) {
            e.preventDefault();

            const emailInput = form.find('input[name="rp_email"]');
            const email = emailInput.val().trim();
            const errorDiv = $('#rp-form-error');
            const resultDiv = $('#rp-result');

            errorDiv.hide().text('');
            resultDiv.hide().html('');

            // Basic validation
            let hasError = false;
            let errorMessage = '';

            // Check required fields
            form.find('[required]').each(function() {
                if ($(this).val() === '' || $(this).val() === null) {
                    hasError = true;
                    const fieldLabel = $(this).attr('placeholder');
                    errorMessage = fieldLabel + ' is a required field.';
                    $(this).focus();
                    return false; // break the loop
                }
            });

            if (hasError) {
                errorDiv.text(errorMessage).show();
                return;
            }
            
            // Email format validation
            if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                errorDiv.text('Please enter a valid email address.').show();
                emailInput.focus();
                return;
            }

            const downloadBox = $('#rp-download-box');
            const loader = $('#rp-loader');
            const formContainer = $('#rp-form-container');
            const downloadUrl = downloadBox.data('download-url');

            formContainer.hide();
            loader.show();

            // AJAX call to save the lead
            $.ajax({
                url: rp_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'rp_save_lead',
                    email: email,
                    first_name: form.find('input[name="rp_first_name"]').val(),
                    last_name: form.find('input[name="rp_last_name"]').val(),
                    job_title: form.find('input[name="rp_job_title"]').val(),
                    company: form.find('input[name="rp_company"]').val(),
                    phone: form.find('input[name="rp_phone"]').val(),
                    country: form.find('select[name="rp_country"]').val(),
                    post_id: rp_ajax_object.post_id,
                    nonce: rp_ajax_object.nonce
                },
                success: function(response) {
                    loader.hide();
                    if (response.success) {
                        const successMessage = '<p>Thank you! Your download is ready.</p>';
                        const downloadButton = `<a href="${downloadUrl}" class="rp-download-btn" download>Download Report</a>`;
                        resultDiv.html(successMessage + downloadButton).show();
                    } else {
                        const message = response.data || 'An unknown error occurred. Please try again.';
                        errorDiv.text(message).show();
                        formContainer.show(); // Show form again on error
                    }
                },
                error: function() {
                    loader.hide();
                    errorDiv.text('A server error occurred. Please try again later.').show();
                    formContainer.show(); // Show form again on error
                }
            });
        });
    }
});

