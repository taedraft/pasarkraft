<?php
session_start();
require "../db_connect.php";

// Redirect if not admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login_admin.php");
    exit();
}

$type = isset($_GET["type"]) ? $_GET["type"] : "users";
$now = date("Ymd_His");
$filename = "pasarkraft_export_{$type}_{$now}.csv";

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");

$output = fopen("php://output", "w");

if ($type === "recent") {
    fputcsv($output, ["User ID", "Name", "Role", "Date Joined", "Status"]);

    $recent_res = $conn->query("
        SELECT u.id, u.firstname, u.lastname, u.username, u.role, u.status, u.created_at, a.shopname, a.approval_status
        FROM users u
        LEFT JOIN artisans a ON u.id = a.user_id
        WHERE u.role != 'admin'
        ORDER BY u.created_at DESC
        LIMIT 5
    ");

    while ($row = $recent_res->fetch_assoc()) {
        $name = $row["role"] === "seller" ? $row["shopname"] : trim($row["firstname"] . " " . $row["lastname"]);
        $status = $row["role"] === "seller" && $row["approval_status"] === "pending"
            ? "Pending"
            : ucfirst($row["status"]);

        fputcsv($output, [
            $row["id"],
            $name,
            ucfirst($row["role"]),
            $row["created_at"],
            $status
        ]);
    }
} else {
    fputcsv($output, ["User ID", "Name/Shop", "Email", "Contact", "Role", "Status", "Date Joined"]);

    $users_res = $conn->query("
        SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.role, u.status, u.created_at,
               a.shopname, a.phone, a.approval_status
        FROM users u
        LEFT JOIN artisans a ON u.id = a.user_id
        WHERE u.role != 'admin'
        ORDER BY u.created_at DESC
    ");

    while ($row = $users_res->fetch_assoc()) {
        $name = $row["role"] === "seller" ? $row["shopname"] : trim($row["firstname"] . " " . $row["lastname"]);
        $contact = $row["role"] === "seller" ? $row["phone"] : "";
        $status = $row["role"] === "seller" && $row["approval_status"] === "pending"
            ? "Pending"
            : ucfirst($row["status"]);

        fputcsv($output, [
            $row["id"],
            $name,
            $row["email"],
            $contact,
            ucfirst($row["role"]),
            $status,
            $row["created_at"]
        ]);
    }
}

fclose($output);
exit();
