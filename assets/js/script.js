$(document).ready(() => {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))
  
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    popoverTriggerList.map((popoverTriggerEl) => new bootstrap.Popover(popoverTriggerEl))
  
    // Confirm delete actions
    $(".confirm-delete").on("click", (e) => {
      if (!confirm("Are you sure you want to delete this item? This action cannot be undone.")) {
        e.preventDefault()
      }
    })
  
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      $(".alert-auto-dismiss").fadeOut("slow")
    }, 5000)
  
    // Toggle sidebar on mobile
    $("#sidebarToggle").on("click", () => {
      $(".sidebar").toggleClass("show")
    })
  
    // Datepicker initialization (if jQuery UI is included)
    if ($.fn.datepicker) {
      $(".datepicker").datepicker({
        format: "yyyy-mm-dd",
        autoclose: true,
        todayHighlight: true,
      })
    }
  
    // DataTables initialization (if DataTables is included)
    if ($.fn.DataTable) {
      $(".data-table").DataTable({
        responsive: true,
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Search...",
        },
      })
    }
  })
  