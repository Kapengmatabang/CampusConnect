<?php
session_start();

/* =========================================================
   STUDENT ACCESS CHECK
   ========================================================= */

if (!isset($_SESSION["user"]) || $_SESSION["usertype"] !== "student") {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION["user"];


/* =========================================================
   DATABASE CONNECTION
   ========================================================= */

$conn = new mysqli("localhost", "root", "", "campusconnect");

if ($conn->connect_error) {
    die("DB Error");
}

$conn->set_charset("utf8mb4");


/* =========================================================
   DEFAULT VARIABLES
   ========================================================= */

$error = "";
$success = "";

$orgLoad = 0;

$balanceVerdict = "No data yet";
$balanceClass = "neutral";

$avgStudy = 0;
$avgOrg = 0;

$insights = [];

$showSupportNudge = false;

$history = [];
$chartData = [];

$studyPoints = [];
$orgPoints = [];

$studyPolyline = "";
$orgPolyline = "";

$svgW = 700;
$svgH = 180;


/* =========================================================
   MOOD LABELS
   ========================================================= */

$moodLabels = [
    1 => "Very low",
    2 => "Low",
    3 => "Okay",
    4 => "Good",
    5 => "Great"
];


/* =========================================================
   SAVE CHECK-IN
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $mood = isset($_POST["mood"]) ? (int) $_POST["mood"] : 0;
    $stress = isset($_POST["stress"]) ? (int) $_POST["stress"] : 0;

    $study_hours = isset($_POST["study_hours"])
        ? (float) $_POST["study_hours"]
        : 0;

    $org_hours = isset($_POST["org_hours"])
        ? (float) $_POST["org_hours"]
        : 0;

    $sleep_hours = isset($_POST["sleep_hours"])
        ? (float) $_POST["sleep_hours"]
        : 0;

    $note = isset($_POST["note"])
        ? trim($_POST["note"])
        : "";


    /* Validate mood */
    if ($mood < 1 || $mood > 5) {
        $error = "Please select your mood.";
    }

    /* Validate stress */
    elseif ($stress < 1 || $stress > 5) {
        $error = "Please select your stress level.";
    }

    /* Validate hours */
    elseif (
        $study_hours < 0 || $study_hours > 24 ||
        $org_hours < 0 || $org_hours > 24 ||
        $sleep_hours < 0 || $sleep_hours > 24
    ) {
        $error = "Please enter valid hours.";
    }


    /* Insert check-in */
    if ($error === "") {

        $stmt = $conn->prepare("
            INSERT INTO wellness_checkins
            (
                student_id,
                mood,
                stress,
                study_hours,
                org_hours,
                sleep_hours,
                note
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "siiddds",
                $student_id,
                $mood,
                $stress,
                $study_hours,
                $org_hours,
                $sleep_hours,
                $note
            );

            if ($stmt->execute()) {
                $success = "Your check-in has been saved.";
            } else {
                $error = "Unable to save your check-in.";
            }

            $stmt->close();

        } else {
            $error = "Unable to prepare check-in.";
        }
    }
}


/* =========================================================
   ORGANIZATION LOAD
   Count approved organizations
   ========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM applications
    WHERE student_id = ?
    AND status = 'approved'
");

if ($stmt) {

    $stmt->bind_param("s", $student_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        $row = $result->fetch_assoc();

        if ($row) {
            $orgLoad = (int) $row["total"];
        }
    }

    $stmt->close();
}


/* =========================================================
   GET CHECK-IN HISTORY
   ========================================================= */

