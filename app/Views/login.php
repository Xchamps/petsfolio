<!-- <title>OTP Verification</title> -->
<style>
  .error {
    color: red;
    font-weight: 900;
    font-family: Cambria, Cochin, Georgia, Times, 'Times New Roman', serif;
  }
</style>
<div class="hm-bnr-sec glb-bnr-frm grm-pkg-bnr"
  style="background-image: url(<?php echo base_url() ?>assets/images/signbg.jpg);">
</div>
<div class="grmng-pkg-sec">
  <div class="container">
    <div class="grmng-pkg-sec-cnt flex-end">
      <div class="grmng-pkg-sec-lft logn-dog">
        <div>
          <img src="<?php echo base_url() ?>public/assets/images/login-dog.png" alt="img">
        </div>
      </div>
      <div class="grmng-pkg-sec-ryt login">
        <div class="grmng-pkg-frm">
          <div class="login-logo">
            <img src="<?php echo base_url() ?>public/assets/images/Login-logo.png">
          </div>
          <div class="login-hd">
            <h2>Welcome Back</h2>
            <p class="np_3">Login to your account</p>
          </div>
          <form method="post" id="myForm">
            <div class="logo-frm">
              <div>
                <label class="pss_s1">Email</label>
                <input type="email" placeholder="Enter Your Email" name="email" id="email" class="p_1"
                  style="background-image: url('<?php echo base_url() ?>assets/images/lg-icn-1.png');">
              </div>
              <div>
                <label class="pss_s1">Mobile<b>*</b></label>
                <input type="number" placeholder="Enter Your Mobile Number" id="number" name="phone" class="p_1"
                  style="background-image: url('<?php echo base_url() ?>assets/images/lg-icn-2.png');">
                <input type="hidden" name="device_token" value="kjdshsjiouqowuqiowuio" id="device_token">
                <span class="error" id="errNumber"></span>
              </div>
              <div class="logn-btn aldy_acnt">
                <button class="primary_btn btn_cpink" style="border-radius: 15px; width: 100%;">Login</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <div class="otp-popup" style="display: none;">
        <div class="otp-main">
          <div class="cls-otp">
            <a href="#" id="cancelButton">
              <img src="<?php echo base_url() ?>public/assets/images/closing-icon.svg" alt="image" />
            </a>

          </div>
          <div class="otp-icon">
            <img src="<?php echo base_url() ?>public/assets/images/otp-pop-icon.svg" alt="image" />
          </div>
          <p class="hd m-b-20 ps_sl">OTP Verification</p>
          <p id="otpVerificationMessage" class="sm m-b-20 np_1">A 4 digit OTP will be sent via SMS to verify your mobile
            number!</p>
          <form method="post" id="otpForm" class="digit-group">
            <div class="otp-entry m-b-30 flex-center">
              <input type="text" id="otp" name="otp" data-next="digit-4" class="input" maxlength="4"
                minlength="4" />
              <input type="hidden" name="email" id="email">
              <input type="hidden" name="phone" id="phone">
            </div>
            <span class="error" id="otp-error"></span>
            <p class="tm pss_s m-b-20" id="countdowntimer">30</p>
            <p class="sm">Didn’t get a code? <a href="#" class="otp_resend" id="otp_resend"> Click to Resend</a></p>
            <button class="primary_btn" type="submit">Submit</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://kit.fontawesome.com/fcc717cb66.js" crossorigin="anonymous"></script>
