<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has super_admin role
check_role('super_admin');

// Include database connection
require_once '../config/db_connect.php';

// Initialize variables
$name = $address = $city = $state = $zip_code = $phone = $email = $manager_name = '';
$status = 'active';
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $manager_name = trim($_POST['manager_name'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($name)) {
        $errors['name'] = 'Branch name is required';
    }
    
    if (empty($address)) {
        $errors['address'] = 'Address is required';
    }
    
    if (empty($city)) {
        $errors['city'] = 'City is required';
    }
    
    if (empty($state)) {
        $errors['state'] = 'State is required';
    }
    
    if (empty($zip_code)) {
        $errors['zip_code'] = 'ZIP code is required';
    }
    
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO branches (name, address, city, state, zip_code, phone, email, manager_name, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name, $address, $city, $state, $zip_code, $phone, $email, $manager_name, $status
            ]);
            
            // Set success message and redirect
            $_SESSION['success_message'] = 'Branch added successfully!';
            header('Location: branches.php');
            exit;
            
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-plus-circle me-2"></i>Add New Branch</h2>
            <p class="text-muted">Create a new branch location in the system</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="branches.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Branches
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="add_branch.php" method="post" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Branch Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                   id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" 
                                      id="address" name="address" rows="3" required><?php echo htmlspecialchars($address); ?></textarea>
                            <?php if (isset($errors['address'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-5">
                                <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['city']) ? 'is-invalid' : ''; ?>" 
                                       id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" required>
                                <?php if (isset($errors['city'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['city']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label for="state" class="form-label">State <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['state']) ? 'is-invalid' : ''; ?>" 
                                       id="state" name="state" value="<?php echo htmlspecialchars($state); ?>" required>
                                <?php if (isset($errors['state'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['state']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label for="zip_code" class="form-label">ZIP <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['zip_code']) ? 'is-invalid' : ''; ?>" 
                                       id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($zip_code); ?>" required>
                                <?php if (isset($errors['zip_code'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['zip_code']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                   id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" required>
                            <?php if (isset($errors['phone'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="manager_name" class="form-label">Branch Manager</label>
                            <input type="text" class="form-control" id="manager_name" name="manager_name" 
                                   value="<?php echo htmlspecialchars($manager_name); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_active" 
                                       value="active" <?php echo $status === 'active' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status_active">
                                    Active
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status" id="status_inactive" 
                                       value="inactive" <?php echo $status === 'inactive' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status_inactive">
                                    Inactive
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="d-flex justify-content-end">
                    <button type="reset" class="btn btn-secondary me-2">Reset</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Branch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Form validation
    (function() {
        'use strict';
        
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        var forms = document.querySelectorAll('.needs-validation');
        
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
    })();
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
