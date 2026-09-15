<?php

require 'auth.php';

$adminId = $_SESSION['user'];

$success = "";
$error = "";

// ================= GET CURRENT ADMIN INFO =================

$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    die("Admin account not found.");
}

// ================= UPDATE USERNAME =================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_username'])) {

    $newUsername = trim($_POST['username'] ?? '');

    if (empty($newUsername)) {

        $error = "Username cannot be empty.";

    } else {

        $check = $conn->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $check->bind_param("si", $newUsername, $adminId);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {

            $error = "That username is already taken.";

        } else {

            $update = $conn->prepare("UPDATE admins SET username = ? WHERE id = ?");
            $update->bind_param("si", $newUsername, $adminId);
            $update->execute();

            $admin['username'] = $newUsername;
            $success = "Username updated successfully.";
        }
    }
}

// ================= UPDATE PASSWORD =================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {

        $error = "All password fields are required.";

    } elseif (!password_verify($currentPassword, $admin['password'])) {

        $error = "Current password is incorrect.";

    } elseif ($newPassword !== $confirmPassword) {

        $error = "New password and confirmation do not match.";

    } elseif (strlen($newPassword) < 8) {

        $error = "New password must be at least 8 characters.";

    } else {

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $update->bind_param("si", $hashed, $adminId);
        $update->execute();

        $success = "Password updated successfully.";
    }
}

// ================= ADD ORG ADMIN (super admin only) =================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_org_admin'])) {

    requireSuperAdmin($isSuperAdmin);

    $orgUsername = trim($_POST['org_username'] ?? '');
    $orgPassword = $_POST['org_password'] ?? '';
    $orgId = intval($_POST['org_id'] ?? 0);

    if (empty($orgUsername) || empty($orgPassword) || !$orgId) {

        $error = "All fields are required to add an organization admin.";

    } elseif (strlen($orgPassword) < 8) {

        $error = "Organization admin password must be at least 8 characters.";

    } else {

        $check = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $check->bind_param("s", $orgUsername);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {

            $error = "That username is already in use.";

        } else {

            $hashed = password_hash($orgPassword, PASSWORD_DEFAULT);

            $insert = $conn->prepare(
                "INSERT INTO admins (username, password, org_id) VALUES (?, ?, ?)"
            );

            $insert->bind_param("ssi", $orgUsername, $hashed, $orgId);

            if ($insert->execute()) {

                $success = "Organization admin account created.";

            } else {

                $error = "That organization already has an admin assigned.";
            }
        }
    }
}

// ================= REMOVE ORG ADMIN (super admin only) =================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_org_admin'])) {

    requireSuperAdmin($isSuperAdmin);

    $removeId = intval($_POST['remove_id'] ?? 0);

    // Safety: only ever delete accounts that actually belong to an org,
    // never allow this form to touch a super admin account.

    $del = $conn->prepare(
        "DELETE FROM admins WHERE id = ? AND org_id IS NOT NULL"
    );

    $del->bind_param("i", $removeId);
    $del->execute();

    $success = "Organization admin access removed.";
}

// Builds a short lowercase "code" for the org-admin username suggestion
function orgCodeFor($org) {

    $group = strtoupper(trim($org['course_group']));

    if ($group === 'GENERAL' || $group === 'MEDIA') {

        $slug = strtolower(
            preg_replace('/[^a-z0-9]/i', '', $org['org_name'])
        );

        return substr($slug, 0, 12);
    }

    return strtolower($group);
}

// ================= DATA FOR THE PANEL =================

$orgAdmins = null;
$availableOrgs = null;

