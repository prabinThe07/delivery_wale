<?php
// Include session check
require_once '../includes/session_check.php';

// Check if user has super_admin role
check_role('super_admin');

// Include database connection
require_once '../config/db_connect.php';

// Initialize variables
$username = $email = $full_name = $role = $phone = $address = '';
$branch_id = null;
$status = 'active';
$errors = [];

// Get all branches for dropdown
try {
    $stmt = $pdo->query("SELECT id, name, city, state FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $branches = [];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } else {
        // Check if username already exists
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $errors['username'] = 'Username already exists';
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    } else {
        // Check if email already exists
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $errors['email'] = 'Email already exists';
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
    
    if (empty($full_name)) {
        $errors['full_name'] = 'Full name is required';
    }
    
    if (empty($role)) {
        $errors['role'] = 'Role is required';
    }
    
    // Branch is required for branch_admin and delivery_user
    if (($role === 'branch_admin' || $role === 'delivery_user') && empty($branch_id)) {
        $errors['branch_id'] = 'Branch is required for this role';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, email, full_name, role, branch_id, phone, address, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $username, $hashed_password, $email, $full_name, $role, $branch_id, $phone, $address, $status
            ]);
            
            // Set success message and redirect
            $_SESSION['success_message'] = 'User added successfully!';
            header('Location: manage_users.php');
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
            <h2><i class="fas fa-user-plus me-2"></i>Add New User</h2>
            <p class="text-muted">Create a new user account in the system</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="manage_users.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Users
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
            <form action="add_user.php" method="post" class="needs-validation" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                   id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
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
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                   id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                            <?php if (isset($errors['full_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" 
                                    id="role" name="role" required>
                                <option value="" <?php echo empty($role) ? 'selected' : ''; ?>>-- Select Role --</option>
                                <option value="super_admin" <?php echo $role === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                <option value="branch_admin" <?php echo $role === 'branch_admin' ? 'selected' : ''; ?>>Branch Admin</option>
                                <option value="delivery_user" <?php echo $role === 'delivery_user' ? 'selected' : ''; ?>>Delivery User</option>
                            </select>
                            <?php if (isset($errors['role'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['role']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3" id="branch-container" style="<?php echo ($role === 'branch_admin' || $role === 'delivery_user') ? '' : 'display: none;'; ?>">
                            <label for="branch_id" class="form-label">Branch <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['branch_id']) ? 'is-invalid' : ''; ?>" 
                                    id="branch_id" name="branch_id">
                                <option value="">-- Select Branch --</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>" <?php echo $branch_id == $branch['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch['name'] . ' (' . $branch['city'] . ', ' . $branch['state'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['branch_id'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['branch_id']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($phone); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($address); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                       id="password" name="password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback d-block"><?php echo $errors['password']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                       id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback d-block"><?php echo $errors['confirm_password']; ?></div>
                            <?php endif; ?>
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
                        <i class="fas fa-save me-2"></i>Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Show/hide branch selection based on role
    document.getElementById('role').addEventListener('change', function() {
        const branchContainer = document.getElementById('branch-container');
        const branchSelect = document.getElementById('branch_id');
        
        if (this.value === 'branch_admin' || this.value === 'delivery_user') {
            branchContainer.style.display = 'block';
            branchSelect.setAttribute('required', 'required');
        } else {
            branchContainer.style.display = 'none';
            branchSelect.removeAttribute('required');
        }
    });
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
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
