jQuery(document).ready(function ($) {
  // Handle free report lead form
  const leadForm = $("#rp-lead-form");
  if (leadForm.length) {
    leadForm.on("submit", function (e) {
      e.preventDefault();
      handleLeadFormSubmission($(this));
    });
  }

  // Handle paid report purchase form
  const purchaseForm = $("#rp-purchase-form");
  if (purchaseForm.length) {
    purchaseForm.on("submit", function (e) {
      e.preventDefault();
      handlePurchaseFormSubmission($(this));
    });
  }

  // Handle email report button
  $("#rp-email-report-btn").on("click", function (e) {
    e.preventDefault();
    handleEmailReport($(this));
  });

  /**
   * Handle free report lead form submission
   */
  function handleLeadFormSubmission(form) {
    const emailInput = form.find('input[name="rp_email"]');
    const email = emailInput.val().trim();
    const errorDiv = $("#rp-form-error");
    const resultDiv = $("#rp-result");

    errorDiv.hide().text("");
    resultDiv.hide().html("");

    // Validation
    if (!validateForm(form, errorDiv)) {
      return;
    }

    const downloadBox = $("#rp-download-box");
    const loader = $("#rp-loader");
    const formContainer = $("#rp-form-container");
    const downloadUrl = downloadBox.data("download-url");

    formContainer.hide();
    loader.show();

    // AJAX call to save lead
    $.ajax({
      url: rp_ajax_object.ajax_url,
      type: "POST",
      data: {
        action: "rp_save_lead",
        email: email,
        first_name: form.find('input[name="rp_first_name"]').val(),
        last_name: form.find('input[name="rp_last_name"]').val(),
        job_title: form.find('input[name="rp_job_title"]').val(),
        company: form.find('input[name="rp_company"]').val(),
        phone: form.find('input[name="rp_phone"]').val(),
        country: form.find('select[name="rp_country"]').val(),
        post_id: rp_ajax_object.post_id,
        nonce: rp_ajax_object.nonce,
      },
      success: function (response) {
        loader.hide();
        if (response.success) {
          const successMessage = "<p>Thank you! Your download is ready.</p>";
          const downloadButton = `<a href="${downloadUrl}" class="rp-download-btn" download>Download Report</a>`;
          resultDiv.html(successMessage + downloadButton).show();
        } else {
          const message =
            response.data || "An unknown error occurred. Please try again.";
          errorDiv.text(message).show();
          formContainer.show();
        }
      },
      error: function () {
        loader.hide();
        errorDiv
          .text("A server error occurred. Please try again later.")
          .show();
        formContainer.show();
      },
    });
  }

  /**
   * Handle paid report purchase form submission
   */
  function handlePurchaseFormSubmission(form) {
    const errorDiv = $("#rp-form-error");
    errorDiv.hide().text("");

    // Validation
    const email = form.find('input[name="rp_email"]').val().trim();
    const firstName = form.find('input[name="rp_first_name"]').val().trim();
    const lastName = form.find('input[name="rp_last_name"]').val().trim();

    if (!firstName || !lastName) {
      errorDiv.text("Please enter your first and last name.").show();
      return;
    }

    if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
      errorDiv.text("Please enter a valid email address.").show();
      form.find('input[name="rp_email"]').focus();
      return;
    }

    // Check if Stripe is configured
    if (!rp_ajax_object.stripe_public_key) {
      errorDiv
        .text(
          "Payment system is not configured. Please contact the site administrator."
        )
        .show();
      return;
    }

    const purchaseContainer = $("#rp-purchase-container");
    const loader = $("#rp-loader");

    purchaseContainer.hide();
    loader.show();

    // Create Stripe checkout session
    $.ajax({
      url: rp_ajax_object.ajax_url,
      type: "POST",
      data: {
        action: "rp_create_checkout_session",
        post_id: rp_ajax_object.post_id,
        email: email,
        first_name: firstName,
        last_name: lastName,
        nonce: rp_ajax_object.nonce,
      },
      success: function (response) {
        loader.hide();
        if (response.success) {
          // Redirect to Stripe Checkout
          window.location.href = response.data.url;
        } else {
          errorDiv
            .text(
              response.data || "Payment processing error. Please try again."
            )
            .show();
          purchaseContainer.show();
        }
      },
      error: function () {
        loader.hide();
        errorDiv
          .text("A server error occurred. Please try again later.")
          .show();
        purchaseContainer.show();
      },
    });
  }

  /**
   * Handle email report button
   */
  function handleEmailReport(button) {
    const email = button.data("email");
    const statusDiv = $("#rp-email-status");

    button.prop("disabled", true).text("Sending...");
    statusDiv.hide().text("").removeClass("rp-email-success rp-email-error");

    $.ajax({
      url: rp_ajax_object.ajax_url,
      type: "POST",
      data: {
        action: "rp_email_report",
        post_id: rp_ajax_object.post_id,
        email: email,
        nonce: rp_ajax_object.nonce,
      },
      success: function (response) {
        button.prop("disabled", false).text("Email Me the Report");
        if (response.success) {
          statusDiv
            .text("✓ Email sent successfully! Check your inbox.")
            .addClass("rp-email-success")
            .show();
        } else {
          statusDiv
            .text("Failed to send email. Please try again.")
            .addClass("rp-email-error")
            .show();
        }
      },
      error: function () {
        button.prop("disabled", false).text("Email Me the Report");
        statusDiv
          .text("A server error occurred. Please try again.")
          .addClass("rp-email-error")
          .show();
      },
    });
  }

  /**
   * Validate form fields
   */
  function validateForm(form, errorDiv) {
    let hasError = false;
    let errorMessage = "";

    // Check required fields
    form.find("[required]").each(function () {
      if ($(this).val() === "" || $(this).val() === null) {
        hasError = true;
        const fieldLabel = $(this).attr("placeholder");
        errorMessage = fieldLabel + " is a required field.";
        $(this).focus();
        return false;
      }
    });

    if (hasError) {
      errorDiv.text(errorMessage).show();
      return false;
    }

    // Email validation
    const emailInput = form.find('input[name="rp_email"]');
    const email = emailInput.val().trim();
    if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
      errorDiv.text("Please enter a valid email address.").show();
      emailInput.focus();
      return false;
    }

    return true;
  }

  /**
   * Check for payment status in URL
   */
  const urlParams = new URLSearchParams(window.location.search);
  const paymentStatus = urlParams.get("payment");

  if (paymentStatus === "success") {
    // Reload without query parameter to show purchased state
    window.history.replaceState({}, document.title, window.location.pathname);
    window.location.reload();
  } else if (paymentStatus === "cancelled") {
    // Show cancellation message
    $("#rp-form-error")
      .text(
        "Payment was cancelled. Please try again if you wish to purchase this report."
      )
      .show();
    window.history.replaceState({}, document.title, window.location.pathname);
  }
});