if ($isSuperAdmin) {

    $orgAdmins = $conn->query("
        SELECT ad.id, ad.username, ad.created_at, o.org_name
        FROM admins ad
        JOIN organizations o ON ad.org_id = o.org_id
        ORDER BY o.org_name ASC
    ");

    $availableOrgs = $conn->query("
        SELECT o.org_id, o.org_name, o.course_group
        FROM organizations o
        LEFT JOIN admins ad ON ad.org_id = o.org_id
        WHERE ad.id IS NULL
        ORDER BY o.org_name ASC
    ");

    $orgAdminCount = $conn->query(
        "SELECT COUNT(*) c FROM admins WHERE org_id IS NOT NULL"
    )->fetch_assoc()['c'];

    $nextOrgNumber = str_pad(
        $orgAdminCount + 1,
        2,
        '0',
        STR_PAD_LEFT
    );
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Settings</title>

    <link rel="stylesheet" href="../css/admin/settings.css">

</head>

<body>

<div class="sidebar">

    <h2>Admin</h2>

    <div class="theme-toggle" id="themeToggle">
        <span>
            <span id="themeIcon"></span>
            <span id="themeLabel">Dark Mode</span>
        </span>

        <span>Tap to switch</span>
    </div>

    <?php if ($isSuperAdmin): ?>

        <a href="dashboard.php">Dashboard</a>
        <a href="dashboard.php">Create Post</a>

    <?php else: ?>

        <a href="org_dashboard.php">Dashboard</a>

    <?php endif; ?>

    <a href="applications.php">Applications</a>
    <a href="interviews.php">Interviews</a>
    <a href="records.php">Records</a>

    <?php if ($isSuperAdmin): ?>
        <a href="reports.php">Reports</a>
    <?php endif; ?>

    <a href="settings.php">Settings</a>
    <a href="../logout.php">Logout</a>

</div>


<div class="main">

    <h2>
        Settings

        <?php if ($isSuperAdmin): ?>
            <span class="badge">Super Admin</span>
        <?php endif; ?>

    </h2>

    <p class="page-description">
        Manage your admin account<?= $isSuperAdmin ? ' and organization admin access' : '' ?>.
    </p>


    <?php if ($success): ?>

        <div class="alert success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- USERNAME -->

    <div class="panel">

        <h3>Username</h3>

        <p class="sub">
            This is what you use to log in to the admin dashboard.
        </p>

        <form method="POST">

            <label>Username</label>

            <input
                type="text"
                name="username"
                value="<?= htmlspecialchars($admin['username']) ?>"
                required
            >

            <button type="submit" name="update_username">
                Save Username
            </button>

        </form>

        <p class="meta">
            Account created:
            <?= date('F j, Y', strtotime($admin['created_at'])) ?>
        </p>

    </div>


    <!-- PASSWORD -->

    <div class="panel">

        <h3>Change Password</h3>

        <p class="sub">
            Use at least 8 characters. You'll need your current password to confirm.
        </p>

        <form method="POST">

            <label>Current Password</label>

            <input
                type="password"
                name="current_password"
                id="currentPassword"
                required
            >

            <label>New Password</label>

            <input
                type="password"
                name="new_password"
                id="newPassword"
                required
            >

            <label>Confirm New Password</label>

            <div class="password-row">

                <input
                    type="password"
                    name="confirm_password"
                    id="confirmPassword"
                    required
                >

                <label class="show-password-toggle">

                    <input
                        type="checkbox"
                        id="showPasswordToggle"
                        onclick="toggleAllPasswords()"
                    >

                    Show Password

                </label>

            </div>

            <button type="submit" name="update_password">
                Update Password
            </button>

        </form>

    </div>


    <?php if ($isSuperAdmin): ?>

    <!-- ORGANIZATION ADMINS -->

    <div class="panel">

        <h3>Organization Admins</h3>

        <p class="sub">
            Give each organization its own login
            (e.g. <code>orgid_orgname_admin</code>).
            They'll only see and manage their own organization's applications
            and posts once that scoping is wired in.
        </p>


        <?php if ($orgAdmins && $orgAdmins->num_rows > 0): ?>

            <?php while ($oa = $orgAdmins->fetch_assoc()): ?>

                <div class="org-admin-row">

                    <div class="who">

                        <strong>
                            <?= htmlspecialchars($oa['org_name']) ?>
                        </strong>

                        <small>
                            <?= htmlspecialchars($oa['username']) ?>
                            · added
                            <?= date('M j, Y', strtotime($oa['created_at'])) ?>
                        </small>

                    </div>


                    <form
                        method="POST"
                        onsubmit="return confirm('Remove access for this organization admin?');"
                    >

                        <input
                            type="hidden"
                            name="remove_id"
                            value="<?= $oa['id'] ?>"
                        >

                        <button
                            type="submit"
                            name="remove_org_admin"
                            class="danger"
                        >
                            Remove
                        </button>

                    </form>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty-state">
                No organization admins created yet.
            </div>

        <?php endif; ?>

    </div>


    <!-- ADD ORG ADMIN -->

    <div class="panel">

        <h3>Add Organization Admin</h3>

        <p class="sub">
            Create a login for a specific organization.
            A suggested username fills in automatically,
            feel free to customize it later on.
        </p>


        <?php if ($availableOrgs && $availableOrgs->num_rows > 0): ?>

        <form method="POST" id="addOrgAdminForm">

            <label>Organization</label>

            <select name="org_id" id="orgSelect" required>

                <option value="" disabled selected>
                    Select organization
                </option>

                <?php while ($org = $availableOrgs->fetch_assoc()):

                    $code = orgCodeFor($org);

                ?>

                    <option
                        value="<?= $org['org_id'] ?>"
                        data-code="<?= htmlspecialchars($code) ?>"
                    >
                        <?= htmlspecialchars($org['org_name']) ?>
                    </option>

                <?php endwhile; ?>

            </select>


            <label>Username</label>

            <input
                type="text"
                name="org_username"
                id="orgUsernameField"
                placeholder="e.g. org<?= $nextOrgNumber ?>_usc_admin"
                required
            >

            <p class="hint">
                Auto-generated when you pick an organization — you can still edit it before saving.
            </p>


            <label>Password</label>

            <div class="password-row">

                <input
                    type="password"
                    name="org_password"
                    id="orgPasswordField"
                    placeholder="At least 8 characters (they can change this later)"
                    required
                >

                <label class="show-password-toggle">

                    <input
                        type="checkbox"
                        id="showOrgPasswordToggle"
                        onclick="toggleOrgPassword()"
                    >

                    Show Password

                </label>

            </div>


            <button type="submit" name="add_org_admin">
                Create Account
            </button>

        </form>

        <?php else: ?>

            <div class="empty-state">
                Every organization already has an admin assigned.
            </div>

        <?php endif; ?>

    </div>


    <script>

    const orgSelectEl = document.getElementById("orgSelect");
    const orgUsernameFieldEl = document.getElementById("orgUsernameField");

    const nextOrgNumber = "<?= $nextOrgNumber ?>";

    let usernameManuallyEdited = false;


    if (orgUsernameFieldEl) {

        orgUsernameFieldEl.addEventListener("input", () => {
            usernameManuallyEdited = true;
        });

    }


    if (orgSelectEl) {

        orgSelectEl.addEventListener("change", () => {

            if (usernameManuallyEdited) return;

            const selected =
                orgSelectEl.options[orgSelectEl.selectedIndex];

            const code =
                selected && selected.dataset.code
                    ? selected.dataset.code
                    : 'org';

            orgUsernameFieldEl.value =
                "org" + nextOrgNumber + "_" + code + "_admin";

        });

    }


    function toggleOrgPassword() {

        const field =
            document.getElementById("orgPasswordField");

        const checkbox =
            document.getElementById("showOrgPasswordToggle");

        if (field) {

            field.type =
                checkbox.checked ? "text" : "password";

        }

    }

    </script>

    <?php endif; ?>

</div>


<script>

function toggleAllPasswords() {

    const fields = [
        "currentPassword",
        "newPassword",
        "confirmPassword"
    ];

    const checkbox =
        document.getElementById("showPasswordToggle");

    fields.forEach(id => {

        const el = document.getElementById(id);

        if (el) {
            el.type =
                checkbox.checked ? "text" : "password";
        }

    });

}


// ============ THEME TOGGLE ============

const root = document.documentElement;

const themeToggle =
    document.getElementById("themeToggle");

const themeIcon =
    document.getElementById("themeIcon");

const themeLabel =
    document.getElementById("themeLabel");


function applyTheme(theme) {

    if (theme === "light") {

        root.classList.add("light");

        themeIcon.textContent = "";
        themeLabel.textContent = "Light Mode";

    } else {

        root.classList.remove("light");

        themeIcon.textContent = "";
        themeLabel.textContent = "Dark Mode";

    }

}


const savedTheme =
    localStorage.getItem("cc_admin_theme") || "dark";

applyTheme(savedTheme);


themeToggle.addEventListener("click", () => {

    const isLight =
        root.classList.contains("light");

    const newTheme =
        isLight ? "dark" : "light";

    applyTheme(newTheme);

    localStorage.setItem(
        "cc_admin_theme",
        newTheme
    );

});

</script>

</body>
</html>