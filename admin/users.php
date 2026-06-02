<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Handle user status update
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    if ($_GET['action'] == 'approve') {
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
    } elseif ($_GET['action'] == 'reject') {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: users.php");
    exit();
}

$query = "SELECT * FROM users WHERE user_type = 'customer' ORDER BY created_at DESC";
$users = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - ShopVerse Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 bg-dark min-vh-100 p-0">
                <h4 class="text-white text-center py-3">ShopVerse Admin</h4>
                <a href="dashboard.php" class="text-white d-block p-3 text-decoration-none"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="users.php" class="text-white d-block p-3 text-decoration-none bg-secondary"><i class="fas fa-users"></i> Users</a>
                <a href="products.php" class="text-white d-block p-3 text-decoration-none"><i class="fas fa-box"></i> Products</a>
                <a href="orders.php" class="text-white d-block p-3 text-decoration-none"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="../logout.php" class="text-white d-block p-3 text-decoration-none"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            <div class="col-md-10 p-4">
                <h2>Manage Users</h2>
                <table class="table table-bordered mt-3">
                    <thead>
                        <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Registered</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : ($user['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                    <?php echo $user['status']; ?>
                                </span>
                            </td>
                            <td><?php echo $user['created_at']; ?></td>
                            <td>
                                <?php if($user['status'] == 'pending'): ?>
                                    <a href="?action=approve&id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm">Approve</a>
                                    <a href="?action=reject&id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user?')">Reject</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>