$stmt = $conn->prepare("
    SELECT
        mood,
        stress,
        study_hours,
        org_hours,
        sleep_hours,
        note,
        created_at
    FROM wellness_checkins
    WHERE student_id = ?
    ORDER BY created_at DESC
    LIMIT 14
");

if ($stmt) {

    $stmt->bind_param("s", $student_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
    }

    $stmt->close();
}


/* =========================================================
   SUPPORT NUDGE
   If last 3 check-ins have average stress >= 4
   ========================================================= */

$recentStress = array_slice($history, 0, 3);

if (count($recentStress) >= 3) {

    $stressTotal = 0;

    foreach ($recentStress as $row) {
        $stressTotal += (float) $row["stress"];
    }

    $stressAverage = $stressTotal / count($recentStress);

    if ($stressAverage >= 4) {
        $showSupportNudge = true;
    }
}


/* =========================================================
   CHART DATA
   Show oldest -> newest
   ========================================================= */

$chartData = array_reverse($history);


/* =========================================================
   AVERAGES
   ========================================================= */

if (count($history) > 0) {

    $studyTotal = 0;
    $orgTotal = 0;
    $sleepTotal = 0;
    $stressTotal = 0;
    $moodTotal = 0;

    foreach ($history as $row) {

        $studyTotal += (float) $row["study_hours"];
        $orgTotal += (float) $row["org_hours"];
        $sleepTotal += (float) $row["sleep_hours"];
        $stressTotal += (float) $row["stress"];
        $moodTotal += (float) $row["mood"];
    }

    $count = count($history);

    $avgStudy = $studyTotal / $count;
    $avgOrg = $orgTotal / $count;

    $avgSleep = $sleepTotal / $count;
    $avgStress = $stressTotal / $count;
    $avgMood = $moodTotal / $count;
}


/* =========================================================
   SCHOOL-LIFE BALANCE
   ========================================================= */

if (count($history) === 0) {

    $balanceVerdict = "No data yet";
    $balanceClass = "neutral";

} elseif ($avgStress >= 4 && $avgSleep < 6) {

    $balanceVerdict = "Needs attention";
    $balanceClass = "warn";

} elseif ($avgStudy >= 8 && $avgOrg >= 4) {

    $balanceVerdict = "Heavy workload";
    $balanceClass = "warn";

} elseif ($avgStudy >= 6 && $avgOrg <= 3 && $avgSleep >= 6) {

    $balanceVerdict = "Looking balanced";
    $balanceClass = "ok";

} elseif ($avgSleep >= 7 && $avgStress <= 3) {

    $balanceVerdict = "Looking balanced";
    $balanceClass = "ok";

} else {

    $balanceVerdict = "Keep an eye on it";
    $balanceClass = "neutral";
}


/* =========================================================
   PEARSON CORRELATION FUNCTION
   ========================================================= */

function pearsonCorrelation($x, $y)
{
    $n = count($x);

    if ($n < 2 || $n !== count($y)) {
        return 0;
    }

    $meanX = array_sum($x) / $n;
    $meanY = array_sum($y) / $n;

    $numerator = 0;
    $denomX = 0;
    $denomY = 0;

    for ($i = 0; $i < $n; $i++) {

        $dx = $x[$i] - $meanX;
        $dy = $y[$i] - $meanY;

        $numerator += $dx * $dy;
        $denomX += $dx * $dx;
        $denomY += $dy * $dy;
    }

    if ($denomX == 0 || $denomY == 0) {
        return 0;
    }

    return $numerator / sqrt($denomX * $denomY);
}


/* =========================================================
   CORRELATIONS
   ========================================================= */

$orgHoursData = [];
$studyHoursData = [];
$stressData = [];
$sleepData = [];

foreach ($history as $row) {

    $orgHoursData[] = (float) $row["org_hours"];
    $studyHoursData[] = (float) $row["study_hours"];
    $stressData[] = (float) $row["stress"];
    $sleepData[] = (float) $row["sleep_hours"];
}

$corrOrgStudy = pearsonCorrelation(
    $orgHoursData,
    $studyHoursData
);

$corrOrgStress = pearsonCorrelation(
    $orgHoursData,
    $stressData
);

$corrOrgSleep = pearsonCorrelation(
    $orgHoursData,
    $sleepData
);


/* =========================================================
   INSIGHTS
   ========================================================= */

if (count($history) > 0) {

    if ($corrOrgStudy <= -0.5) {

        $insights[] =
            "Higher organization hours tend to line up with lower study hours in your check-ins.";

    } elseif ($corrOrgStudy >= 0.5) {

        $insights[] =
            "Your organization activity tends to increase alongside your study hours.";

    } else {

        $insights[] =
            "Your organization hours do not show a strong relationship with your study hours.";

    }


    if ($corrOrgStress >= 0.5) {

        $insights[] =
            "Higher organization hours tend to coincide with higher stress levels.";

    } elseif ($corrOrgStress <= -0.5) {

        $insights[] =
            "Your check-ins show lower stress on days with more organization activity.";

    } else {

        $insights[] =
            "Your organization hours do not show a strong relationship with stress.";

    }


    if ($corrOrgSleep <= -0.5) {

        $insights[] =
            "More organization activity tends to coincide with less sleep.";

    } elseif ($corrOrgSleep >= 0.5) {

        $insights[] =
            "Your organization activity tends to coincide with more sleep.";

    } else {

        $insights[] =
            "Your organization hours do not show a strong relationship with sleep.";

    }


    if ($avgSleep < 6) {

        $insights[] =
            "Your average sleep is below 6 hours. Consider giving yourself more time to rest.";

    } elseif ($avgSleep >= 7) {

        $insights[] =
            "Your average sleep is 7 hours or more, which is a good sign for your overall routine.";

    }


    if ($avgStress >= 4) {

        $insights[] =
            "Your average stress level is high. Try to identify what activities are contributing most to it.";

    }

} else {

    $insights[] =
        "Complete a few check-ins to start seeing patterns in your school-life balance.";
}


/* =========================================================
   TREND CHART
   ========================================================= */

if (count($chartData) > 0) {

    $chartCount = count($chartData);

    /*
     * Limit visual chart to last 7 entries
     */
    if ($chartCount > 7) {
        $chartData = array_slice($chartData, -7);
    }

    $chartCount = count($chartData);

    $paddingLeft = 20;
    $paddingRight = 20;
    $paddingTop = 15;
    $paddingBottom = 15;

    $usableWidth =
        $svgW - $paddingLeft - $paddingRight;

    $usableHeight =
        $svgH - $paddingTop - $paddingBottom;


    $studyPoints = [];
    $orgPoints = [];

    foreach ($chartData as $index => $row) {

        if ($chartCount <= 1) {
            $x = $svgW / 2;
        } else {
            $x =
                $paddingLeft +
                ($index / ($chartCount - 1)) * $usableWidth;
        }


        /*
         * Chart scale:
         * 0 - 12 hours
         */
        $maxHours = 12;

        $studyValue = min(
            max((float) $row["study_hours"], 0),
            $maxHours
        );

        $orgValue = min(
            max((float) $row["org_hours"], 0),
            $maxHours
        );


        $studyY =
            $paddingTop +
            $usableHeight -
            ($studyValue / $maxHours) * $usableHeight;

        $orgY =
            $paddingTop +
            $usableHeight -
            ($orgValue / $maxHours) * $usableHeight;


        $studyPoints[] = [
            "x" => round($x, 2),
            "y" => round($studyY, 2),
            "v" => number_format(
                (float) $row["study_hours"],
                1
            )
        ];


        $orgPoints[] = [
            "x" => round($x, 2),
            "y" => round($orgY, 2),
            "v" => number_format(
                (float) $row["org_hours"],
                1
            )
        ];
    }


    $studyPolylineParts = [];
    $orgPolylineParts = [];

    foreach ($studyPoints as $point) {

        $studyPolylineParts[] =
            $point["x"] . "," . $point["y"];
    }

    foreach ($orgPoints as $point) {

        $orgPolylineParts[] =
            $point["x"] . "," . $point["y"];
    }


    $studyPolyline =
        implode(" ", $studyPolylineParts);

    $orgPolyline =
        implode(" ", $orgPolylineParts);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Wellness Check-in</title>

    <!-- STUDENT CHECK-IN CSS -->
    <link
        rel="stylesheet"
        href="../css/student/student_checkin.css"
    >

</head>


<body>

<div class="topbar">

    <a
        href="dashboard.php"
        class="back-btn"
    >
        Back to Dashboard
    </a>

</div>


<div class="container">

    <h1>
        Health Status Check
    </h1>


    <p class="subtitle">
        A quick, private way to check in with yourself.
        Only you can see this.
    </p>


    <!-- =====================================================
         ORGANIZATION LOAD BANNER
         ===================================================== -->

    <?php if ($orgLoad >= 4): ?>

        <div class="load-banner load-warn">

            <span class="load-icon">
                Notice:
            </span>

            <span>

                You're currently active in

                <strong>
                    <?= $orgLoad ?> organizations
                </strong>.

                That's a lot to juggle — it might be worth
                checking in with yourself about how it's feeling.

            </span>

        </div>

    <?php else: ?>

        <div class="load-banner load-ok">

            <span class="load-icon">
                Status:
            </span>

            <span>

                You're currently active in

                <strong>
                    <?= $orgLoad ?>
                </strong>

                organization<?= $orgLoad === 1 ? '' : 's' ?>.

            </span>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SCHOOL-LIFE BALANCE
         ===================================================== -->

    <div class="card">

        <div class="balance-header-row">

            <h3>
                School-Life Balance
            </h3>


            <span class="balance-badge <?= htmlspecialchars($balanceClass) ?>">

                <?= htmlspecialchars($balanceVerdict) ?>

            </span>

        </div>


        <p class="card-sub">

            Based on your own logged check-ins —
            how your org hours line up with study time,
            stress, and sleep.

        </p>


        <div class="balance-stats-row">

            <div class="balance-stat study">

                <div class="stat-label">
                    Avg. study hours / day
                </div>

                <div class="stat-value">

                    <?= number_format($avgStudy, 1) ?>h

                </div>

            </div>


            <div class="balance-stat org">

                <div class="stat-label">
                    Avg. org hours / day
                </div>

                <div class="stat-value">

                    <?= number_format($avgOrg, 1) ?>h

                </div>

            </div>

        </div>


        <!-- =================================================
             TREND CHART
             ================================================= -->

        <?php if (!empty($chartData)): ?>

            <div class="chart-legend">

                <span>

                    <span class="legend-dot study"></span>

                    Study

                </span>


                <span>

                    <span class="legend-dot org"></span>

                    Org

                </span>

            </div>


            <div class="trend-chart-wrap">

                <svg
                    class="trend-svg"
                    viewBox="0 0 <?= $svgW ?> <?= $svgH ?>"
                    preserveAspectRatio="none"
                >

                    <polyline
                        class="trend-line study"
                        points="<?= htmlspecialchars($studyPolyline) ?>"
                    ></polyline>


                    <polyline
                        class="trend-line org"
                        points="<?= htmlspecialchars($orgPolyline) ?>"
                    ></polyline>


                    <?php foreach ($studyPoints as $p): ?>

                        <circle
                            class="trend-dot study"
                            cx="<?= $p['x'] ?>"
                            cy="<?= $p['y'] ?>"
                            r="4"
                        >

                            <title>
                                Study: <?= $p['v'] ?>h
                            </title>

                        </circle>

                    <?php endforeach; ?>


                    <?php foreach ($orgPoints as $p): ?>

                        <circle
                            class="trend-dot org"
                            cx="<?= $p['x'] ?>"
                            cy="<?= $p['y'] ?>"
                            r="4"
                        >

                            <title>
                                Org: <?= $p['v'] ?>h
                            </title>

                        </circle>

                    <?php endforeach; ?>

                </svg>


                <div class="trend-labels">

                    <?php foreach ($chartData as $row): ?>

                        <span class="trend-day-label">

                            <?= date(
                                "M j",
                                strtotime($row["created_at"])
                            ) ?>

                        </span>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endif; ?>


        <!-- =================================================
             INSIGHTS
             ================================================= -->

        <ul class="insight-list">

            <?php foreach ($insights as $insight): ?>

                <li>

                    <span class="insight-icon">
                        Insight:
                    </span>

                    <span>

                        <?= htmlspecialchars($insight) ?>

                    </span>

                </li>

            <?php endforeach; ?>

        </ul>

    </div>


    <!-- =====================================================
         SUPPORT NUDGE
         ===================================================== -->

    <?php if ($showSupportNudge): ?>

        <div class="support-nudge">

            Your last few check-ins have shown consistently
            high stress levels. That's worth paying attention to.

            You don't have to handle it alone —
            the <strong>PLMUN Student Affairs / Guidance Office</strong>
            is there to talk things through.

            <br><br>

            (Update this contact detail to your actual guidance
            office info — email, office location, or hotline.)

        </div>

    <?php endif; ?>


    <!-- =====================================================
         CHECK-IN FORM
         ===================================================== -->

    <div class="card">

        <h3>
            How are you doing today?
        </h3>


        <?php if ($error): ?>

            <div class="alert error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <?php if ($success): ?>

            <div class="alert success">

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            id="checkinForm"
        >

            <label class="field-label">
                Mood
            </label>


            <input
                type="hidden"
                name="mood"
                id="moodInput"
                required
            >


            <div
                class="rating-group"
                id="moodGroup"
            >

                <?php foreach ($moodLabels as $val => $label): ?>

                    <div
                        class="rating-option"
                        data-value="<?= $val ?>"
                        onclick="selectRating('mood', <?= $val ?>)"
                    >

                        <span class="mood-label">

                            <?= htmlspecialchars($label) ?>

                        </span>

                        <?= $val ?>

                    </div>

                <?php endforeach; ?>

            </div>


            <label class="field-label">

                Stress level
                (1 = calm, 5 = overwhelmed)

            </label>


            <input
                type="hidden"
                name="stress"
                id="stressInput"
                required
            >


            <div
                class="rating-group"
                id="stressGroup"
            >

                <?php for ($s = 1; $s <= 5; $s++): ?>

                    <div
                        class="rating-option"
                        data-value="<?= $s ?>"
                        onclick="selectRating('stress', <?= $s ?>)"
                    >

                        <?= $s ?>

                    </div>

                <?php endfor; ?>

            </div>


            <div class="hours-row">


                <div>

                    <label class="field-label">
                        Study hours today
                    </label>

                    <input
                        type="number"
                        step="0.5"
                        min="0"
                        max="24"
                        name="study_hours"
                        value="0"
                        required
                    >

                </div>


                <div>

                    <label class="field-label">
                        Org activity hours today
                    </label>

                    <input
                        type="number"
                        step="0.5"
                        min="0"
                        max="24"
                        name="org_hours"
                        value="0"
                        required
                    >

                </div>


                <div>

                    <label class="field-label">
                        Sleep last night (hrs)
                    </label>

                    <input
                        type="number"
                        step="0.5"
                        min="0"
                        max="24"
                        name="sleep_hours"
                        value="0"
                        required
                    >

                </div>

            </div>


            <label class="field-label">

                Anything on your mind? (optional)

            </label>


            <textarea
                name="note"
                placeholder="Just for you — jot down anything you want to remember"
            ></textarea>


            <button
                type="submit"
                class="submit-btn"
            >

                Save Check-in

            </button>

        </form>

    </div>


    <!-- =====================================================
         HISTORY
         ===================================================== -->

    <div class="card">

        <h3>
            Your recent check-ins
        </h3>


        <?php if (empty($history)): ?>

            <div class="empty-state">

                No check-ins yet —
                your first one will show up here.

            </div>

        <?php else: ?>


            <?php foreach ($history as $h): ?>

                <div class="history-item">


                    <div class="history-date">

                        <?= date(
                            "M j",
                            strtotime($h["created_at"])
                        ) ?>

                    </div>


                    <div>


                        <div class="bar-row">

                            <span class="bar-label">
                                Mood
                            </span>


                            <div class="bar-track">

                                <div
                                    class="bar-fill mood"
                                    style="width: <?= ((int)$h["mood"]) * 20 ?>%"
                                ></div>

                            </div>

                        </div>


                        <div class="bar-row">

                            <span class="bar-label">
                                Stress
                            </span>


                            <div class="bar-track">

                                <div
                                    class="bar-fill stress"
                                    style="width: <?= ((int)$h["stress"]) * 20 ?>%"
                                ></div>

                            </div>

                        </div>


                    </div>


                    <div class="history-sleep">

                        Sleep:
                        <?= htmlspecialchars($h["sleep_hours"]) ?>h

                    </div>

                </div>

            <?php endforeach; ?>


        <?php endif; ?>

    </div>

</div>


<script>

function selectRating(group, value) {

    document.getElementById(
        group + "Input"
    ).value = value;


    document
        .querySelectorAll(
            "#" + group + "Group .rating-option"
        )
        .forEach(el => {

            el.classList.toggle(
                "selected",
                el.dataset.value == value
            );

        });

}


// Follow the theme set on the dashboard

const savedTheme =
    localStorage.getItem("cc_theme") || "dark";


if (savedTheme === "light") {

    document.documentElement.classList.add("light");

}

</script>


</body>
</html>