<script src="<?php echo base_url() ?>public/assets/js/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function() {

    $('#cancelButton').click(function() {
      window.location.href = '<?php echo base_url() ?>signin';
    });

    function stripTags(input) {
      return input.replace(/<[^>]+>/g, '');
    }

    var downloadTimer;

    function startTimer() {
      clearInterval(downloadTimer);

      var timeleft = 30;
      document.getElementById("countdowntimer").textContent = timeleft;

      downloadTimer = setInterval(function() {
        timeleft--;
        document.getElementById("countdowntimer").textContent = timeleft;
        if (timeleft <= 0)
          clearInterval(downloadTimer);
      }, 1000);
    }

    $('#myForm').on('submit', function(event) {
      event.preventDefault();

      var mobileNumber = $('#number').val();
      var email = $('#email').val();
      var device_token = $('#device_token').val();

      if (!mobileNumber) {
        $('#errNumber').text('Please Enter Mobile Number');
        return;
      }
      if (!isValidMobileNumber(mobileNumber)) {
        $('#errNumber').text('Invalid Mobile Number');
        return;
      }

      var formData = {
        phone: mobileNumber,
        email: email,
        device_token: device_token

      };

      startTimer();

      $.ajax({
        url: '<?= base_url() ?>api/user/login',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
          if (response.status === true) {
            $('.otp-popup').show();
            $('#email').val(email)
            $('#phone').val(mobileNumber)

            $('#otpVerificationMessage').text("A 6 digit OTP has been sent via SMS to verify your mobile number " + response.number);
            sessionStorage.setItem("number", response.mobileNumber);
          } else if (response.status === false) {
            $('#errNumber').text(response.message);
            console.log("Invalid Number");
          }
        },
        error: function(xhr, status, error) {
          console.log('AJAX error:', error);
        }
      });
    });


    function isValidMobileNumber(mobileNumber) {
      return /^(\+\d{1,3}[- ]?)?\d{10}$/.test(mobileNumber);
    }


    $("#otp_resend").click(function() {
      startTimer();
      $('#otpForm')[0].reset();
      $.ajax({
        url: '<?php echo base_url() ?>api/user/login',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
          console.log(response);
          if (response.status === 'success') {
            $('.otp-popup').show();
            $('#otpVerificationMessage').text("A 6 digit OTP has been sent via SMS to verify your mobile number " + response.mobileNumber);
            sessionStorage.setItem("number", response.mobileNumber);
            // resendOTP();
          } else if (response.status === 'false') {
            console.log(response.message);
          }
        },
        error: function(xhr, status, error) {
          console.log('error');
        }
      });
    });


    // Handling the OTP form submission
    $('#otpForm').on('submit', function(e) {
      e.preventDefault();

      var mobileNumber = $('#number').val();
      var email = $('#email').val();
      var otp = $('#otp').val();


      var formData = {
        phone: mobileNumber,
        email: email,
        otp: otp,

      };

      $.ajax({
        url: '<?php echo base_url() ?>api/user/validateOtp',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        dataType: 'json',
        success: function(response) {
          if (response.status === true) {
            var data = response.data;
            localStorage.setItem('jwt_token', data);
            window.location = 'https://www.petsfolio.com/in';
          } else if (response.status === false) {
            $('#otp-error').text(response.message);
          }
        },
        error: function(xhr, status, error) {
          console.log("Error", error);
        }
      });
    });

  });
</script>

<script>
  // $(document).ready(function() {
  //   $('.digit-group').find('input').each(function() {
  //     $(this).attr('maxlength', 1);
  //     $(this).on('keyup', function(e) {
  //       var parent = $($(this).parent());

  //       if (e.keyCode === 8 || e.keyCode === 37) {
  //         var prev = parent.find('input#' + $(this).data('previous'));

  //         if (prev.length) {
  //           $(prev).select();
  //         }
  //       } else if ((e.keyCode >= 48 && e.keyCode <= 57) || (e.keyCode >= 65 && e.keyCode <= 90) || (e.keyCode >= 96 && e.keyCode <= 105) || e.keyCode === 39) {
  //         var next = parent.find('input#' + $(this).data('next'));

  //         if (next.length) {
  //           $(next).select();
  //         } else {
  //           if (parent.data('autosubmit')) {
  //             parent.submit();
  //           }
  //         }
  //       }
  //     });
  //   });
  // });
</script>