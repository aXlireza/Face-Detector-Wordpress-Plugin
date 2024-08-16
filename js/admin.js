jQuery(document).ready(function ($) {
  $('#isp-add-message').on('click', function () {
      $('#isp-analysis-messages-container').append('<div class="isp-message"><textarea name="isp_analysis_messages[]"></textarea><button type="button" class="isp-remove-message">Remove</button></div>');
  });

  $(document).on('click', '.isp-remove-message', function () {
      $(this).closest('.isp-message').remove();
  });
});
