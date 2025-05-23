$(document).ready(() => {
    // User type selection
    $(".user-type-option").click(function () {
      $(".user-type-option").removeClass("selected")
      $(this).addClass("selected")
  
      const userType = $(this).data("type")
      $("#user-type").val(userType)
  
      // Update the selected role info text
      let roleText = ""
      switch (userType) {
        case "super_admin":
          roleText = "Hello Super Admin! Please fill out the form below to get started."
          break
        case "branch_admin":
          roleText = "Hello Branch Admin! Please fill out the form below to get started."
          break
        case "delivery_user":
          roleText = "Hello Delivery User! Please fill out the form below to get started."
          break
      }
  
      $("#selected-role-info").text(roleText)
  
      // Enable the login button if a role is selected
      $("#login-btn").prop("disabled", false)
    })
  
    // Username validation with check mark
    $("#username").on("blur", function () {
      const username = $(this).val().trim()
      if (username.length > 0) {
        // In a real app, you might want to check if the username exists
        // For now, just show the check mark
        $("#username-check").removeClass("d-none")
      } else {
        $("#username-check").addClass("d-none")
      }
    })
  
    // Form submission with AJAX
    $("#login-form").on("submit", (e) => {
      e.preventDefault()
  
      // Basic form validation
      const username = $("#username").val().trim()
      const password = $("#password").val()
      const userType = $("#user-type").val()
  
      if (!username || !password || !userType) {
        $("#login-error").removeClass("d-none").text("Please fill in all fields and select an account type.")
        return
      }
  
      // Show loading state
      $("#login-btn").html(
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Logging in...',
      )
      $("#login-btn").prop("disabled", true)
  
      // AJAX request to the server
      $.ajax({
        url: "login.php",
        type: "POST",
        data: {
          action: "login",
          username: username,
          password: password,
          user_type: userType,
        },
        dataType: "json",
        success: (response) => {
          if (response.success) {
            // Show success message
            $("#login-error").removeClass("d-none alert-danger").addClass("alert-success").text(response.message)
  
            // Redirect to dashboard
            setTimeout(() => {
              window.location.href = response.redirect
            }, 1000)
          } else {
            // Show error message
            $("#login-error").removeClass("d-none").text(response.message)
            $("#login-btn").html("LOGIN")
            $("#login-btn").prop("disabled", false)
          }
        },
        error: () => {
          $("#login-error").removeClass("d-none").text("An error occurred. Please try again.")
          $("#login-btn").html("LOGIN")
          $("#login-btn").prop("disabled", false)
        },
      })
    })
  })
  