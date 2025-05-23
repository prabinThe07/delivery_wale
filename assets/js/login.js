document.addEventListener("DOMContentLoaded", () => {
    // User type selection
    const userTypeOptions = document.querySelectorAll(".user-type-option")
    const userTypeInput = document.getElementById("user-type")
    const selectedRoleInfo = document.getElementById("selected-role-info")
    const loginBtn = document.getElementById("login-btn")
    const branchSelectionContainer = document.getElementById("branch-selection-container")
    const branchSelect = document.getElementById("branch_id")
  
    userTypeOptions.forEach((option) => {
      option.addEventListener("click", function () {
        // Remove selected class from all options
        userTypeOptions.forEach((opt) => opt.classList.remove("selected"))
  
        // Add selected class to clicked option
        this.classList.add("selected")
  
        // Get the user type from data attribute
        const userType = this.getAttribute("data-type")
  
        // Set the hidden input value
        userTypeInput.value = userType
  
        // Show/hide branch selection based on role
        if (userType === "branch_admin" || userType === "delivery_user") {
          branchSelectionContainer.classList.remove("d-none")
          branchSelect.setAttribute("required", "required")
        } else {
          branchSelectionContainer.classList.add("d-none")
          branchSelect.removeAttribute("required")
        }
  
        // Update the selected role info text
        let roleText = ""
        switch (userType) {
          case "super_admin":
            roleText = "Hello Super Admin! Please fill out the form below to get started."
            break
          case "branch_admin":
            roleText = "Hello Branch Admin! Please select your branch and fill out the form below."
            break
          case "delivery_user":
            roleText = "Hello Delivery User! Please select your branch and fill out the form below."
            break
        }
  
        selectedRoleInfo.textContent = roleText
        selectedRoleInfo.classList.remove("text-danger")
        selectedRoleInfo.classList.add("text-muted")
  
        // Enable the login button
        loginBtn.disabled = false
      })
    })
  
    // Branch selection validation
    branchSelect.addEventListener("change", function () {
      if (this.value) {
        this.classList.remove("is-invalid")
        this.classList.add("is-valid")
      } else {
        this.classList.remove("is-valid")
        this.classList.add("is-invalid")
      }
    })
  
    // Username validation with check mark
    const usernameInput = document.getElementById("username")
    const usernameCheck = document.getElementById("username-check")
  
    usernameInput.addEventListener("input", function () {
      if (this.value.trim().length > 0) {
        usernameCheck.classList.remove("d-none")
      } else {
        usernameCheck.classList.add("d-none")
      }
    })
  
    // Initialize username check if value exists
    if (usernameInput.value.trim().length > 0) {
      usernameCheck.classList.remove("d-none")
    }
  
    // Password toggle visibility
    const passwordToggle = document.querySelector(".password-toggle")
    const passwordInput = document.getElementById("password")
  
    passwordToggle.addEventListener("click", function () {
      const icon = this.querySelector("i")
  
      if (passwordInput.type === "password") {
        passwordInput.type = "text"
        icon.classList.remove("fa-eye-slash")
        icon.classList.add("fa-eye")
      } else {
        passwordInput.type = "password"
        icon.classList.remove("fa-eye")
        icon.classList.add("fa-eye-slash")
      }
    })
  
    // Form submission
    const loginForm = document.getElementById("login-form")
    const loginSpinner = document.getElementById("login-spinner")
    const loginBtnText = document.getElementById("login-btn-text")
  
    loginForm.addEventListener("submit", (e) => {
      // Check if user type is selected
      if (!userTypeInput.value) {
        e.preventDefault()
        selectedRoleInfo.textContent = "Please select an account type to continue"
        selectedRoleInfo.classList.remove("text-muted")
        selectedRoleInfo.classList.add("text-danger")
        return false
      }
  
      // Check if branch is selected for branch_admin and delivery_user
      if ((userTypeInput.value === "branch_admin" || userTypeInput.value === "delivery_user") && !branchSelect.value) {
        e.preventDefault()
        branchSelect.classList.add("is-invalid")
        return false
      }
  
      // Basic form validation
      if (!usernameInput.value.trim() || !passwordInput.value) {
        e.preventDefault()
        return false
      }
  
      // Show loading state
      loginBtn.disabled = true
      loginSpinner.classList.remove("d-none")
      loginBtnText.textContent = "Logging in..."
    })
  
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll(".alert")
    if (alerts.length > 0) {
      setTimeout(() => {
        alerts.forEach((alert) => {
          const closeBtn = alert.querySelector(".btn-close")
          if (closeBtn) {
            closeBtn.click()
          }
        })
      }, 5000)
    }
  })
  