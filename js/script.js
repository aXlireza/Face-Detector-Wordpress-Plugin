import {
    FaceDetector,
    FilesetResolver,
} from "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.0"

jQuery(document).ready(function ($) {
    $('#isp-form').on('submit', async function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('action', 'isp_image_upload');
        $('#isp-scanning').show();
        $('#isp-result').hide();
        $('#isp-user-info').hide();

        let faceDetector;
        let runningMode = "IMAGE";
        const vision = await FilesetResolver.forVisionTasks(
            "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.0/wasm"
        );
        faceDetector = await FaceDetector.createFromOptions(vision, {
            baseOptions: {
                modelAssetPath: `https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite`,
                delegate: "GPU"
            },
            runningMode: runningMode
        });

        // faceDetector.detect returns a promise which, when resolved, is an array of Detection faces
        const imageElement = document.getElementById('uploaded-image'); // Get the DOM element
        console.log(imageElement);
        
        const detections = await faceDetector.detect(imageElement);
        if (detections.detections.length === 0) {
            alert("No Face Detected, Try again.")
        } else {
            console.log(detections);
            
            $.ajax({
                url: isp_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    setTimeout(function () { // Delay the response handling by 5 seconds
                        $('#isp-scanning').hide();
                        var data = JSON.parse(response);
                        if (data.success) {
                            $('#isp-analysis').html(data.message);
                            $('#image-url').val(data.image_url); // Set the hidden input field with the image URL
                            $('#isp-result').show();
                            $('#isp-form label').hide(); // Hide the file input button
                            $('#isp-user-info').show(); // Show the user info form
                        } else {
                            alert(data.message);
                        }
                    }, 5000); // Ensure the scanning effect lasts at least 5 seconds
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    setTimeout(function () { // Delay the error handling by 5 seconds
                        $('#isp-scanning').hide();
                        alert('Error: ' + textStatus + ' - ' + errorThrown);
                    }, 5000);
                }
            });
        }
  });

  $('#isp-image').on('change', function () {
      $('#isp-form').trigger('submit');
      $('#isp-scanning').show();
      $('#uploaded-image').attr('src', URL.createObjectURL(this.files[0]));
      $('#uploaded-image-container').show();
  });

  $('#isp-user-info').on('submit', function (e) {
      e.preventDefault();
      var formData = $(this).serialize() + '&action=isp_user_info';
      $.ajax({
          url: isp_ajax.ajax_url,
          type: 'POST',
          data: formData,
          success: function (response) {
              var data = JSON.parse(response);
              if (data.success) {
                  alert(data.message);
              } else {
                  alert(data.message);
              }
          },
          error: function (jqXHR, textStatus, errorThrown) {
              alert('Error: ' + textStatus + ' - ' + errorThrown);
          }
      });
  });
});
