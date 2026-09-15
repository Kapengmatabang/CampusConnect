```php
<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feed | PLMUN CampusConnect</title>

<link rel="icon" type="image/png" href="img/favicon.png">
<link rel="stylesheet" href="css/feed.css">
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-logo">
        <img src="img/cclogo.png" alt="PLMUN Logo">
    </div>

    <div class="nav-links" id="navLinks">
        <a href="index.html">Home</a>
        <a href="feed.php" class="active">Feed</a>
        <a href="index.html#about">About</a>
        <a href="index.html#contact">Contact</a>

        <div class="nav-buttons">
        <?php if (isset($_SESSION['org_id'])): ?>

            <a href="logout.php">
                <button class="post-submit-btn">Logout</button>
            </a>

        <?php else: ?>

            <a href="login.php">
                <button class="post-submit-btn">Login</button>
            </a>

            <a href="signup.php">
                <button class="filter-btn">Sign Up</button>
            </a>

        <?php endif; ?>
        </div>
    </div>
</nav>

<!-- FLOATING MOBILE BOTTOM NAVBAR -->
<nav class="mobile-navbar" id="mobileNavbar">

    <a href="index.html" class="mnav-item">
        <svg viewBox="0 0 24 24">
            <path d="M3 11.5 12 4l9 7.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"/>

            <path d="M5.5 10v9a1 1 0 0 0 1 1H9v-5.5a1.5 1.5 0 0 1 1.5-1.5h3a1.5 1.5 0 0 1 1.5 1.5V20h2.5a1 1 0 0 0 1-1v-9"
                  stroke-linecap="round"
                  stroke-linejoin="round"/>
        </svg>
    </a>

    <a href="feed.php" class="mnav-item active">
        <svg viewBox="0 0 24 24">
            <rect x="3.5" y="5" width="17" height="16" rx="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"/>

            <path d="M8 3v4M16 3v4M3.5 10h17"
                  stroke-linecap="round"
                  stroke-linejoin="round"/>
        </svg>
    </a>

    <a href="signup.php" class="mnav-fab" aria-label="Sign Up">
        <svg viewBox="0 0 24 24">
            <path d="M12 5v14M5 12h14"
                  stroke-linecap="round"
                  stroke-linejoin="round"/>
        </svg>
    </a>

    <a href="index.html#about" class="mnav-item">
        <svg viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7"
                    stroke-linecap="round"
                    stroke-linejoin="round"/>

            <path d="M20.5 20.5 16.5 16.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"/>
        </svg>
    </a>

    <?php if (isset($_SESSION['org_id'])): ?>

    <a href="logout.php" class="mnav-item">
        <svg viewBox="0 0 24 24">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"
                  stroke-linecap="round"
                  stroke-linejoin="round"/>

            <path d="M16 17l5-5-5-5M21 12H9"
                  stroke-linecap="round"
                  stroke-linejoin="round"/>
        </svg>
    </a>

    <?php else: ?>

    <a href="login.php" class="mnav-item">
        <svg viewBox="0 0 24 24">
            <circle cx="9" cy="8" r="3.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"/>

            <path d="M4.5 19c.6-3 2.5-4.6 4.5-4.6S13 16 13.5 19"
                  stroke-linecap="round"
                  stroke-linejoin="round"/>

            <path d="M15.5 8h5M15.5 12h5M15.5 16h3"
                  stroke-linecap="round"
                  stroke-linejoin="round"/>
        </svg>
    </a>

    <?php endif; ?>

</nav>

<!-- PAGE HEADER -->
<div class="page-header">
    <h1>Organization Feed</h1>
    <p>Updates, announcements, and events from all campus organizations.</p>
</div>

<!-- MAIN LAYOUT -->
<div class="feed-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <!-- FILTER CARD -->
        <div class="card">

            <h3>Filter by Category</h3>

            <div class="filter-list" id="filterBar">

                <button class="filter-btn active" data-tag="All">
                    All Posts
                </button>

                <button class="filter-btn" data-tag="Events">
                    Events
                </button>

                <button class="filter-btn" data-tag="Announcements">
                    Announcements
                </button>

                <button class="filter-btn" data-tag="Recruitment">
                    Recruitment
                </button>

                <button class="filter-btn" data-tag="General">
                    General
                </button>

            </div>

        </div>

        <?php if (isset($_SESSION['org_id'])): ?>

        <!-- COMPOSER -->
        <div class="card composer">

            <h3>
                Post as
                <?php echo htmlspecialchars($_SESSION['org_name'] ?? 'your organization'); ?>
            </h3>

            <form id="postForm" enctype="multipart/form-data">

                <input
                    type="text"
                    name="title"
                    placeholder="Post title"
                    required
                >

                <textarea
                    name="content"
                    placeholder="Share your update, event, or announcement..."
                    required
                ></textarea>

                <select name="tag">

                    <option value="General">
                        General
                    </option>

                    <option value="Events">
                        Events
                    </option>

                    <option value="Announcements">
                        Announcements
                    </option>

                    <option value="Recruitment">
                        Recruitment
                    </option>

                </select>

                <label class="file-label">

                    <span id="fileName">
                        Attach an image (optional)
                    </span>

                    <input
                        type="file"
                        name="image"
                        id="imageInput"
                        accept="image/*"
                    >

                </label>

                <button
                    type="submit"
                    class="post-submit-btn"
                    id="submitBtn"
                >
                    Post Update
                </button>

                <div
                    class="composer-msg"
                    id="composerMsg"
                ></div>

            </form>

        </div>

        <?php else: ?>

        <!-- LOGIN PROMPT -->
        <div class="card">

            <h3>Want to post?</h3>

            <p class="login-prompt">

                Only registered organizations can post updates.

                <a href="login.php">Log in</a>
                or
                <a href="signup.php">sign up</a>
                to get started.

            </p>

        </div>

        <?php endif; ?>

    </aside>

    <!-- FEED -->
    <main>

        <div class="timeline" id="feed">

            <p class="feed-status loading">
                Loading posts...
            </p>

        </div>

    </main>

</div>

<!-- FOOTER -->
<div class="footer">
    © 2026 PLMUN CampusConnect
</div>

<script>

let currentTag = "All";

/* LOAD POSTS */
function loadPosts() {

    fetch(
        "fetch_posts.php?tag=" +
        encodeURIComponent(currentTag)
    )

    .then(res => res.text())

    .then(data => {

        document.getElementById("feed").innerHTML =
            data.trim() === ""
                ? '<p class="feed-status">No posts yet.</p>'
                : data;

    })

    .catch(() => {

        document.getElementById("feed").innerHTML =
            '<p class="feed-status error">Failed to load posts</p>';

    });
}


/* FILTER BUTTONS */
document.querySelectorAll(".filter-btn").forEach(btn => {

    btn.addEventListener("click", () => {

        document
            .querySelectorAll(".filter-btn")
            .forEach(b => b.classList.remove("active"));

        btn.classList.add("active");

        currentTag = btn.dataset.tag;

        loadPosts();

    });

});


/* FILE NAME PREVIEW */
const imageInput =
    document.getElementById("imageInput");

if (imageInput) {

    imageInput.addEventListener("change", () => {

        const fileName =
            imageInput.files.length
                ? imageInput.files[0].name
                : "Attach an image (optional)";

        document.getElementById("fileName").textContent =
            fileName;

    });

}


/* SUBMIT NEW POST */
const postForm =
    document.getElementById("postForm");

if (postForm) {

    postForm.addEventListener("submit", (e) => {

        e.preventDefault();

        const submitBtn =
            document.getElementById("submitBtn");

        const msg =
            document.getElementById("composerMsg");

        submitBtn.disabled = true;
        submitBtn.textContent = "Posting...";

        msg.textContent = "";
        msg.className = "composer-msg";


        fetch("insert_post.php", {

            method: "POST",

            body: new FormData(postForm)

        })

        .then(res => res.json())

        .then(data => {

            submitBtn.disabled = false;
            submitBtn.textContent = "Post Update";


            if (data.success) {

                msg.textContent = data.message;

                msg.classList.add("success");

                postForm.reset();

                document.getElementById("fileName").textContent =
                    "Attach an image (optional)";

                loadPosts();

            } else {

                msg.textContent = data.message;

                msg.classList.add("error");

            }

        })

        .catch(() => {

            submitBtn.disabled = false;
            submitBtn.textContent = "Post Update";

            msg.textContent =
                "Something went wrong. Please try again.";

            msg.classList.add("error");

        });

    });

}


/* INITIAL LOAD */
loadPosts();


/* AUTO REFRESH */
setInterval(loadPosts, 8000);

</script>

</body>
</html>
```
