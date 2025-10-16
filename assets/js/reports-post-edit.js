jQuery(document).ready(function ($) {
  let mediaUploader;

  // File upload button
  $("#rp_upload_file_button").on("click", function (e) {
    e.preventDefault();

    if (mediaUploader) {
      mediaUploader.open();
      return;
    }

    mediaUploader = wp.media.frames.file_frame = wp.media({
      title: "Choose Report File",
      button: {
        text: "Choose File",
      },
      multiple: false,
    });

    mediaUploader.on("select", function () {
      const attachment = mediaUploader
        .state()
        .get("selection")
        .first()
        .toJSON();
      $("#rp_download_link").val(attachment.url);
      $("#rp_remove_file_button").show();
    });

    mediaUploader.open();
  });

  // Remove file button
  $("#rp_remove_file_button").on("click", function (e) {
    e.preventDefault();
    $("#rp_download_link").val("");
    $(this).hide();
  });

  // Toggle paid fields visibility
  $("#rp_is_paid").on("change", function () {
    if ($(this).is(":checked")) {
      $("#rp_paid_fields").addClass("active");
    } else {
      $("#rp_paid_fields").removeClass("active");
    }
  });

  // Initialize paid fields state on page load
  if ($("#rp_is_paid").is(":checked")) {
    $("#rp_paid_fields").addClass("active");
  }
});
