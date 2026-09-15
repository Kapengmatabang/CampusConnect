<?php
// ============================================================
// Central RBAC helper for the admin panel.
// Roles:
//   Super Admin  = admins.org_id IS NULL. Full access to everything,
//                   including creating/removing organization admins.
//   Org Admin    = admins.org_id = X. Restricted to organization X's
//                   applications, interviews, and reports only.
// ============================================================

session_start();

if (!isset($_SESSION['user']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "campusconnect");
if ($conn->connect_error) die("DB Error");

$scopedOrgId  = $_SESSION['org_id'] ?? null;
$isSuperAdmin = empty($scopedOrgId);

/**
 * Restrict a page or action to the super admin only.
 * Use for things like managing organization admin accounts.
 */
function requireSuperAdmin($isSuperAdmin) {
    if (!$isSuperAdmin) {
        http_response_code(403);
        die("Access denied: this action is restricted to the super admin.");
    }
}

/**
 * Same idea as requireSuperAdmin(), but for whole pages an org admin
 * should never land on at all (Dashboard, Reports). Instead of a hard
 * 403 wall, this quietly sends them to their actual home page.
 */
function redirectIfNotSuperAdmin($isSuperAdmin, $redirectTo = 'applications.php') {
    if (!$isSuperAdmin) {
        header("Location: $redirectTo");
        exit();
    }
}

/**
 * Restrict an action to applications belonging to the logged-in admin's
 * own organization. Call this before approving, rejecting, scheduling
 * an interview for, or otherwise modifying a specific application_id.
 *
 * A scoped org admin who submits an application_id belonging to a
 * different organization (whether by mistake or by tampering with the
 * form) will be blocked here, even though the UI never shows them
 * that ID in the first place.
 */
function requireOrgAccessToApplication($conn, $applicationId, $scopedOrgId, $isSuperAdmin) {
    if ($isSuperAdmin) return; // super admin can act on any application

    $stmt = $conn->prepare("SELECT org_id FROM applications WHERE application_id = ?");
    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || (int)$row['org_id'] !== (int)$scopedOrgId) {
        http_response_code(403);
        die("Access denied: this application does not belong to your organization.");
    }
}