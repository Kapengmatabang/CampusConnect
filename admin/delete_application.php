<?php
require 'auth.php'; // sets $conn, $scopedOrgId, $isSuperAdmin; redirects if not logged in as admin

// Only the Main Admin can permanently delete an applicant record.
// Org admins never see this button, but this is the actual enforcement
// point — never trust the UI alone.
requireSuperAdmin($isSuperAdmin);

$id = intval($_POST['id'] ?? 0);
$statusFilter = $_POST['status_filter'] ?? 'All';

if ($id) {
    // Delete the uploaded file too, if one exists
    $res = $conn->prepare("SELECT attachment FROM applications WHERE application_id = ?");
    $res->bind_param("i", $id);
    $res->execute();
    $row = $res->get_result()->fetch_assoc();

    if ($row && !empty($row['attachment'])) {
        $filePath = "../uploads/" . $row['attachment'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    $del = $conn->prepare("DELETE FROM applications WHERE application_id = ?");
    $del->bind_param("i", $id);
    $del->execute();
}

header("Location: records.php?status=" . urlencode($statusFilter));
exit();