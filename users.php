<?php
require_once 'includes/auth.php';
require_role(['admin']); // Users management is admin-only
require_once 'connect.php';

$deleteError = null;
$deleteSuccess = false;
$addError = null;
$addSuccess = false;

/*Handle Add User submission*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $fullName = trim($_POST['fullName'] ?? '');
    $userName = trim($_POST['userName'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';
    $status   = $_POST['status'] ?? 'active';

    if ($fullName === '' || $userName === '' || $email === '' || $password === '') {
        $addError = "Name, Username, Email, and Password are all required.";
    } elseif (!in_array($role, ['admin', 'pharmacist', 'staff'])) {
        $addError = "Please select a valid role.";
    } else {
        try {
            // Hash password securely with PASSWORD_DEFAULT using PHP's password_hash()
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (fullName, userName, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fullName, $userName, $email, $phone, $hashedPassword, $role, $status]);
            $addSuccess = true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $addError = "That username or email is already taken.";
            } else {
                $addError = "Could not create user.";
            }
        }
    }
}

/*manage delete request*/
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];

    if ($deleteId === (int)$_SESSION['userId']) {
        $deleteError = "You can't delete your own account while logged in as it.";
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE userId = ?");
            $stmt->execute([$deleteId]);
            $deleteSuccess = true;
        } catch (PDOException $e) {
            $deleteError = "This user can't be deleted because they have sales records linked to their account.";
        }
    }
}

$result = $db->query("SELECT userId, fullName, userName, role, status FROM users ORDER BY fullName ASC")->fetchAll();
$total_accounts = count($result);

$pageTitle = 'Users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/style_dashboard.css">
    <link rel="stylesheet" href="includes/style_users.css">
</head>
<body>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="pims-main">
        <?php include 'includes/header.php'; ?>

        <div class="container">

            <?php if ($deleteSuccess): ?>
                <div class="pos-alert pos-alert-success">User deleted successfully.</div>
            <?php endif; ?>
            <?php if ($deleteError): ?>
                <div class="pos-alert pos-alert-error"><?= htmlspecialchars($deleteError) ?></div>
            <?php endif; ?>
            <?php if ($addSuccess): ?>
                <div class="pos-alert pos-alert-success">User created successfully.</div>
            <?php endif; ?>

            <div class="users-toolbar">
                <div class="users-count"><?= $total_accounts ?> user account(s)</div>
                <button type="button" class="users-add-btn" onclick="openAddUserModal()">+ Add User</button>
            </div>

            <div class="panel full-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($result)): ?>
                            <tr><td colspan="5" class="empty-row">No users found.</td></tr>
                        <?php else: foreach ($result as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['fullName']) ?></td>
                                <td><?= htmlspecialchars($row['userName']) ?></td>
                                <td><?= htmlspecialchars($row['role']) ?></td>
                                <td>
                                    <span class="status-badge <?= strtolower($row['status']) === 'active' ? 'active' : '' ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td class="users-actions">
                                    <a href="users.php?delete=<?= $row['userId'] ?>"
                                       title="Delete"
                                       class="users-delete"
                                       onclick="return confirm('Delete <?= htmlspecialchars(addslashes($row['fullName'])) ?>? This cannot be undone.');"><i class="fa-solid fa-trash" style="color: rgb(251, 0, 0);"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserOverlay" style="<?= $addError ? 'display:flex;' : '' ?>">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add User</h3>
            <button type="button" class="modal-close" onclick="closeAddUserModal()">&times;</button>
        </div>

        <?php if ($addError): ?>
            <div class="pos-alert pos-alert-error" style="margin: 16px 24px 0;"><?= htmlspecialchars($addError) ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="add_user" value="1">

            <div class="form-group">
                <label>Name</label>
                <input type="text" name="fullName" value="<?= htmlspecialchars($_POST['fullName'] ?? '') ?>" required placeholder="e.g Yassin">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="userName" value="<?= htmlspecialchars($_POST['userName'] ?? '') ?>" required placeholder="username123">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required placeholder="e.g name@gmail.com">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="e.g +256 700000000">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="">Select role</option>
                        <option value="admin">Admin</option>
                        <option value="pharmacist">Pharmacist</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="modal-cancel-btn" onclick="closeAddUserModal()">Cancel</button>
                <button type="submit" class="modal-save-btn">Save User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddUserModal() {
    document.getElementById('addUserOverlay').style.display = 'flex';
}
function closeAddUserModal() {
    document.getElementById('addUserOverlay').style.display = 'none';
}
</script>

</body>
</html>
